<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Http\Request;

/**
 * Daftarkan di routes/web.php (di dalam grup middleware 'auth'):
 *   Route::post('/live/olah', \App\Http\Controllers\RefineController::class)
 *       ->name('live.refine')->middleware('throttle:30,1');
 */
class RefineController extends Controller
{
    public function __invoke(Request $r, AiService $ai)
    {
        $r->validate([
            'question' => 'required|string|max:800',
            'answer' => 'required|string|max:3000',
            'instruction' => 'required|string|max:300',
            'role' => 'nullable|string|max:150',
            'company' => 'nullable|string|max:150',
            'language' => 'nullable|in:id,en',
            'mode' => 'nullable|in:ringkas,luas,kreatif',
        ]);

        $cv = auth()->user()->cv_text ?: (string) session('cv_text', '');

        try {
            return response()->json($ai->refineAnswer(
                $r->question,
                $r->answer,
                $r->instruction,
                (string) $r->role,
                (string) $r->company,
                $r->language ?? 'id',
                $cv,
                $r->mode ?? 'kreatif',
            ));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}