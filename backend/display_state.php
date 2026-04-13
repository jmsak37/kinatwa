<?php
require_once __DIR__ . '/../kinatwadb.php';
header('Content-Type: application/json');

try {
    $pdo->exec("
        UPDATE display_state
        SET admin_message = NULL,
            admin_message_until = NULL,
            version = version + 1
        WHERE id = 1
          AND admin_message_until IS NOT NULL
          AND admin_message_until <= NOW()
    ");

    $pdo->exec("
        UPDATE display_state
        SET forced_file_id = NULL,
            priority_action = 'play_now',
            play_type = 'loop',
            active_until = NULL,
            version = version + 1
        WHERE id = 1
          AND active_until IS NOT NULL
          AND active_until <= NOW()
    ");

    $stmt = $pdo->prepare("
        SELECT version, admin_message, updated_at
        FROM display_state
        WHERE id = 1
        LIMIT 1
    ");
    $stmt->execute();
    $row = $stmt->fetch();

    echo json_encode([
        "version" => (int)($row['version'] ?? 1),
        "forced_file" => null,
        "admin_message" => $row['admin_message'] ?? null,
        "updated_at" => $row['updated_at'] ?? null
    ]);
} catch (Exception $e) {
    echo json_encode([
        "version" => 1,
        "forced_file" => null,
        "admin_message" => null,
        "updated_at" => null
    ]);
}