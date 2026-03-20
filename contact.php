<?php
/**
 * Rachel Rae's Rundown — contact.php
 * Contact form that delivers messages to rachelraemoreno@gmail.com
 */
session_start();
require_once __DIR__ . '/config.php';

$pageTitle = 'Contact Us';
$msg = '';
$msgType = '';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid form submission. Please try again.';
        $msgType = 'error';
    }
    // Rate limiting: 1 submission per 60 seconds
    elseif (isset($_SESSION['last_contact']) && (time() - $_SESSION['last_contact']) < 60) {
        $msg = 'Please wait a moment before submitting again.';
        $msgType = 'error';
    }
    else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? 'General');
        $message = trim($_POST['message'] ?? '');

        // Validate
        if (!$name || !$email || !$message) {
            $msg = 'Please fill in all required fields.';
            $msgType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Please enter a valid email address.';
            $msgType = 'error';
        } else {
            // Build and send email
            $to = 'rachelraemoreno@gmail.com';
            $mailSubject = "[Rachel Rae's Rundown — $subject] Message from $name";
            $mailBody  = "Name: $name\n";
            $mailBody .= "Email: $email\n";
            $mailBody .= "Subject: $subject\n";
            $mailBody .= "---\n\n$message\n";

            $headers  = "From: noreply@" . SITE_DOMAIN . "\r\n";
            $headers .= "Reply-To: $email\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            if (mail($to, $mailSubject, $mailBody, $headers)) {
                $msg = 'Your message has been sent! We\'ll get back to you (or we won\'t — we\'re satire, not customer service).';
                $msgType = 'success';
                $_SESSION['last_contact'] = time();
                // Clear form values on success
                $name = $email = $message = '';
                $subject = 'General';
            } else {
                $msg = 'Something went wrong sending your message. Try again later.';
                $msgType = 'error';
            }
        }
    }

    // Regenerate CSRF token after submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$name    = $name ?? '';
$email   = $email ?? '';
$subject = $subject ?? 'General';
$message = $message ?? '';

include __DIR__ . '/includes/header.php';
?>

<div class="contact-wrap">
  <h1>Contact the Rundown</h1>
  <p class="contact-sub">Got a tip? A complaint? A declaration of love? Send it our way.</p>

  <?php if ($msg): ?>
  <div class="contact-msg <?= $msgType ?>"><?= e($msg) ?></div>
  <?php endif; ?>

  <form method="post" class="contact-form">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

    <div class="form-field">
      <label>Your Name *</label>
      <input type="text" name="name" value="<?= e($name) ?>" required>
    </div>

    <div class="form-field">
      <label>Your Email *</label>
      <input type="email" name="email" value="<?= e($email) ?>" required>
    </div>

    <div class="form-field">
      <label>Subject</label>
      <select name="subject">
        <option value="Tips" <?= $subject === 'Tips' ? 'selected' : '' ?>>Submit a Tip</option>
        <option value="Letters" <?= $subject === 'Letters' ? 'selected' : '' ?>>Letter to the Editor</option>
        <option value="General" <?= $subject === 'General' ? 'selected' : '' ?>>General Inquiry</option>
        <option value="Legal" <?= $subject === 'Legal' ? 'selected' : '' ?>>Legal (Good Luck)</option>
        <option value="Emotional Support" <?= $subject === 'Emotional Support' ? 'selected' : '' ?>>Emotional Support</option>
      </select>
    </div>

    <div class="form-field">
      <label>Message *</label>
      <textarea name="message" required><?= e($message) ?></textarea>
    </div>

    <button type="submit">Send Message</button>
  </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
