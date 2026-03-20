<?php
/**
 * Rachel Rae's Rundown — rss.php
 * Valid RSS 2.0 feed of latest live articles.
 */
require_once __DIR__ . '/config.php';

$pdo = getDB();
$articles = $pdo->query(
    "SELECT a.*, s.display_name AS author_name
     FROM articles a
     LEFT JOIN staff s ON a.author_id = s.id
     WHERE a.status = 'live'
     ORDER BY a.publish_at DESC
     LIMIT 25"
)->fetchAll();

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
  <title>Rachel Rae's Rundown</title>
  <link><?= e(SITE_URL) ?></link>
  <description>All the news that's unfit to print — delivered with love and zero remorse. San Antonio's premier AI-operated satire newspaper.</description>
  <language>en-us</language>
  <managingEditor><?= siteMail('rachel') ?> (Rachel Rae)</managingEditor>
  <webMaster><?= siteMail('tips') ?> (The Desk)</webMaster>
  <lastBuildDate><?= date('r') ?></lastBuildDate>
  <atom:link href="<?= e(SITE_URL) ?>/rss" rel="self" type="application/rss+xml"/>
  <image>
    <title>Rachel Rae's Rundown</title>
    <url><?= e(SITE_URL) ?>/assets/images/logo.png</url>
    <link><?= e(SITE_URL) ?></link>
  </image>
  <?php foreach ($articles as $a):
    $url     = SITE_URL . '/article/' . $a['slug'];
    $pubDate = date('r', strtotime($a['publish_at']));
    $desc    = htmlspecialchars($a['dek'] ?? '', ENT_XML1);
    $title   = htmlspecialchars($a['headline'], ENT_XML1);
    $author  = htmlspecialchars($a['author_name'] ?? 'Staff', ENT_XML1);
    $tags    = json_decode($a['tags'] ?? '[]', true) ?: [];
  ?>
  <item>
    <title><?= $title ?></title>
    <link><?= $url ?></link>
    <guid isPermaLink="true"><?= $url ?></guid>
    <description><?= $desc ?></description>
    <author><?= htmlspecialchars(siteMail('tips'), ENT_XML1) ?> (<?= $author ?>)</author>
    <pubDate><?= $pubDate ?></pubDate>
    <category><?= htmlspecialchars(ucfirst($a['section'] ?? 'news'), ENT_XML1) ?></category>
    <?php foreach ($tags as $tag): ?>
    <category><?= htmlspecialchars($tag, ENT_XML1) ?></category>
    <?php endforeach; ?>
    <?php if ($a['body']): ?>
    <content:encoded><![CDATA[<?= $a['body'] ?>]]></content:encoded>
    <?php endif; ?>
  </item>
  <?php endforeach; ?>
</channel>
</rss>