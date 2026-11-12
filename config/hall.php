<?php

return [
    /*
    | Show one-click demo accounts on the sign in page. Useful for a project
    | presentation, keep it off on a real hall's server.
    */
    'demo_logins' => (bool) env('HALL_DEMO_LOGINS', false),

    // Password of every seeded demo account (database/seeders/DemoSeeder.php)
    'demo_password' => env('HALL_DEMO_PASSWORD', 'password'),

    /*
    | Shown in the footer of every page. The site uses the real university's
    | name and contact details, so visitors must not take it for an official site.
    | Set HALL_DISCLAIMER to an empty value once the university runs it.
    */
    'disclaimer' => env('HALL_DISCLAIMER', 'A student capstone project, not an official City University website.'),

    /*
    | Ask AI, the hall assistant (Google Gemini). Without a key the page says
    | it is switched off. Get a key at https://aistudio.google.com/apikey
    */
    'ai' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('HALL_AI_MODEL', 'gemini-3.5-flash-lite'),
        'embedding_model' => env('HALL_AI_EMBEDDING_MODEL', 'gemini-embedding-001'),
    ],

    /*
    | Push alerts on phones and browsers (Web Push). Every in-app notification
    | is also pushed to the devices that turned alerts on. Make the key pair
    | once with "php artisan push:keys"; without keys push is switched off.
    | Needs HTTPS (or localhost) in the browser.
    */
    'push' => [
        'public_key' => env('HALL_PUSH_PUBLIC_KEY'),
        'private_key' => env('HALL_PUSH_PRIVATE_KEY'),
        // Who the push services may contact about this server: an https or mailto address
        'subject' => env('HALL_PUSH_SUBJECT', env('APP_URL')),
    ],

    /*
    | Telegram bot for the hall office (provost and staff). Token from @BotFather.
    | Runs with "php artisan telegram:poll"; the meal lists need the scheduler.
    */
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'lunch_at' => env('HALL_LUNCH_LIST_AT', '11:00'),
        'dinner_at' => env('HALL_DINNER_LIST_AT', '18:00'),
    ],
];
