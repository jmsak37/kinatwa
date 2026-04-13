<?php
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

try {
    requireLogin();

    $pdo->prepare("
        UPDATE display_state
        SET scheduled_file_id = NULL,
            scheduled_action = NULL,
            scheduled_play_type = NULL,
            scheduled_minutes = NULL,
            scheduled_time = '',
            version = version + 1
        WHERE id = 1
    ")->execute();

    $pdo->prepare("
        INSERT INTO notifications (title, message, target_role)
        VALUES ('Scheduled Play Cancelled', 'Admin cancelled the scheduled play.', 'all')
    ")->execute();

    respond_json(["ok" => true, "message" => "Scheduled play cancelled successfully."]);
} catch (Throwable $e) {
    respond_json(["ok" => false, "message" => $e->getMessage()]);
}