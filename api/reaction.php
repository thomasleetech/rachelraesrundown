<?php
/**
 * Rachel Rae's Rundown — api/reaction.php
 * AJAX endpoint for like/dislike/share actions on articles.
 * POST { article_id: int, action: "like"|"dislike"|"share" }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$articleId = (int)($input['article_id'] ?? 0);
$action    = $input['action'] ?? '';

if (!$articleId || !in_array($action, ['like', 'dislike', 'share'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$pdo = getDB();

// Ensure reactions table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS article_reactions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id  INT UNSIGNED NOT NULL,
    action_type ENUM('like','dislike','share') NOT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_article_action (article_id, action_type),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = explode(',', $ip)[0];
$ip = trim($ip);

// For like/dislike: toggle behavior (one per IP per article per action type)
if ($action === 'like' || $action === 'dislike') {
    $existing = $pdo->prepare(
        'SELECT id FROM article_reactions WHERE article_id = ? AND action_type = ? AND ip_address = ? LIMIT 1'
    );
    $existing->execute([$articleId, $action, $ip]);
    if ($existing->fetch()) {
        // Remove the reaction (toggle off)
        $pdo->prepare('DELETE FROM article_reactions WHERE article_id = ? AND action_type = ? AND ip_address = ?')
            ->execute([$articleId, $action, $ip]);
    } else {
        // Remove opposite reaction if exists, then add
        $opposite = $action === 'like' ? 'dislike' : 'like';
        $pdo->prepare('DELETE FROM article_reactions WHERE article_id = ? AND action_type = ? AND ip_address = ?')
            ->execute([$articleId, $opposite, $ip]);
        $pdo->prepare('INSERT INTO article_reactions (article_id, action_type, ip_address) VALUES (?, ?, ?)')
            ->execute([$articleId, $action, $ip]);
    }
} else {
    // Share: always count
    $pdo->prepare('INSERT INTO article_reactions (article_id, action_type, ip_address) VALUES (?, ?, ?)')
        ->execute([$articleId, $action, $ip]);
}

// Return updated counts
$counts = [];
foreach (['like', 'dislike', 'share'] as $a) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM article_reactions WHERE article_id = ? AND action_type = ?');
    $stmt->execute([$articleId, $a]);
    $counts[$a] = (int)$stmt->fetchColumn();
}

// Check current user state
$userState = [];
foreach (['like', 'dislike'] as $a) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM article_reactions WHERE article_id = ? AND action_type = ? AND ip_address = ?');
    $stmt->execute([$articleId, $a, $ip]);
    $userState[$a] = (int)$stmt->fetchColumn() > 0;
}

echo json_encode(['counts' => $counts, 'user' => $userState]);
