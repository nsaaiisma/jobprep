<?php

// Salin file ini ke config/ai.php
return [
    // gemini  = Google Gemini (key gratis dari Google AI Studio)
    // openai  = API kompatibel OpenAI: Groq, OpenRouter, atau Ollama (lokal, 100% gratis)
    'driver' => env('AI_DRIVER', 'gemini'),

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        // Model tercepat & kuota paling longgar dulu, fallback yang lebih pintar.
        // Daftar valid: gemini-3.5-flash-lite, gemini-3.8-flash, gemini-2.5-flash, dst.
        // Hindari alias gemini-flash-latest (sering 429/503 di Interactions API).
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-3.8-flash'),
        'grounding' => env('GEMINI_GROUNDING', true), // pencarian Google untuk riset perusahaan
        // Timeout per request (detik). Harus < max_execution_time PHP (30s).
        'timeout' => (int) env('GEMINI_TIMEOUT', 25),
        'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 5),
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'http://localhost:11434/v1'),
        'key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'llama3.1'),
    ],
];
