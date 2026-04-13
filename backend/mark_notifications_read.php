<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

if (!refreshAdminSession($pdo)) {
    echo json_encode([
        "ok" => false,
        "message" => "Please login first."
    ]);
    exit;
}

try {
    $roleFilterMain = isMainAdmin() ? 1 : 0;
    $roleFilterNormal = isMainAdmin() ? 0 : 1;

    $sql = "
        UPDATE notifications
        SET is_read = 1
        WHERE target_role = 'all'
           OR (target_role = 'main_admin' AND {$roleFilterMain} = 1)
           OR (target_role = 'normal_admin' AND {$roleFilterNormal} = 1)
    ";

    $pdo->exec($sql);

    echo json_encode([
        "ok" => true,
        "message" => "Notifications marked as read."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}