<?php
/**
 * Rachel Rae's Rundown — terms.php
 */
require_once __DIR__ . '/config.php';
$pageTitle = 'User Agreement';
include __DIR__ . '/includes/header.php';
?>

<div class="legal-wrap">
  <h1>User Agreement</h1>
  <div class="updated">Last updated: <?= date('F j, Y') ?></div>

  <p>By accessing and using Rachel Rae's Rundown ("the Rundown," "the site"), you agree to be bound by the following terms and conditions.</p>

  <h2>1. Nature of Content</h2>
  <p>The Rundown is a satire and parody publication. All articles, headlines, named individuals, quotes, events, statistics, and editorial content published on this site are entirely fictional or constitute satire and parody. No content should be construed as factual reporting. Any resemblance to actual events, locales, or persons, living or dead, is coincidental.</p>

  <h2>2. Intellectual Property</h2>
  <p>All content on this site — including text, graphics, logos, images, and software — is the property of Rachel Rae's Rundown and is protected by copyright and intellectual property laws. You may not reproduce, distribute, modify, or create derivative works from our content without prior written consent.</p>

  <h2>3. User Conduct</h2>
  <p>When using our site, you agree not to:</p>
  <ul>
    <li>Use the site for any unlawful purpose</li>
    <li>Attempt to gain unauthorized access to our systems or data</li>
    <li>Interfere with or disrupt the site's functionality</li>
    <li>Scrape, harvest, or collect data from the site without permission</li>
    <li>Misrepresent satirical content as factual reporting</li>
  </ul>

  <h2>4. Disclaimer of Warranties</h2>
  <p>The site is provided "as is" and "as available" without warranties of any kind, either express or implied. We do not warrant that the site will be uninterrupted, error-free, or free of harmful components.</p>

  <h2>5. Limitation of Liability</h2>
  <p>In no event shall Rachel Rae's Rundown, its owner, contributors, or AI agents be liable for any indirect, incidental, special, consequential, or punitive damages arising from your use of the site, including but not limited to: hurt feelings, existential crises prompted by satirical accuracy, or the sudden realization that reality is stranger than fiction.</p>

  <h2>6. Subscription Services</h2>
  <p>Premium subscriptions, if offered, are billed at the rate displayed at the time of purchase. You may cancel at any time. Refunds are handled on a case-by-case basis. We reserve the right to modify subscription pricing with reasonable notice.</p>

  <h2>7. Contact Form</h2>
  <p>Information submitted through our contact form is used solely to respond to your inquiry and is not shared with third parties. By submitting a message, you consent to receiving a response via the email address you provide.</p>

  <h2>8. Modifications</h2>
  <p>We reserve the right to modify these terms at any time. Continued use of the site after changes constitutes acceptance of the updated terms.</p>

  <h2>9. Governing Law</h2>
  <p>These terms are governed by and construed in accordance with the laws of the State of Texas, without regard to its conflict of law provisions.</p>

  <h2>10. Contact</h2>
  <p>Questions about these terms? <a href="/contact" style="color:var(--teal);">Contact us</a>.</p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
