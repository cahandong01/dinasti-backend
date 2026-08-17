<?php

/**
 * Konfigurasi Rate Limiting — Peta Gurita Dinasti
 *
 * SATU sumber kebenaran buat semua angka batas request per menit.
 * Kalau mau ubah angka, cukup edit di sini — JANGAN hardcode angka
 * di provider/route manapun. Dipakai oleh App\Providers\AppServiceProvider.
 *
 * Riset dasar: OWASP API Security Top 10 2023 (API4:2023 — Unrestricted
 * Resource Consumption), Laravel RateLimiter best practice.
 */

return [

    // Login & reset password — paling ketat, cegah brute-force
    'auth' => [
        'per_minute' => 5,
    ],

    // Dispute Submission — publik tanpa login, rawan spam
    'dispute' => [
        'per_minute' => 5,
    ],

    // Graph traversal (Explore Network, Find Connection) — query paling berat
    'graph' => [
        'guest_per_minute' => 10,
        'authenticated_per_minute' => 30,
    ],

    // Entity search (fuzzy pg_trgm) — cegah scraping/enumeration data
    'search' => [
        'per_minute' => 30,
    ],

    // Endpoint umum lain (CRUD, dst)
    'api' => [
        'guest_per_minute' => 20,
        'authenticated_per_minute' => 60,
    ],

];