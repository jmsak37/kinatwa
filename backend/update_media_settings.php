<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

try {
    requireLogin();

    $fileName = trim($_POST['file_name'] ?? '');
    $displaySeconds = max(1, (int)($_POST['display_seconds'] ?? 6));
    $isLoop = (int)($_POST['is_loop'] ?? 1) === 1 ? 1 : 0;
    $showBottomMessage = (int)($_POST['show_bottom_message'] ?? 1) === 1 ? 1 : 0;
    $playOrder = max(0, (int)($_POST['play_order'] ?? 0));

    if ($fileName === '') {
        respond_json(["ok" => false, "message" => "File name is required."]);
    }

    $stmt = $pdo->prepare("
        UPDATE media_files
        SET display_seconds = ?, is_loop = ?, show_bottom_message = ?, play_order = ?
        WHERE file_name = ?
    ");
    $stmt->execute([$displaySeconds, $isLoop, $showBottomMessage, $playOrder, $fileName]);

    $pdo->prepare("UPDATE display_state SET version = version + 1 WHERE id = 1")->execute();

    respond_json(["ok" => true, "message" => "Media settings updated successfully."]);
} catch (Throwable $e) {
    respond_json(["ok" => false, "message" => $e->getMessage()]);
}