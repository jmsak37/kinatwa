<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

requireLogin();

try {
    $old = trim($_POST['old_password'] ?? '');
    $new = trim($_POST['new_password'] ?? '');

    if ($old === '' || $new === '') {
        echo json_encode([
            "ok" => false,
            "message" => "All password fields are required."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, username, password_hash
        FROM admins
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([currentAdminId()]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($old, $admin['password_hash'])) {
        echo json_encode([
            "ok" => false,
            "message" => "Current password is incorrect."
        ]);
        exit;
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$newHash, currentAdminId()]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_account_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NULL,
            username VARCHAR(100) NULL,
            action_type VARCHAR(100) NOT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->prepare("
        INSERT INTO admin_account_logs (admin_id, username, action_type, details)
        VALUES (?, ?, 'change_password', 'Password changed by admin')
    ")->execute([currentAdminId(), $_SESSION['admin_username'] ?? null]);

    addNotification(
        $pdo,
        'Password Changed',
        'An admin password was changed successfully.',
        'all',
        '/kinatwa/admin.html'
    );

    echo json_encode([
        "ok" => true,
        "message" => "Password changed successfully."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}