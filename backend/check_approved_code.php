<?php
require_once __DIR__ . '/../kinatwadb.php';
require_once __DIR__ . '/schema_sync.php';

header('Content-Type: application/json');

try {
    syncKinatwaSchema($pdo);

    $username = trim($_GET['username'] ?? '');
    $email = trim($_GET['email'] ?? '');

    if ($username === '' || $email === '') {
        echo json_encode([
            "ok" => false,
            "message" => "Username and email are required."
        ]);
        exit;
    }

    $pdo->exec("
        UPDATE security_codes
        SET status = 'expired'
        WHERE status = 'pending' AND expires_at <= NOW()
    ");

    $pdo->exec("
        UPDATE admin_code_requests
        SET status = 'expired', responded_at = NOW()
        WHERE status IN ('pending', 'approved')
          AND requested_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");

    $requestStmt = $pdo->prepare("
        SELECT id, status, approved_code, responded_at
        FROM admin_code_requests
        WHERE requested_username = ?
          AND requested_email = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $requestStmt->execute([$username, $email]);
    $requestRow = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$requestRow) {
        echo json_encode([
            "ok" => true,
            "status" => "none",
            "message" => "No request found."
        ]);
        exit;
    }

    if ($requestRow['status'] === 'rejected') {
        echo json_encode([
            "ok" => true,
            "status" => "rejected",
            "message" => "The admin declined your request."
        ]);
        exit;
    }

    if ($requestRow['status'] === 'expired') {
        echo json_encode([
            "ok" => true,
            "status" => "expired",
            "message" => "The request expired."
        ]);
        exit;
    }

    if (in_array($requestRow['status'], ['accepted'], true)) {
        echo json_encode([
            "ok" => true,
            "status" => "accepted",
            "message" => "The request has already been used."
        ]);
        exit;
    }

    $codeStmt = $pdo->prepare("
        SELECT code, expires_at, status
        FROM security_codes
        WHERE status = 'pending'
          AND expires_at > NOW()
          AND (intended_username IS NULL OR intended_username = ?)
          AND (intended_email IS NULL OR intended_email = ?)
          AND request_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $codeStmt->execute([$username, $email, $requestRow['id']]);
    $codeRow = $codeStmt->fetch(PDO::FETCH_ASSOC);

    if ($codeRow) {
        echo json_encode([
            "ok" => true,
            "status" => "approved",
            "code" => $codeRow['code'],
            "expires_at" => $codeRow['expires_at'],
            "message" => "Code approved."
        ]);
        exit;
    }

    if ($requestRow['status'] === 'approved' && !$codeRow) {
        echo json_encode([
            "ok" => true,
            "status" => "expired",
            "message" => "Approved code expired."
        ]);
        exit;
    }

    echo json_encode([
        "ok" => true,
        "status" => "pending",
        "message" => "Still waiting for admin approval."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}