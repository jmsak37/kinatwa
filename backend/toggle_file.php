<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

requireLogin();

$fileName = trim($_POST['file_name'] ?? '');
$toggle = trim($_POST['toggle_enabled'] ?? '');

if ($fileName === '') {
    echo json_encode(["ok" => false, "message" => "No file selected."]);
    exit;
}

$enabled = $toggle === 'on' ? 1 : 0;

$stmt = $pdo->prepare("UPDATE media_files SET enabled = ? WHERE file_name = ?");
$stmt->execute([$enabled, $fileName]);

$pdo->prepare("UPDATE display_state SET version = version + 1 WHERE id = 1")->execute();

$stmt = $pdo->prepare("SELECT version FROM display_state WHERE id = 1");
$stmt->execute();
$ver = $stmt->fetch();

echo json_encode([
    "ok" => true,
    "message" => "Updated switch for: " . $fileName,
    "version" => (int)($ver['version'] ?? 1)
]);