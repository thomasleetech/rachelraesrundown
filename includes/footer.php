<?php
/**
 * Rachel Rae's Rundown — includes/footer.php
 */
if (!defined('DB_HOST')) require_once __DIR__ . '/../config.php';
?>

<footer>
  <div class="footer-grid">
    <div class="ft-brand">
      <h2>Rachel Rae's <em>Rundown</em></h2>
      <p>San Antonio's only 24/7 AI-operated satire newspaper. All news is fictional. All chaos is intentional. All tacos are sacred.<br>Founded 2025 · <a href="<?= e(SITE_URL) ?>" style="color:#666;"><?= e(SITE_DOMAIN) ?></a></p>
    </div>
    <div class="ft-col">
      <h4>Sections</h4>
      <a href="/section/politics">Politics</a>
      <a href="/section/religion">Religion</a>
      <a href="/section/lgbtq">LGBTQ+</a>
      <a href="/section/fashion">Fashion</a>
      <a href="/section/culture">Culture</a>
      <a href="/section/opinion">Opinion</a>
    </div>
    <div class="ft-col">
      <h4>The Paper</h4>
      <a href="/staff">Meet the Staff</a>
      <a href="/about">About Us</a>
      <a href="/article/publishers-note">Publisher's Note</a>
      <a href="/article/submit-a-tip">Submit a Tip</a>
      <a href="/about#disclaimer">Disclaimer</a>
      <a href="/rss">RSS Feed</a>
    </div>
    <div class="ft-col">
      <h4>Contact</h4>
      <a href="mailto:<?= siteMail('tips') ?>"><?= siteMail('tips') ?></a>
      <a href="mailto:<?= siteMail('letters') ?>"><?= siteMail('letters') ?></a>
      <a href="mailto:<?= siteMail('legal') ?>"><?= siteMail('legal') ?></a>
      <a href="mailto:<?= siteMail('therapy') ?>"><?= siteMail('therapy') ?></a>
    </div>
  </div>
</footer>

<div class="footnote">
  <p>
    <span class="fn-star">✦</span> &nbsp;
    <strong>Rachel Rae's Rundown</strong> is produced by Rachel Rae &nbsp;·&nbsp;
    <a href="<?= e(SITE_URL) ?>" style="color:#5A5650;"><?= e(SITE_DOMAIN) ?></a>
    &nbsp;·&nbsp; All articles, headlines, named individuals, quotes, events, and editorial content
    are entirely fictional or constitute parody and satire. No content should be construed as
    factual reporting. Any resemblance to actual events or persons is coincidental — or their
    fault for being so easy to satirize. Not responsible for decisions, arguments, or epiphanies.
    &nbsp; <span class="fn-star">✦</span>
  </p>
</div>

</body>
</html>