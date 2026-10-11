<?php
// Copy this file to local.php and fill in values. Keep config outside the web root.
return [
    'app_url' => 'https://example.com/gather',
    'db' => ['host' => 'localhost', 'port' => 3306, 'name' => 'gather', 'user' => 'gather', 'password' => '', 'ssl_ca' => null],
    // Fill these in before launch. Blank values display reminders on legal pages.
    'legal_operator' => '',
    'legal_contact_email' => '',
    'secure_cookies' => true,
    'registration_enabled' => true,
    // Optional shared invitation code; leave empty for open registration.
    'registration_code' => '',
    'youtube_api_key' => '',
    // Linked accounts: see CONNECTIONS.md. Keep credentials and encryption key private.
    'youtube_client_id' => '',
    'youtube_client_secret' => '',
    'oauth_callback_url' => '', // e.g. https://example.com/gather/public/oauth.php
    'oauth_encryption_key' => '', // base64-encoded 32 random bytes; keep across upgrades
    'twitch_client_id' => '',
    'twitch_client_secret' => '',
];

