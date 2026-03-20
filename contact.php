<?php
/**
 * Rachel Rae's Rundown — contact.php
 * Contact form that sends email via mail() to rachelraemoreno@gmail.com
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Contact Us';
$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'General Inquiry');
    $message = trim($_POST['message'] ?? '');

    if (!$name || !$email || !$message) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $to      = 'rachelraemoreno@gmail.com';
        $subj    = '[RRR Contact] ' . $subject . ' — from ' . $name;
        $body    = "Name: $name\nEmail: $email\nSubject: $subject\n\n$message";
        $headers = "From: noreply@" . SITE_DOMAIN . "\r\n"
                 . "Reply-To: $email\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";

        if (@mail($to, $subj, $body, $headers)) {
            $success = true;
        } else {
            $error = 'Message could not be sent. Please try again later.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="contact-form-wrap">
  <h1>Contact the Rundown</h1>
  <p class="subtitle">Letters, tips, complaints, confessions, and vaguely threatening praise — all welcome.</p>

  <?php if ($success): ?>
    <div class="contact-success">Your message has been sent. We'll read it between existential crises.</div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="background:rgba(168,48,24,0.1);border:1px solid var(--red-accent);color:var(--red-accent);padding:16px;font-family:'DM Mono',monospace;font-size:12px;margin-bottom:20px;">
      <?= e($error) ?>
    </div>
  <?php endif; ?>

  <?php if (!$success): ?>
  <form method="post" class="contact-form">
    <label for="name">Your Name</label>
    <input type="text" id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>

    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

    <label for="subject">Subject</label>
    <select id="subject" name="subject">
      <option value="General Inquiry">General Inquiry</option>
      <option value="Letters to Nobody">Letters to Nobody</option>
      <option value="Submit a Tip">Submit a Tip</option>
      <option value="Advertising">Advertising</option>
      <option value="Legal">Legal</option>
      <option value="Other">Other</option>
    </select>

    <label for="message">Your Message</label>
    <textarea id="message" name="message" required><?= e($_POST['message'] ?? '') ?></textarea>

    <button type="submit">Send Message</button>
  </form>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
