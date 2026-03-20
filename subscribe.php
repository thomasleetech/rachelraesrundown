<?php
/**
 * Rachel Rae's Rundown — subscribe.php
 * Premium subscription page
 */
require_once __DIR__ . '/config.php';
$pageTitle = 'Subscribe — Premium';
include __DIR__ . '/includes/header.php';
?>

<div class="subscribe-wrap">
  <div style="margin-bottom:8px;">
    <span class="label label-inv" style="font-size:10px;letter-spacing:0.2em;">✦ PREMIUM ✦</span>
  </div>
  <h1>Rachel Rae's <em>Rundown</em></h1>
  <p style="font-family:'EB Garamond',serif;font-size:18px;font-style:italic;color:var(--warm-gray);margin-bottom:4px;">
    "All the chaos that's fit to monetize — delivered without apology."
  </p>

  <div class="subscribe-price-big">$4.20<span>/mo</span></div>
  <p style="font-family:'DM Mono',monospace;font-size:11px;color:var(--warm-gray);letter-spacing:0.06em;">
    Cancel anytime. We won't take it personally. <em>(We will take it personally.)</em>
  </p>

  <ul class="subscribe-features">
    <li>Unlimited access to all articles, past and future</li>
    <li>Exclusive "Letters to Nobody" archive — unfiltered, unhinged</li>
    <li>Early access to stories before they hit the front page</li>
    <li>Ad-free reading experience (not that we have ads, but still)</li>
    <li>Members-only weekly newsletter from Rachel Rae herself</li>
    <li>Priority access to submit tips, letters, and grudges</li>
    <li>The smug satisfaction of supporting independent satire</li>
    <li>A warm feeling in your chest (or possibly heartburn)</li>
  </ul>

  <div style="margin-top:32px;">
    <a href="/contact" class="premium-cta-btn" style="display:inline-block;">Get Started</a>
  </div>

  <p style="font-family:'DM Mono',monospace;font-size:10px;color:var(--warm-gray);margin-top:24px;letter-spacing:0.04em;">
    Questions? <a href="/contact" style="color:var(--teal);">Contact us</a>.
    By subscribing you agree to our <a href="/terms" style="color:var(--teal);">User Agreement</a>.
  </p>
</div>

<div style="background:var(--charcoal);padding:40px 36px;">
  <div style="max-width:680px;margin:0 auto;text-align:center;">
    <p style="font-family:'Cormorant Garamond',serif;font-size:24px;font-style:italic;color:var(--mustard);line-height:1.5;">
      "I built a fake newspaper staffed entirely by robots and it is somehow more credible than three outlets I could name. That's not my problem. That's the news industry's problem."
    </p>
    <p style="font-family:'DM Mono',monospace;font-size:11px;color:var(--warm-gray);margin-top:14px;letter-spacing:0.1em;">
      — RACHEL RAE, FOUNDER & SUPREME OVERLORD
    </p>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
