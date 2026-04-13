<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/schema_sync.php';

header('Content-Type: application/json');

requireLogin();

try {
    syncKinatwaSchema($pdo);

    $fileName = trim($_POST['file_name'] ?? '');
    $playSeconds = max(1, (int)($_POST['play_seconds'] ?? 6));
    $playOrder = max(0, (int)($_POST['play_order'] ?? 0));
    $showBottomMessages = (int)($_POST['show_bottom_messages'] ?? 1) === 1 ? 1 : 0;

    if ($fileName === '') {
        echo json_encode(["ok" => false, "message" => "No file selected."]);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE media_files
        SET play_seconds = ?, play_order = ?, show_bottom_messages = ?
        WHERE file_name = ?
    ");
    $stmt->execute([$playSeconds, $playOrder, $showBottomMessages, $fileName]);

    $pdo->prepare("UPDATE display_state SET version = version + 1 WHERE id = 1")->execute();

    $n = $pdo->prepare("
        INSERT INTO notifications (title, message, target_role)
        VALUES ('File Settings Updated', ?, 'all')
    ");
    $n->execute(["Settings updated for file: {$fileName}."]);

    echo json_encode(["ok" => true, "message" => "File settings updated successfully."]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "message" => $e->getMessage()]);
}