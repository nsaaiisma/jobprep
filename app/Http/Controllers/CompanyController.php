<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        return view('pages.company');
    }

    public function research(Request $r, AiService $ai)
    {
        $r->validate(['company' => 'required|string|max:150', 'role' => 'nullable|string|max:150']);

        $system = "Kamu analis riset untuk pelamar kerja. Cari informasi terbaru di web tentang perusahaan yang diminta, lalu susun briefing singkat dalam Bahasa Indonesia (markdown) dengan bagian:
**Gambaran umum** (bidang usaha, ukuran, kantor pusat),
**Produk & layanan**, **Visi, misi, dan nilai perusahaan**, **Budaya kerja**,
**Berita terbaru** (maks 3, sebutkan sumber), **Tantangan/peluang bisnis**,
**Pertanyaan cerdas untuk ditanyakan ke pewawancara** (4 butir),
**Cara menghubungkan pengalamanmu dengan perusahaan ini**.
Jika informasi tidak ditemukan atau tidak pasti, katakan terus terang. Jangan mengarang data.";

        $user = "Perusahaan: {$r->company}\nPosisi yang dilamar: " . ($r->role ?: '-');

        try {
            return response()->json(['answer' => $ai->ask($system, $user, true, 3000)]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
