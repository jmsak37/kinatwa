<?php
require_once __DIR__ . '/../kinatwadb.php';
require_once __DIR__ . '/schema_sync.php';

header('Content-Type: application/json');

try {
    syncKinatwaSchema($pdo);

    $username = trim($_POST['requested_username'] ?? '');
    $email = trim($_POST['requested_email'] ?? '');

    if ($username === '' || $email === '') {
        echo json_encode(["ok" => false, "message" => "Username and email are required."]);
        exit;
    }

    $check = $pdo->prepare("
        SELECT id
        FROM admin_code_requests
        WHERE requested_username = ?
          AND requested_email = ?
          AND status = 'pending'
        LIMIT 1
    ");
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        echo json_encode(["ok" => true, "message" => "Request already sent. Wait for admin approval."]);
        exit;
    }

    $ins = $pdo->prepare("
        INSERT INTO admin_code_requests (requested_username, requested_email, status)
        VALUES (?, ?, 'pending')
    ");
    $ins->execute([$username, $email]);

    $n = $pdo->prepare("
        INSERT INTO notifications (title, message, target_role)
        VALUES ('New Code Request', ?, 'main_admin')
    ");
    $n->execute(["New code request from username: {$username}, email: {$email}."]);

    echo json_encode(["ok" => true, "message" => "Request sent to main admin successfully."]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "message" => $e->getMessage()]);
}