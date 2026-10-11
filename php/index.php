<?php
// Public entry point for installations such as https://example.com/gather/.
// This page works before database configuration and does not start a session.
declare(strict_types=1);
require_once __DIR__ . '/app/legal.php';
$registeredUsers = null;
$needsInstall = !is_file(__DIR__.'/config/local.php') && !is_file(__DIR__.'/storage/installed.lock') && is_file(__DIR__.'/install/index.php');
if (is_file(__DIR__ . '/config/local.php')) {
    require_once __DIR__ . '/app/bootstrap.php';
    try {
        $registeredUsers = (int) query('SELECT COUNT(*) FROM users')->fetchColumn();
    } catch (Throwable $e) {
        // The welcome page remains available during setup or a database outage.
        // Hide an unavailable count rather than displaying a misleading zero.
        error_log('Gather welcome: registered-user count is unavailable.');
    }
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="description" content="Your YouTube videos, Twitch streams and podcasts, together in one personal feed.">
  <title>Welcome to Gather · Content Curator</title>
  <link rel="stylesheet" href="public/assets/site.css">
  <link rel="stylesheet" href="public/assets/welcome.css">
  <link rel="icon" href="public/assets/favicon.svg">
</head>
<body class="splash-page">
  <header class="splash-header">
    <a class="brand" href="./" aria-label="Gather home"><span class="brand-mark">G</span>Gather<span class="brand-dot">.</span></a>
    <div class="splash-header-actions">
      <?php if ($registeredUsers !== null): ?>
        <span class="signup-count"><strong><?= number_format($registeredUsers) ?></strong> <?= $registeredUsers === 1 ? 'user signed up' : 'users signed up' ?></span>
      <?php endif; ?>
      <a class="splash-signin" href="public/auth.php">Sign in ↗</a>
    </div>
  </header>
  <main class="splash-main">
    <div class="eyebrow">YOUR CREATORS. YOUR FEED.</div>
    <h1>Your favorites.<br>All together.</h1>
    <p class="splash-intro">A personal home for the videos, live streams and podcasts you follow. Less switching between platforms. More time for what you enjoy.</p>
    <div class="splash-actions">
      <?php if ($needsInstall): ?><a class="primary" href="install/">Install Gather ↗</a><?php else: ?><a class="primary" href="public/index.php">Open your feed ↗</a><?php endif; ?>
      <a class="outline-button" href="public/preview.php">Take a look around</a>
    </div>
    <p class="splash-caption">New here? Choose Sign in to create your account when registration is open.</p>
    <section class="splash-features" aria-label="What you can do with Gather">
      <article><span class="splash-symbol" aria-hidden="true">▤</span><h2>One personal feed</h2><p>Bring your YouTube subscriptions, Twitch creators and podcast feeds into one place.</p></article>
      <article><span class="splash-symbol" aria-hidden="true">◉</span><h2>Catch them live</h2><p>See verified live streams from your creators when live connections are enabled.</p></article>
      <article><span class="splash-symbol" aria-hidden="true">◇</span><h2>Find your next favorite</h2><p>Discover creators followed by people who share your subscriptions.</p></article>
    </section>
  </main>
  <?php legal_footer('public/', 'splash-footer'); ?>
</body>
</html>
