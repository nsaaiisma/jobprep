<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InterviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_automatic_live_audio_controls_and_consent_notice(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('interview'));

        $response->assertOk();
        $response->assertSee('Bantuan audio langsung');
        $response->assertSee('Ambil audio GMeet');
        $response->assertSee('Share tab audio');
        $response->assertSee('Generate');
        $response->assertSee('Selesai');
        $response->assertSee('Buka jendela melayang');
        $response->assertSee('Mikrofon:', escape: false);
        $response->assertSee('Audio GMeet:', escape: false);
        $response->assertSee('id="microphone-name"', escape: false);
        $response->assertSee('id="tab-audio-name"', escape: false);
        $response->assertSee('navigator.mediaDevices.getUserMedia', escape: false);
        $response->assertSee('navigator.mediaDevices?.getDisplayMedia', escape: false);
        $response->assertSee('AudioWorklet');
        $response->assertSee('jobprep-capture', escape: false);
        $response->assertSee('visibilitychange', escape: false);
        $response->assertSee('await connectAudioInput()', escape: false);
        $response->assertDontSee('id="audio-device"', escape: false);
        $response->assertSee('id="generate-live-audio"', escape: false);
        $response->assertSee('id="open-floating-window"', escape: false);
        $response->assertSee('id="live-audio-panel"', escape: false);
        $response->assertSee('id="audio-level"', escape: false);
        $response->assertSee('id="audio-detection-status"', escape: false);
        $response->assertSee('Suara terdeteksi; membuat transkrip dan saran otomatis');
        $response->assertSee('stopAudioGraph', escape: false);
        $response->assertSee('await audioQueue', escape: false);
        $response->assertSee('Tidak ada kalimat yang terdeteksi.', escape: false);
        $response->assertSee('const samplesPerChunk=16000*6', escape: false);
        $response->assertSee('const speechRmsThreshold=0.0025', escape: false);
        $response->assertSee('autoGainControl:true', escape: false);
        $response->assertSee('audioDetectionStatus.textContent=`Analisis audio gagal: ${audioFailure}`', escape: false);
        $response->assertDontSee('Mulai bantuan audio');
        $response->assertDontSee('Muat perangkat audio');
        $response->assertSee("popupUrl.searchParams.set('audio_popup','1')", escape: false);
        $response->assertSee('const audioChunkUrl', escape: false);
        $response->assertSee('Minta saran dari transkrip');
        $response->assertSee('id="request-suggestion"', escape: false);
        $response->assertSee('wawancara\\/saran', escape: false);
    }

    public function test_audio_popup_renders_the_same_live_audio_controls(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('interview', [
            'audio_popup' => '1',
        ]));

        $response->assertOk();
        $response->assertSee('id="live-audio-panel"', escape: false);
        $response->assertSee('id="generate-live-audio"', escape: false);
        $response->assertSee('id="finish-live-audio"', escape: false);
        $response->assertSee('id="microphone-name"', escape: false);
        $response->assertDontSee('id="audio-device"', escape: false);
        $response->assertSee("get('audio_popup')==='1'", escape: false);
    }

    public function test_guest_cannot_submit_live_interview_audio(): void
    {
        $response = $this->postJson(route('interview.audio'));

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_receives_transcript_and_suggestion_from_audio(): void
    {
        config([
            'ai.driver' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'test-model',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'transcript' => 'Ceritakan tentang diri Anda.',
                            'question_complete' => true,
                            'suggested_answer' => 'Sampaikan pengalaman yang relevan.',
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())->post(route('interview.audio'), [
            'audio' => UploadedFile::fake()->createWithContent('segment.wav', $this->wavAudio()),
            'context' => 'Pertanyaan wawancara sebelumnya.',
            'role' => 'Software Engineer',
            'company' => 'Example Co',
            'language' => 'id',
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertExactJson([
            'transcript' => 'Ceritakan tentang diri Anda.',
            'question_complete' => true,
            'suggested_answer' => 'Sampaikan pengalaman yang relevan.',
            'opening' => '',
            'key_points' => [],
            'follow_up_questions' => [],
            'ask_back' => '',
            'added_details' => [],
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request['model'] === 'test-model'
            && $request['input'][1]['type'] === 'audio'
            && $request['input'][1]['mime_type'] === 'audio/wav'
            && $request['input'][1]['data'] === base64_encode($this->wavAudio())
            && $request['response_format']['mime_type'] === 'application/json'
            && $request['store'] === false
        );
    }

    public function test_audio_analysis_uses_fallback_model_when_primary_is_overloaded(): void
    {
        config([
            'ai.driver' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'test-model',
            'ai.gemini.fallback_model' => 'gemini-3.5-flash-lite',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/interactions' => function (Request $request) {
                if ($request['model'] === 'test-model') {
                    return Http::response([
                        'error' => ['message' => 'The model is currently experiencing high demand.'],
                    ], 503);
                }

                return Http::response([
                    'steps' => [[
                        'type' => 'model_output',
                        'content' => [[
                            'type' => 'text',
                            'text' => json_encode([
                                'transcript' => 'Mengapa Anda tertarik dengan posisi ini?',
                                'question_complete' => true,
                                'suggested_answer' => 'Hubungkan minat dan pengalaman dengan posisi ini.',
                            ]),
                        ]],
                    ]],
                ]);
            },
        ]);

        $response = $this->actingAs(User::factory()->create())->post(route('interview.audio'), [
            'audio' => UploadedFile::fake()->createWithContent('segment.wav', $this->wavAudio()),
            'language' => 'id',
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertExactJson([
            'transcript' => 'Mengapa Anda tertarik dengan posisi ini?',
            'question_complete' => true,
            'suggested_answer' => 'Hubungkan minat dan pengalaman dengan posisi ini.',
            'opening' => '',
            'key_points' => [],
            'follow_up_questions' => [],
            'ask_back' => '',
            'added_details' => [],
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request['model'] === 'test-model'
        );
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request['model'] === 'gemini-3.5-flash-lite'
        );
    }

    public function test_authenticated_user_cannot_submit_non_wav_audio(): void
    {
        Http::preventStrayRequests();

        $response = $this->actingAs(User::factory()->create())->post(route('interview.audio'), [
            'audio' => UploadedFile::fake()->createWithContent('segment.wav', 'not a wav file'),
            'language' => 'id',
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
        $response->assertJsonPath('error', 'Audio harus berupa file WAV yang valid.');
        Http::assertNothingSent();
    }

    public function test_audio_without_a_complete_question_returns_no_answer_suggestion(): void
    {
        config([
            'ai.driver' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'test-model',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'transcript' => 'Bisa ceritakan sedikit tentang...',
                            'question_complete' => false,
                            'suggested_answer' => '',
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())->post(route('interview.audio'), [
            'audio' => UploadedFile::fake()->createWithContent('segment.wav', $this->wavAudio()),
            'language' => 'id',
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertExactJson([
            'transcript' => 'Bisa ceritakan sedikit tentang...',
            'question_complete' => false,
            'suggested_answer' => '',
            'opening' => '',
            'key_points' => [],
            'follow_up_questions' => [],
            'ask_back' => '',
            'added_details' => [],
        ]);
    }

    public function test_digital_silence_returns_no_suggestion_without_calling_gemini(): void
    {
        config([
            'ai.driver' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'test-model',
        ]);
        Http::preventStrayRequests();

        $response = $this->actingAs(User::factory()->create())->post(route('interview.audio'), [
            'audio' => UploadedFile::fake()->createWithContent('segment.wav', $this->wavAudio(silent: true)),
            'language' => 'id',
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertExactJson([
            'transcript' => '',
            'question_complete' => false,
            'suggested_answer' => '',
            'opening' => '',
            'key_points' => [],
            'follow_up_questions' => [],
            'ask_back' => '',
            'added_details' => [],
        ]);
    }

    public function test_authenticated_user_gets_suggestion_from_transcript(): void
    {
        config([
            'ai.driver' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'test-model',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'text',
                        'text' => json_encode([
                            'suggested_answer' => 'Ceritakan pengalaman yang paling relevan.',
                            'opening' => 'Terima kasih atas pertanyaannya.',
                            'key_points' => ['Pengalaman relevan', 'Hasil terukur'],
                            'follow_up_questions' => [],
                            'ask_back' => '',
                            'added_details' => [],
                        ]),
                    ]],
                ]],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->create())->postJson(route('interview.suggest'), [
            'transcript' => 'Ceritakan tentang diri Anda dan pengalaman Anda.',
            'role' => 'Software Engineer',
            'company' => 'Example Co',
            'language' => 'id',
        ]);

        $response->assertOk()->assertExactJson([
            'suggested_answer' => 'Ceritakan pengalaman yang paling relevan.',
            'opening' => 'Terima kasih atas pertanyaannya.',
            'key_points' => ['Pengalaman relevan', 'Hasil terukur'],
            'follow_up_questions' => [],
            'ask_back' => '',
            'added_details' => [],
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && str_contains((string) $request['input'], 'Ceritakan tentang diri Anda')
            && ($request['response_format']['mime_type'] ?? null) === 'application/json'
        );
    }

    public function test_guest_cannot_request_suggestion_from_transcript(): void
    {
        $response = $this->postJson(route('interview.suggest'));

        $response->assertUnauthorized();
    }

    private function wavAudio(bool $silent = false): string
    {
        $samples = $silent ? str_repeat("\0", 320) : str_repeat("\x00\x04\x00\xFC", 80);

        return 'RIFF'
            .pack('V', 36 + strlen($samples))
            .'WAVEfmt '
            .pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16)
            .'data'
            .pack('V', strlen($samples))
            .$samples;
    }
}
