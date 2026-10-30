<?php

return [
    // ศูนย์ข้อมูล Zoho: com, eu, in, com.au, jp, ca
    'dc' => env('WORKDRIVE_DC', 'com'),

    // Self Client — scope (อ่านอย่างเดียว): WorkDrive.files.READ,ZohoFiles.files.READ
    'client_id' => env('WORKDRIVE_CLIENT_ID'),
    'client_secret' => env('WORKDRIVE_CLIENT_SECRET'),
    'refresh_token' => env('WORKDRIVE_REFRESH_TOKEN'),

    // โฟลเดอร์ที่มีโฟลเดอร์บริษัทอยู่ข้างใน (Zoho CRM - Workdrive / Accounts) — ใช้อ่านรายชื่อบริษัทเท่านั้น ไม่เขียนอะไรลงไป
    'company_root_id' => env('WORKDRIVE_COMPANY_ROOT_ID'),

    // หน่วงเวลาระหว่างคำขอ API (มิลลิวินาที) กันชนลิมิตของ Zoho
    'request_delay_ms' => (int) env('WORKDRIVE_REQUEST_DELAY_MS', 700),

    // Login ด้วย Zoho (Server-based Application ใน api-console.zoho.com)
    'login' => [
        'client_id' => env('ZOHO_LOGIN_CLIENT_ID'),
        'client_secret' => env('ZOHO_LOGIN_CLIENT_SECRET'),
        // โดเมนอีเมลที่อนุญาต คั่นด้วย , (เว้นว่าง = ทุกบัญชี Zoho)
        'allowed_domains' => env('ZOHO_LOGIN_ALLOWED_DOMAINS', 'thefirstgoodmangroup.com'),
    ],
];
