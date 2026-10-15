<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use App\Services\CvParser;
use Illuminate\Http\Request;

class CvController extends Controller
{
    public function index()
    {
        return view('pages.cv', ['hasCv' => session()->has('cv_text')]);
    }

    public function analyze(Request $r, CvParser $parser, AiService $ai)
    {
        // Naikkan batas eksekusi khusus request AI (default PHP 30s < timeout HTTP lama).
        set_time_limit(60);

        $r->validate([
            'cv' => 'required|file|mimes:pdf,docx,txt|max:5120',
            'role' => 'nullable|string|max:150',
            'job_desc' => 'nullable|string|max:3000',
        ]);

        $cv = $parser->extract($r->file('cv'));
        if (mb_strlen($cv) < 100) {
            return response()->json(['error' => 'Teks CV tidak terbaca (kurang dari 100 karakter). Pastikan bukan hasil scan gambar — jika scan, gunakan PDF berisi teks atau DOCX.'], 422);
        }
        // Potong agar cepat: CV panjang = token banyak = lambat + rawan 429.
        $cv = mb_substr($cv, 0, 8000);
        session(['cv_text' => $cv, 'cv_role' => $r->role]);

        $system = <<<'TXT'
Kamu konsultan karier dan recruiter berpengalaman di Indonesia. Analisis CV pelamar secara jujur dan spesifik.
Skema JSON:
{
 "ringkasan": "2-3 kalimat profil pelamar",
 "level": "Fresh graduate|Junior|Mid|Senior",
 "skill_teknis": ["..."],
 "skill_non_teknis": ["..."],
 "kekuatan": ["..."],
 "gap": ["kekurangan atau hal yang perlu dilengkapi"],
 "skor_kecocokan": angka 0-100 (null jika tidak ada posisi/deskripsi target),
 "alasan_skor": "...",
 "saran_perbaikan_cv": ["..."],
 "prediksi_pertanyaan": ["8 pertanyaan yang kemungkinan ditanyakan recruiter berdasarkan CV ini"]
}
Jawab dalam Bahasa Indonesia. Jangan mengarang pengalaman yang tidak ada di CV.
TXT;

        $user = 'Posisi target: '.($r->role ?: '-')."\nDeskripsi lowongan:\n".mb_substr((string) $r->job_desc ?: '-', 0, 2000)."\n\nCV:\n".$cv;

        $started = microtime(true);

        try {
            $schema = [
                'type' => 'object',
                'properties' => [
                    'ringkasan' => ['type' => 'string'],
                    'level' => ['type' => 'string'],
                    'skill_teknis' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'skill_non_teknis' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'kekuatan' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'gap' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'skor_kecocokan' => ['type' => ['integer', 'null']],
                    'alasan_skor' => ['type' => 'string'],
                    'saran_perbaikan_cv' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'prediksi_pertanyaan' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['ringkasan', 'level', 'skill_teknis', 'kekuatan', 'gap', 'saran_perbaikan_cv', 'prediksi_pertanyaan'],
            ];

            return response()->json($ai->askJson($system, $user, $schema));
        } catch (\Throwable $e) {
            logger()->warning('CV analysis failed.', ['elapsed' => round(microtime(true) - $started, 1), 'message' => $e->getMessage()]);
            $status = str_contains($e->getMessage(), 'terlalu lama') ? 504 : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
