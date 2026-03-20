<?php
/**
 * Rachel Rae's Rundown — privacy.php
 */
require_once __DIR__ . '/config.php';
$pageTitle = 'Privacy Policy';
include __DIR__ . '/includes/header.php';
?>

<div class="legal-wrap">
  <h1>Privacy Policy</h1>
  <div class="updated">Last updated: <?= date('F j, Y') ?></div>

  <p>Rachel Rae's Rundown ("the Rundown," "we," "us") operates the website <?= e(SITE_DOMAIN) ?>. This page informs you of our policies regarding the collection, use, and disclosure of personal information when you use our site.</p>

  <h2>Information We Collect</h2>
  <p>When you visit our site, we may automatically collect certain information about your device and visit, including:</p>
  <ul>
    <li>Your IP address</li>
    <li>Browser type and version</li>
    <li>Pages you visit and time spent on each page</li>
    <li>Referring website or link that brought you to us</li>
    <li>Your approximate geographic location (derived from IP address)</li>
    <li>Device type and operating system</li>
  </ul>

  <h2>Information You Provide</h2>
  <p>When you use our contact form, you voluntarily provide your name, email address, and message content. We use this information solely to respond to your inquiry.</p>

  <h2>How We Use Your Information</h2>
  <p>We use the collected information to:</p>
  <ul>
    <li>Operate and maintain the website</li>
    <li>Understand how visitors use and interact with our content</li>
    <li>Improve our editorial content and user experience</li>
    <li>Respond to contact form submissions</li>
    <li>Detect and prevent abuse or unauthorized access</li>
  </ul>

  <h2>Data Storage</h2>
  <p>Visit data is stored in our secure database and is only accessible to authorized administrators. We do not sell, trade, or rent your personal information to third parties.</p>

  <h2>Cookies</h2>
  <p>We may use session cookies to maintain your browsing experience. These are temporary and are deleted when you close your browser. We do not use tracking cookies or third-party advertising cookies.</p>

  <h2>Third-Party Services</h2>
  <p>We use Google Fonts for typography, which may collect anonymous usage data per Google's privacy policy. We do not use any other third-party analytics, advertising, or tracking services.</p>

  <h2>Your Rights</h2>
  <p>You have the right to request access to, correction of, or deletion of any personal data we hold about you. To make such a request, please contact us using the information below.</p>

  <h2>Children's Privacy</h2>
  <p>Our site is not directed at children under 13. We do not knowingly collect personal information from children.</p>

  <h2>Changes to This Policy</h2>
  <p>We may update this policy from time to time. Changes will be posted on this page with an updated revision date.</p>

  <h2>Contact Us</h2>
  <p>If you have questions about this privacy policy, please <a href="/contact" style="color:var(--teal);">contact us</a>.</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
