<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

requireLogin();

try {
    $password = trim($_POST['delete_password'] ?? '');
    $email = trim($_POST['delete_email'] ?? '');
    $assignMainAdminId = (int)($_POST['assign_main_admin_id'] ?? 0);
    $code = trim($_POST['delete_unique_code'] ?? '');

    if ($password === '' || $email === '') {
        echo json_encode(["ok" => false, "message" => "Password and email are required."]);
        exit;
    }

    if (strtoupper($code) !== 'JMSAK') {
        echo json_encode(["ok" => false, "message" => "Unique code is incorrect."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([currentAdminId()]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo json_encode(["ok" => false, "message" => "Account not found."]);
        exit;
    }

    if (!password_verify($password, $admin['password_hash'])) {
        echo json_encode(["ok" => false, "message" => "Password is incorrect."]);
        exit;
    }

    if (strcasecmp(trim((string)$admin['email']), $email) !== 0) {
        echo json_encode(["ok" => false, "message" => "Email is incorrect."]);
        exit;
    }

    if ((int)$admin['role'] === 1) {
        $otherMain = $pdo->prepare("SELECT id FROM admins WHERE role = 1 AND is_active = 1 AND id <> ? LIMIT 1");
        $otherMain->execute([currentAdminId()]);
        $mainExists = $otherMain->fetch(PDO::FETCH_ASSOC);

        if (!$mainExists) {
            if ($assignMainAdminId <= 0) {
                echo json_encode(["ok" => false, "message" => "Please choose another active admin to become main admin first."]);
                exit;
            }

            $assignStmt = $pdo->prepare("SELECT id FROM admins WHERE id = ? AND is_active = 1 AND id <> ? LIMIT 1");
            $assignStmt->execute([$assignMainAdminId, currentAdminId()]);
            $assignee = $assignStmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignee) {
                echo json_encode(["ok" => false, "message" => "Selected replacement admin is invalid."]);
                exit;
            }

            $pdo->prepare("UPDATE admins SET role = 1 WHERE id = ?")->execute([$assignMainAdminId]);
        }
    }

    $ins = $pdo->prepare("
        INSERT INTO deleted_admin_accounts
        (admin_id, username, email, password_hash, role, created_by, restore_until, deleted_by, delete_code)
        VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 MONTH), ?, ?)
    ");
    $ins->execute([
        $admin['id'],
        $admin['username'],
        $admin['email'] ?? '',
        $admin['password_hash'],
        $admin['role'],
        $admin['created_by'],
        currentAdminId(),
        'JMSAK'
    ]);

    $pdo->prepare("UPDATE admins SET is_active = 0 WHERE id = ?")->execute([currentAdminId()]);

    session_destroy();

    echo json_encode([
        "ok" => true,
        "message" => "Account deleted temporarily. It can be restored within 3 months."
    ]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "message" => $e->getMessage()]);
}