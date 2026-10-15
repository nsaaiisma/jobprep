<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InterviewController extends Controller
{
    public function index()
    {
        return view('pages.interview', ['hasCv' => session()->has('cv_text')]);
    }

    public function answer(Request $r, AiService $ai)
    {
        set_time_limit(60);

        $r->validate([
            'question' => 'required|string|max:1000',
            'role' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:150',
            'style' => 'required|in:star,singkat,formal',
            'language' => 'required|in:id,en',
        ]);

        $style = [
            'star' => 'Gunakan kerangka STAR (Situation, Task, Action, Result) dengan hasil terukur bila relevan.',
            'singkat' => 'Jawab ringkas, maksimal 120 kata, langsung ke inti.',
            'formal' => 'Gunakan bahasa formal dan profesional, terstruktur dalam paragraf.',
        ][$r->style];

        $lang = $r->language === 'en' ? 'English' : 'Bahasa Indonesia';
        $cv = mb_substr((string) session('cv_text', ''), 0, 6000);

        $system = "Kamu pelatih wawancara kerja. Bantu pelamar menyusun jawaban yang jujur, percaya diri, dan natural diucapkan.
$style
Bahasa jawaban: $lang.
Aturan: jika CV tersedia, pakai hanya fakta dari CV. Jika CV tidak memuat pengalaman yang dibutuhkan, beri kerangka jawaban dengan placeholder [isi pengalaman kamu], jangan mengarang.
Format markdown: **Jawaban contoh**, lalu **Kenapa jawaban ini kuat** (2-3 poin), lalu **Hindari** (1-2 poin).";

        $user = "Pertanyaan recruiter: {$r->question}\nPosisi: ".($r->role ?: '-')."\nPerusahaan: ".($r->company ?: '-')
              ."\n\nCV pelamar:\n".($cv ?: '(belum diunggah)');

        try {
            return response()->json(['answer' => $ai->ask($system, $user, false, 1500)]);
        } catch (\Throwable $e) {
            $status = str_contains($e->getMessage(), 'terlalu lama') ? 504 : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function assistWithAudio(Request $request, AiService $ai)
    {
        set_time_limit(60);
        $data = $request->validate([
            'audio' => 'required|file|extensions:wav|max:1024',
            'context' => 'nullable|string|max:5000',
            'role' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:150',
            'language' => 'required|in:id,en',
        ]);

        $audio = $request->file('audio')->get();
        if (strlen($audio) < 44 || substr($audio, 0, 4) !== 'RIFF' || substr($audio, 8, 4) !== 'WAVE') {
            return response()->json(['error' => 'Audio harus berupa file WAV yang valid.'], 422);
        }

        try {
            return response()->json($ai->analyzeInterviewAudio(
                $audio,
                $data['context'] ?? '',
                $data['role'] ?? '',
                $data['company'] ?? '',
                $data['language'],
                mb_substr((string) session('cv_text', ''), 0, 12000),
            ));
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 502);
        } catch (\Throwable $exception) {
            Log::error('Live interview audio analysis failed.', ['exception' => $exception]);

            return response()->json(['error' => 'Analisis audio gagal. Periksa koneksi dan konfigurasi Gemini, lalu coba lagi.'], 502);
        }
    }

    /**
     * Susun saran jawaban dari akumulasi transkrip (teks), tanpa menunggu
     * model menilai pertanyaan "lengkap" pada potongan audio 6 detik.
     */
    public function suggestFromTranscript(Request $request, AiService $ai)
    {
        set_time_limit(60);

        $data = $request->validate([
            'transcript' => 'required|string|max:5000',
            'role' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:150',
            'language' => 'required|in:id,en',
        ]);

        try {
            return response()->json($ai->suggestAnswerFromTranscript(
                $data['transcript'],
                $data['role'] ?? '',
                $data['company'] ?? '',
                $data['language'],
                mb_substr((string) session('cv_text', ''), 0, 6000),
            ));
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 502);
        } catch (\Throwable $exception) {
            Log::error('Live interview suggestion failed.', ['exception' => $exception]);

            return response()->json(['error' => 'Gagal menyusun saran. Coba lagi.'], 502);
        }
    }
}
