<?php

return [
    // ศูนย์ข้อมูล Zoho: com, eu, in, com.au, jp, ca
    'dc' => env('WORKDRIVE_DC', 'com'),

    'client_id' => env('WORKDRIVE_CLIENT_ID'),
    'client_secret' => env('WORKDRIVE_CLIENT_SECRET'),
    'refresh_token' => env('WORKDRIVE_REFRESH_TOKEN'),

    // ID ของโฟลเดอร์ "แชร์ภายนอก" ใน Team Folder (ปลายทางของทุกบริษัท)
    'share_root_id' => env('WORKDRIVE_SHARE_ROOT_ID'),

    // role_id ของ WorkDrive (34 = View)
    'email_role_id' => env('WORKDRIVE_EMAIL_ROLE_ID', '34'),
    'link_role_id' => env('WORKDRIVE_LINK_ROLE_ID', '34'),

    // Login ด้วย Zoho (Server-based Application ใน api-console.zoho.com)
    'login' => [
        'client_id' => env('ZOHO_LOGIN_CLIENT_ID'),
        'client_secret' => env('ZOHO_LOGIN_CLIENT_SECRET'),
        // โดเมนอีเมลที่อนุญาต คั่นด้วย , (เว้นว่าง = ทุกบัญชี Zoho)
        'allowed_domains' => env('ZOHO_LOGIN_ALLOWED_DOMAINS', 'thefirstgoodmangroup.com'),
    ],
];
