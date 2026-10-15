<?php

namespace Tests\Feature;

use App\Services\AiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    public function test_gemini_text_uses_interactions_api_and_google_search_tool(): void
    {
        config([
            'ai.driver' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'test-model',
            'ai.gemini.fallback_model' => null,
            'ai.gemini.grounding' => true,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [['type' => 'text', 'text' => 'Jawaban dari Gemini.']],
                ]],
            ]),
        ]);

        $answer = app(AiService::class)->ask('Jawab dengan ringkas.', 'Apa kabar terbaru?', true, 500);

        $this->assertSame('Jawaban dari Gemini.', $answer);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && $request['model'] === 'test-model'
            && $request['input'] === 'Apa kabar terbaru?'
            && $request['system_instruction'] === 'Jawab dengan ringkas.'
            && $request['generation_config']['max_output_tokens'] === 500
            && $request['tools'] === [['type' => 'google_search']]
            && $request['store'] === false
        );
    }

    public function test_gemini_json_answer_uses_interactions_json_response_format(): void
    {
        config([
            'ai.driver' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'test-model',
            'ai.gemini.fallback_model' => null,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [['type' => 'text', 'text' => '{"ready":true}']],
                ]],
            ]),
        ]);

        $answer = app(AiService::class)->askJson('Balas JSON.', 'Apakah sudah siap?');

        $this->assertSame(['ready' => true], $answer);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
            && ($request['response_format']['type'] ?? null) === 'text'
            && ($request['response_format']['mime_type'] ?? null) === 'application/json'
            && ! isset($request['tools'])
        );
    }
}
