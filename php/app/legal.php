<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function legal_details(): array {
    try { $settings = config(); } catch (Throwable $e) { $settings = []; }
    $operator = trim((string)($settings['legal_operator'] ?? ''));
    $email = trim((string)($settings['legal_contact_email'] ?? ''));
    return [
        'operator' => $operator,
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
    ];
}

function legal_footer(string $prefix = '', string $class = 'page-footer'): void {
    $details = legal_details();
    echo '<footer class="' . escape($class) . ' legal-footer"><span>© ' . date('Y') . ' ' . escape($details['operator'] ?: 'Gather') . '</span>';
    echo '<nav aria-label="Legal"><a href="' . escape($prefix . 'legal.php?page=privacy') . '">Privacy Policy</a><a href="' . escape($prefix . 'legal.php?page=terms') . '">Terms of Use</a></nav></footer>';
}
