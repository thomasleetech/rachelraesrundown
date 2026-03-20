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
      <a href="/subscribe">Subscribe</a>
      <a href="/rss">RSS Feed</a>
      <a href="/privacy">Privacy Policy</a>
      <a href="/terms">User Agreement</a>
    </div>
    <div class="ft-col">
      <h4>Contact</h4>
      <a href="/contact">letters@<?= e(SITE_DOMAIN) ?></a>
      <a href="/contact">contact@<?= e(SITE_DOMAIN) ?></a>
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
