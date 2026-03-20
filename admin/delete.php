<?php
/**
 * Rachel Rae's Rundown — admin/delete.php
 * Deletes a single article by ID with CSRF token validation.
 * Usage: /admin/delete.php?id=INT&tok=MD5(ADMIN_PASSWORD.id)
 */
session_start();
require_once __DIR__ . '/../config.php';

if (!($_SESSION['rrr_admin'] ?? false)) {
    header('Location: /admin/');
    exit;
}

$id  = (int)($_GET['id'] ?? 0);
$tok = $_GET['tok'] ?? '';

// Validate token and ID
if (!$id || $tok !== md5(ADMIN_PASSWORD . $id)) {
    header('Location: /admin/?msg=invalid_token');
    exit;
}

$pdo = getDB();

// Confirm article exists
$stmt = $pdo->prepare('SELECT id, headline FROM articles WHERE id = ?');
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: /admin/?msg=not_found');
    exit;
}

// Delete it
$pdo->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);

header('Location: /admin/?msg=deleted');
exit;
