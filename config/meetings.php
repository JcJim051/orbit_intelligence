<?php

return [
    'max_size_kb' => (int) env('MEETING_AUDIO_MAX_SIZE_KB', 512000),
    'max_duration_seconds' => (int) env('MEETING_AUDIO_MAX_DURATION_SECONDS', 7200),
    'chunk_seconds' => (int) env('MEETING_AUDIO_CHUNK_SECONDS', 900),
    'chunk_max_bytes' => 24 * 1024 * 1024,
    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
    'ai_driver' => env('MEETING_AI_DRIVER', 'fake'),
    'retention_days' => env('AUDIO_RETENTION_DAYS'),
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-transcribe-diarize'),
        'analysis_model' => env('OPENAI_ANALYSIS_MODEL', 'gpt-5-mini'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
        'root_folder' => env('GOOGLE_DRIVE_ROOT_FOLDER', 'Actas de reuniones'),
    ],
];
