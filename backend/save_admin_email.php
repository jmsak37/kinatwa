<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

requireLogin();

try {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        echo json_encode([
            "ok" => false,
            "message" => "Email is required."
        ]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            "ok" => false,
            "message" => "Enter a valid email address."
        ]);
        exit;
    }

    $check = $pdo->prepare("
        SELECT id
        FROM admins
        WHERE email = ?
          AND id <> ?
          AND is_active = 1
        LIMIT 1
    ");
    $check->execute([$email, currentAdminId()]);
    if ($check->fetch()) {
        echo json_encode([
            "ok" => false,
            "message" => "This email is already used by another active admin."
        ]);
        exit;
    }

    $upd = $pdo->prepare("UPDATE admins SET email = ? WHERE id = ?");
    $upd->execute([$email, currentAdminId()]);

    $_SESSION['admin_email'] = $email;

    addNotification(
        $pdo,
        'Admin Email Saved',
        "Admin email updated for user: " . ($_SESSION['admin_username'] ?? 'Unknown') . ".",
        'all',
        '/kinatwa/admin.html'
    );

    echo json_encode([
        "ok" => true,
        "message" => "Email saved successfully."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}