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
        $r->validate([
            'cv' => 'required|file|mimes:pdf,docx,txt|max:5120',
            'role' => 'nullable|string|max:150',
            'job_desc' => 'nullable|string|max:5000',
        ]);

        $cv = $parser->extract($r->file('cv'));
        if (mb_strlen($cv) < 100) {
            return response()->json(['error' => 'Teks CV tidak terbaca. Pastikan bukan hasil scan gambar.'], 422);
        }
        session(['cv_text' => $cv, 'cv_role' => $r->role]);

        $system = <<<TXT
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

        $user = "Posisi target: " . ($r->role ?: '-') . "\nDeskripsi lowongan:\n" . ($r->job_desc ?: '-') . "\n\nCV:\n" . $cv;

        try {
            return response()->json($ai->askJson($system, $user));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
