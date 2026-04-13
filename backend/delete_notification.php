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
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        echo json_encode([
            "ok" => false,
            "message" => "Notification not found."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$notificationId]);

    echo json_encode([
        "ok" => true,
        "message" => "Notification deleted successfully."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}