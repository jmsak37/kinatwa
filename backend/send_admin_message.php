<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

requireLogin();

$messageText = trim($_POST['admin_message_text'] ?? '');
$minutes = max(1, (int)($_POST['admin_message_minutes'] ?? 1));

if ($messageText === '') {
    echo json_encode(["ok" => false, "message" => "Please type the message."]);
    exit;
}

$pdo->prepare("
    UPDATE display_state
    SET admin_message = ?,
        admin_message_until = DATE_ADD(NOW(), INTERVAL ? MINUTE),
        version = version + 1
    WHERE id = 1
")->execute([$messageText, $minutes]);

$stmt = $pdo->prepare("SELECT version FROM display_state WHERE id = 1");
$stmt->execute();
$ver = $stmt->fetch();

echo json_encode([
    "ok" => true,
    "message" => "Admin message sent to display.",
    "version" => (int)($ver['version'] ?? 1)
]);