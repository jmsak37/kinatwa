<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

requireLogin();

try {
    if (!isMainAdmin()) {
        echo json_encode([
            "ok" => false,
            "message" => "Only main admin can restore deleted account."
        ]);
        exit;
    }

    $username = trim($_POST['restore_username'] ?? '');
    $email = trim($_POST['restore_email'] ?? '');
    $code = trim($_POST['restore_unique_code'] ?? '');

    if ($username === '' || $email === '' || $code === '') {
        echo json_encode([
            "ok" => false,
            "message" => "Username, email, and unique code are required."
        ]);
        exit;
    }

    $active = $pdo->prepare("
        SELECT id
        FROM admins
        WHERE username = ? AND is_active = 1
        LIMIT 1
    ");
    $active->execute([$username]);
    if ($active->fetch()) {
        echo json_encode([
            "ok" => false,
            "message" => "This account is already active."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM deleted_admin_accounts
        WHERE username = ?
          AND email = ?
          AND delete_code = ?
          AND restore_until >= NOW()
        ORDER BY deleted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$username, $email, $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode([
            "ok" => false,
            "message" => "Deleted account not found for these details."
        ]);
        exit;
    }

    $newPasswordHash = password_hash($email, PASSWORD_DEFAULT);

    $check = $pdo->prepare("SELECT id FROM admins WHERE username = ? LIMIT 1");
    $check->execute([$username]);
    $exists = $check->fetch(PDO::FETCH_ASSOC);

    if ($exists) {
        $pdo->prepare("
            UPDATE admins
            SET is_active = 1,
                email = ?,
                password_hash = ?,
                role = ?,
                created_by = ?,
                deleted_at = NULL
            WHERE id = ?
        ")->execute([
            $email,
            $newPasswordHash,
            $row['role'],
            $row['created_by'],
            $exists['id']
        ]);
    } else {
        if (!empty($row['original_admin_id'])) {
            $pdo->prepare("
                INSERT INTO admins
                (id, username, email, password_hash, role, created_by, is_active, deleted_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NULL)
            ")->execute([
                $row['original_admin_id'],
                $row['username'],
                $email,
                $newPasswordHash,
                $row['role'],
                $row['created_by']
            ]);
        } else {
            $pdo->prepare("
                INSERT INTO admins
                (username, email, password_hash, role, created_by, is_active, deleted_at)
                VALUES (?, ?, ?, ?, ?, 1, NULL)
            ")->execute([
                $row['username'],
                $email,
                $newPasswordHash,
                $row['role'],
                $row['created_by']
            ]);
        }
    }

    $pdo->prepare("DELETE FROM deleted_admin_accounts WHERE id = ?")->execute([$row['id']]);

    addNotification(
        $pdo,
        'Account Restored',
        "Deleted account restored: {$username}. New password is the email address.",
        'all',
        '/kinatwa/login.html'
    );

    echo json_encode([
        "ok" => true,
        "message" => "Account restored successfully. Use the email as the password now."
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}