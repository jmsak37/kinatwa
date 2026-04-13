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

    $pdo->exec("UPDATE admin_code_requests SET status='expired' WHERE status='pending' AND requested_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $pdo->exec("UPDATE security_codes SET status='expired' WHERE status='pending' AND expires_at <= NOW()");

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
        echo json_encode(["ok" => true, "message" => "Request already sent. Wait for main admin response."]);
        exit;
    }

    $ins = $pdo->prepare("
        INSERT INTO admin_code_requests (requested_username, requested_email, status)
        VALUES (?, ?, 'pending')
    ");
    $ins->execute([$username, $email]);
    $requestId = (int)$pdo->lastInsertId();

    $link = "/kinatwa/admin.html?request_id=" . $requestId;

    $n = $pdo->prepare("
        INSERT INTO notifications (title, message, link_url, target_role, is_read)
        VALUES ('New Code Request', ?, ?, 'main_admin', 0)
    ");
    $n->execute([
        "New admin code request from username: {$username}, email: {$email}.",
        $link
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "Request sent to main admin successfully.",
        "request_id" => $requestId
    ]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "message" => $e->getMessage()]);
}