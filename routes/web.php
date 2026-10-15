<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CvController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\InterviewController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.home')->name('home');

// Tamu (belum login)
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:6,1');

    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

// Wajib login
Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    Route::get('/cv', [CvController::class, 'index'])->name('cv');
    Route::post('/cv', [CvController::class, 'analyze'])->middleware('throttle:10,1');

    Route::get('/wawancara', [InterviewController::class, 'index'])->name('interview');
    Route::post('/wawancara', [InterviewController::class, 'answer'])->middleware('throttle:20,1');
    Route::post('/wawancara/audio', [InterviewController::class, 'assistWithAudio'])
        ->middleware('throttle:12,1')
        ->name('interview.audio');

    Route::get('/perusahaan', [CompanyController::class, 'index'])->name('company');
    Route::post('/perusahaan', [CompanyController::class, 'research'])->middleware('throttle:10,1');
});
