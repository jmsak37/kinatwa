<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

requireLogin();
cleanupExpiredCodes($pdo);

if (!isMainAdmin()) {
    echo json_encode(["ok" => false, "message" => "Only main admin can generate code."]);
    exit;
}

try {
    $intendedUsername = trim($_POST['intended_username'] ?? '');
    $intendedEmail = trim($_POST['intended_email'] ?? '');

    if ($intendedEmail === '') {
        echo json_encode(["ok" => false, "message" => "Email is required."]);
        exit;
    }

    $code = generateCode();

    $stmt = $pdo->prepare("
        INSERT INTO security_codes (
            code,
            generated_by,
            intended_username,
            intended_email,
            status,
            expires_at
        ) VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 1 MINUTE))
    ");
    $stmt->execute([
        $code,
        currentAdminId(),
        $intendedUsername !== '' ? $intendedUsername : null,
        $intendedEmail
    ]);

    $link = '#request-' . (int)$pdo->lastInsertId();

    $notify = $pdo->prepare("
        INSERT INTO notifications (title, message, link_url, target_role)
        VALUES (?, ?, ?, 'main_admin')
    ");
    $notify->execute([
        'Security Code Generated',
        'Security code generated for username: ' . ($intendedUsername !== '' ? $intendedUsername : 'Not limited') . ', email: ' . $intendedEmail . '.',
        $link
    ]);

    echo json_encode([
        "ok" => true,
        "message" => "Security code generated successfully for that email only.",
        "code" => $code
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}