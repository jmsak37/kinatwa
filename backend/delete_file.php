<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

requireLogin();

$fileName = trim($_POST['file_name'] ?? '');

if ($fileName === '') {
    echo json_encode(["ok" => false, "message" => "No file selected."]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, file_path FROM media_files WHERE file_name = ? LIMIT 1");
$stmt->execute([$fileName]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(["ok" => false, "message" => "File not found."]);
    exit;
}

if (!empty($row['file_path']) && file_exists($row['file_path'])) {
    @unlink($row['file_path']);
}

$pdo->prepare("DELETE FROM media_files WHERE id = ?")->execute([$row['id']]);
$pdo->prepare("
    UPDATE display_state
    SET forced_file_id = CASE WHEN forced_file_id = ? THEN NULL ELSE forced_file_id END,
        scheduled_file_id = CASE WHEN scheduled_file_id = ? THEN NULL ELSE scheduled_file_id END,
        version = version + 1
    WHERE id = 1
")->execute([$row['id'], $row['id']]);

$stmt = $pdo->prepare("SELECT version FROM display_state WHERE id = 1");
$stmt->execute();
$ver = $stmt->fetch();

echo json_encode([
    "ok" => true,
    "message" => "Deleted: " . $fileName,
    "version" => (int)($ver['version'] ?? 1)
]);