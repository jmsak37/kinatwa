<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

requireLogin();

if (!isMainAdmin()) {
    echo json_encode(["ok" => false, "message" => "Only main admin can delete other admins."]);
    exit;
}

try {
    $adminId = (int)($_POST['admin_id'] ?? 0);

    if ($adminId <= 0) {
        echo json_encode(["ok" => false, "message" => "Admin not found."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo json_encode(["ok" => false, "message" => "Admin account not found."]);
        exit;
    }

    if ((int)$admin['role'] === 1) {
        echo json_encode(["ok" => false, "message" => "Use the main admin self-delete process for main admin account."]);
        exit;
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

    $pdo->prepare("UPDATE admins SET is_active = 0 WHERE id = ?")->execute([$adminId]);

    echo json_encode(["ok" => true, "message" => "Admin deleted temporarily."]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "message" => $e->getMessage()]);
}