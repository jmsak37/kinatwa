<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/schema_sync.php';

header('Content-Type: application/json');

requireLogin();
syncKinatwaSchema($pdo);

if (!isMainAdmin()) {
    echo json_encode(["ok" => false, "message" => "Only main admin can approve requests."]);
    exit;
}

try {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $decision = trim($_POST['decision'] ?? 'approve');

    $stmt = $pdo->prepare("SELECT * FROM admin_code_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $requestRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$requestRow) {
        echo json_encode(["ok" => false, "message" => "Request not found or already handled."]);
        exit;
    }

    if ($decision === 'ignore') {
        echo json_encode(["ok" => true, "message" => "Request left pending."]);
        exit;
    }

    if ($decision === 'decline') {
        $upd = $pdo->prepare("
            UPDATE admin_code_requests
            SET status='rejected', responded_at=NOW(), approved_by=?
            WHERE id=?
        ");
        $upd->execute([currentAdminId(), $requestId]);

        $n = $pdo->prepare("
            INSERT INTO notifications (title, message, link_url, target_role, is_read)
            VALUES ('Code Request Declined', ?, ?, 'main_admin', 0)
        ");
        $n->execute([
            "Code request declined for username: {$requestRow['requested_username']}, email: {$requestRow['requested_email']}.",
            "/kinatwa/admin.html?request_id=" . $requestId
        ]);

        echo json_encode(["ok" => true, "message" => "Request declined."]);
        exit;
    }

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $ins = $pdo->prepare("
        INSERT INTO security_codes (
            code, generated_by, intended_username, intended_email, request_id, status, expires_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 1 MINUTE))
    ");
    $ins->execute([
        $code,
        currentAdminId(),
        $requestRow['requested_username'],
        $requestRow['requested_email'],
        $requestId
    ]);

    $upd = $pdo->prepare("
        UPDATE admin_code_requests
        SET status='approved', responded_at=NOW(), approved_by=?, approved_code=?
        WHERE id=?
    ");
    $upd->execute([currentAdminId(), $code, $requestId]);

    $n = $pdo->prepare("
        INSERT INTO notifications (title, message, link_url, target_role, is_read)
        VALUES ('Code Approved', ?, ?, 'main_admin', 0)
    ");
    $n->execute([
        "Security code approved for username: {$requestRow['requested_username']}, email: {$requestRow['requested_email']}.",
        "/kinatwa/admin.html?request_id=" . $requestId
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "Code approved and generated.",
        "code" => $code
    ]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "message" => $e->getMessage()]);
}