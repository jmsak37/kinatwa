<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

requireLogin();

try {
    $cancelType = trim($_POST['cancel_type'] ?? '');

    if ($cancelType === 'forced') {
        $pdo->prepare("
            UPDATE display_state
            SET forced_file_id = NULL,
                priority_action = NULL,
                play_type = 'loop',
                minutes = 5,
                start_time = NULL,
                active_until = NULL,
                admin_message = NULL,
                admin_message_until = NULL,
                version = version + 1
            WHERE id = 1
        ")->execute();

        echo json_encode([
            "ok" => true,
            "message" => "Current priority play cancelled. Live normal play has returned."
        ]);
        exit;
    }

    if ($cancelType === 'scheduled') {
        $pdo->prepare("
            UPDATE display_state
            SET scheduled_file_id = NULL,
                scheduled_action = NULL,
                scheduled_play_type = NULL,
                scheduled_minutes = NULL,
                scheduled_time = NULL,
                version = version + 1
            WHERE id = 1
        ")->execute();

        echo json_encode([
            "ok" => true,
            "message" => "Scheduled play cancelled. Live normal play has returned."
        ]);
        exit;
    }

    echo json_encode([
        "ok" => false,
        "message" => "Invalid cancel type."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}