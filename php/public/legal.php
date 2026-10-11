<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/legal.php';
$page = $_GET['page'] ?? 'privacy';
if (!is_string($page) || !in_array($page, ['privacy', 'terms'], true)) {
    http_response_code(404);
    exit('Page not found.');
}
$title = $page === 'privacy' ? 'Privacy Policy' : 'Terms of Use';
$details = legal_details();
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
  <title><?= escape($title) ?> · Gather</title>
  <link rel="stylesheet" href="assets/site.css">
  <link rel="icon" href="assets/favicon.svg">
</head>
<body class="legal-page">
  <header class="legal-header"><a class="brand" href="../"><span class="brand-mark">G</span>Gather.</a><a href="index.php">Open your feed ↗</a></header>
  <main class="legal-document">
    <p class="eyebrow">GATHER · SITE INFORMATION</p>
    <h1><?= escape($title) ?></h1>
    <p class="legal-date">Last updated: September 11, 2026</p>
    <?php if (!$details['operator'] || !$details['email']): ?>
      <aside class="legal-placeholder" aria-label="Details to complete"><strong>Site owner: complete these details before launch.</strong><p>Operator: <?= escape($details['operator'] ?: '[ADD SITE OPERATOR NAME]') ?><br>Contact email: <?= escape($details['email'] ?: '[ADD PUBLIC CONTACT EMAIL]') ?></p></aside>
    <?php endif; ?>
    <p>This page applies to this Gather installation, operated by <?= escape($details['operator'] ?: '[ADD SITE OPERATOR NAME]') ?>.</p>
    <?php if ($page === 'privacy'): ?>
      <h2>Information this site stores</h2>
      <p>When you register, Gather stores your name, email address, a password hash, account creation time and information used to manage your signed-in session. It stores the creators you subscribe to and when you added them. Sign-in protection also records attempt counts and hashed identifiers derived from email addresses and IP addresses.</p>
      <p>Gather saves public creator and feed information, such as creator names, platform links, content titles, thumbnails, publication dates, media URLs and available live status. Subscription import files are parsed in your browser; extracted creator or feed URLs are sent to the site, rather than the original file.</p>
      <h2>How information is used and shared</h2>
      <p>Account and subscription information is used to sign you in, build your feed and suggest creators. Recommendations use follows added directly in Gather. Subscriptions imported through linked accounts are excluded from shared-subscriber recommendations. Other users are not given access to your personal subscription library through these features. The welcome page may show the total number of registered accounts, and discovery features may show aggregate subscription counts.</p>
      <p>The site operator and hosting providers may process stored information to run, maintain and protect the service. Hosting systems may record requests and errors and retain backups. Contact the operator for details about the providers, storage locations and any additional practices used for this installation.</p>
      <h2>Linked YouTube and Twitch accounts</h2>
      <p>When you choose to connect a provider, Gather requests read access to your YouTube subscriptions or Twitch followed channels. It stores the provider account identifier and display name, encrypted access and refresh tokens, imported creator links, and connection and sync times. These are used to populate and update your personal library and check whether the connection remains valid. Gather does not receive your Google or Twitch password or use these permissions to post, purchase, follow or unfollow on your behalf.</p>
      <p>Use Linked accounts to sync or choose <strong>Disconnect &amp; remove imports</strong>. Disconnecting deletes the saved connection and the subscriptions it imported into Gather, and attempts to revoke provider access. Creators already added manually remain. This does not delete or change data on YouTube or Twitch. You can also revoke access in <a href="https://myaccount.google.com/connections">Google account connections</a> or <a href="https://www.twitch.tv/settings/connections">Twitch connections</a>. Revoked connections are removed when detected. Connections and their imports are removed after 29 days without a completed sync; scheduled maintenance enforces this limit, and stale connections are also removed when you next open your library. Temporary sync data is kept in your server session while an import runs.</p>
      <p>Gather uses YouTube API Services. Use and transfer of information received from Google APIs must adhere to the <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>, including its Limited Use requirements. The bundled integration uses this information only to provide and maintain your connected library, and does not sell it, use it for advertising or include it in shared-subscriber recommendations. See the <a href="https://policies.google.com/privacy">Google Privacy Policy</a> and <a href="https://legal.twitch.com/en/legal/privacy-notice/">Twitch Privacy Notice</a> for provider practices.</p>
      <h2>Cookies and third-party content</h2>
      <p>Gather uses a session cookie to keep you signed in and protect account actions. Signing out clears that cookie. Blocking it prevents account features from working. The bundled application does not include advertising or analytics scripts.</p>
      <p>Remote thumbnails, embedded YouTube and Twitch players, podcast audio and links connect your browser to external services. Those services may receive your IP address, browser information and referring-page information, and may use their own cookies. Their own privacy policies apply to their processing. A privacy-enhanced video embed does not prevent all information from reaching its provider.</p>
      <h2>Retention and your choices</h2>
      <p>Account information and manually added subscriptions remain stored until removed by the operator or through available site controls. Linked-account data follows the deletion rules described above. Removing a subscription removes it from your library; public creator and cached feed information may remain. Hosting logs and backup retention depend on the operator's settings.</p>
      <p>You can remove subscriptions and sign out using the site. Contact the operator to request access to, correction of, export of or deletion of your account information, or to ask about retention and privacy rights that apply to you. This installation does not provide an automatic account-deletion or password-reset email service.</p>
      <h2>Changes to this policy</h2>
      <p>The operator should update this page when this installation's data practices change and provide any notice required by applicable law. The date above identifies this version.</p>
    <?php else: ?>
      <h2>Using Gather</h2>
      <p>Gather brings links, feeds and supported embedded media from creators you follow into one place. These terms describe the conditions for using this installation. If you do not agree to these terms, do not use the service.</p>
      <h2>Your account</h2>
      <p>Provide accurate account details, protect your password and use only accounts you are authorized to access. Contact the operator if you suspect unauthorized access. Registration may be closed or require an invitation.</p>
      <h2>Acceptable use</h2>
      <p>Use the service lawfully and respect other users and creators. Do not bypass access controls, attempt to access another user's private information, distribute malicious content, interfere with the service or place an unreasonable load on it. Only import subscriptions and feed information you are authorized to use.</p>
      <h2>Creators and external platforms</h2>
      <p>Videos, streams, podcasts, artwork and other third-party materials remain the property of their respective owners. Access through Gather does not grant a license to copy or redistribute them. Follow the terms of the platforms and creators whose services you use. Gather is not affiliated with or endorsed by those platforms unless the operator states otherwise.</p>
      <p>Use of YouTube through Gather is also subject to the <a href="https://www.youtube.com/t/terms">YouTube Terms of Service</a>.</p>
      <h2>Availability and accuracy</h2>
      <p>Feeds, recommendations and live indicators depend on external sources and refresh timing. They may be delayed, incomplete or unavailable. Content may change or be removed by its provider. The operator may maintain, modify or discontinue features and may restrict access when needed to address abuse or protect the service.</p>
      <p>The service is provided as available. To the extent permitted by applicable law, the operator does not promise uninterrupted service or error-free content. Nothing in these terms excludes rights or protections that cannot lawfully be excluded.</p>
      <h2>Privacy and changes</h2>
      <p>Read the <a href="legal.php?page=privacy">Privacy Policy</a> for information about accounts, subscriptions, cookies and external media. The operator may revise these terms and should provide any notice required by applicable law. The date above identifies this version.</p>
    <?php endif; ?>
    <h2>Contact</h2>
    <p>For questions<?= $page === 'terms' ? ', account issues or copyright concerns' : ' or privacy requests' ?>, contact <?= escape($details['operator'] ?: '[ADD SITE OPERATOR NAME]') ?> at
    <?php if ($details['email']): ?><a href="mailto:<?= escape($details['email']) ?>"><?= escape($details['email']) ?></a><?php else: ?><strong>[ADD PUBLIC CONTACT EMAIL]</strong><?php endif; ?>.</p>
  </main>
  <?php legal_footer('', 'legal-page-footer'); ?>
</body>
</html>
