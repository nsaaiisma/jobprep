<?php

// Salin file ini ke config/ai.php
return [
    // gemini  = Google Gemini (key gratis dari Google AI Studio)
    // openai  = API kompatibel OpenAI: Groq, OpenRouter, atau Ollama (lokal, 100% gratis)
    'driver' => env('AI_DRIVER', 'gemini'),

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-3.5-flash-lite'),
        'grounding' => env('GEMINI_GROUNDING', true), // pencarian Google untuk riset perusahaan
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'http://localhost:11434/v1'),
        'key' => env('OPENAI_API_KEY', ''),
        'model' => env('OPENAI_MODEL', 'llama3.1'),
    ],
];
