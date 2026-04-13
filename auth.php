<?php
session_start();

require_once __DIR__ . '/kinatwadb.php';
require_once __DIR__ . '/backend/schema_sync.php';

syncKinatwaSchema($pdo);

function cleanupExpiredCodes(PDO $pdo): void
{
    $pdo->exec("
        UPDATE security_codes
        SET status = 'expired'
        WHERE status = 'pending'
          AND expires_at <= NOW()
    ");

    $pdo->exec("
        UPDATE admin_code_requests r
        LEFT JOIN security_codes c
            ON c.request_id = r.id
           AND c.status = 'pending'
           AND c.expires_at > NOW()
        SET r.status = 'expired',
            r.responded_at = NOW()
        WHERE r.status IN ('pending', 'approved')
          AND c.id IS NULL
          AND (
                r.requested_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                OR (r.responded_at IS NOT NULL AND r.responded_at <= DATE_SUB(NOW(), INTERVAL 1 MINUTE))
          )
    ");
}

function adminCount(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE is_active = 1")->fetchColumn();
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id']);
}

function currentAdminId(): ?int
{
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

function currentAdminRole(): ?int
{
    return isset($_SESSION['admin_role']) ? (int)$_SESSION['admin_role'] : null;
}

function currentAdminEmail(): string
{
    return (string)($_SESSION['admin_email'] ?? '');
}

function isMainAdmin(): bool
{
    return currentAdminRole() === 1;
}

function refreshAdminSession(PDO $pdo): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT id, username, COALESCE(email, '') AS email, role, is_active
        FROM admins
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([currentAdminId()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)$row['is_active'] !== 1) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return false;
    }

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_username'] = $row['username'];
    $_SESSION['admin_email'] = $row['email'];
    $_SESSION['admin_role'] = (int)$row['role'];

    return true;
}

function requireLogin(): void
{
    global $pdo;

    if (!refreshAdminSession($pdo)) {
        header('Content-Type: application/json');
        echo json_encode([
            "ok" => false,
            "message" => "Please login first."
        ]);
        exit;
    }
}

function generateCode(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function flashMessage(string $message, string $type = 'success'): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = [
        "message" => $message,
        "type" => $type
    ];
}

function addNotification(
    PDO $pdo,
    string $title,
    string $message,
    string $targetRole = 'all',
    ?string $linkUrl = '/kinatwa/admin.html'
): void {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (title, message, link_url, target_role, is_read)
        VALUES (?, ?, ?, ?, 0)
    ");
    $stmt->execute([$title, $message, $linkUrl, $targetRole]);
}

function createAdmin(PDO $pdo, string $username, string $email, string $password, int $role = 2, ?int $createdBy = null): array
{
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return [false, "Username already exists."];
    }

    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return [false, "Email already exists."];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO admins (username, email, password_hash, role, created_by, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$username, $email, $hash, $role, $createdBy]);

    return [true, (int)$pdo->lastInsertId()];
}

function validateCodeForRegistration(PDO $pdo, string $username, string $email, string $code): bool
{
    cleanupExpiredCodes($pdo);

    if (adminCount($pdo) === 0) {
        return $code === '123456';
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM security_codes
        WHERE code = ?
          AND status = 'pending'
          AND expires_at > NOW()
          AND (intended_username IS NULL OR intended_username = ?)
          AND (intended_email IS NULL OR intended_email = ?)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$code, $username, $email]);

    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function consumeCode(PDO $pdo, string $username, string $email, string $code, int $usedByAdminId): void
{
    if ($code === '123456') {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM security_codes
        WHERE code = ?
          AND status = 'pending'
          AND expires_at > NOW()
          AND (intended_username IS NULL OR intended_username = ?)
          AND (intended_email IS NULL OR intended_email = ?)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$code, $username, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return;
    }

    $upd = $pdo->prepare("
        UPDATE security_codes
        SET status = 'used',
            used_by = ?,
            used_at = NOW()
        WHERE id = ?
    ");
    $upd->execute([$usedByAdminId, $row['id']]);

    if (!empty($row['request_id'])) {
        $upd2 = $pdo->prepare("
            UPDATE admin_code_requests
            SET status = 'accepted',
                responded_at = NOW()
            WHERE id = ?
        ");
        $upd2->execute([$row['request_id']]);
    }
}

function softDeleteAdmin(PDO $pdo, int $adminId, ?int $deletedBy = null, string $deleteCode = 'JMSAK'): array
{
    $stmt = $pdo->prepare("
        SELECT id, username, COALESCE(email, '') AS email, password_hash, role, created_by
        FROM admins
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        return [false, "Admin account not found or already inactive."];
    }

    $checkDeleted = $pdo->prepare("
        SELECT id
        FROM deleted_admin_accounts
        WHERE original_admin_id = ?
        LIMIT 1
    ");
    $checkDeleted->execute([$adminId]);

    if ($checkDeleted->fetch()) {
        $pdo->prepare("UPDATE admins SET is_active = 0, deleted_at = NOW() WHERE id = ?")->execute([$adminId]);
        return [true, "Admin account moved to deleted records."];
    }

    $ins = $pdo->prepare("
        INSERT INTO deleted_admin_accounts
        (
            original_admin_id,
            username,
            email,
            password_hash,
            role,
            created_by,
            deleted_at,
            restore_until,
            deleted_by,
            delete_code
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 3 MONTH), ?, ?
        )
    ");
    $ins->execute([
        $admin['id'],
        $admin['username'],
        $admin['email'],
        $admin['password_hash'],
        $admin['role'],
        $admin['created_by'],
        $deletedBy,
        $deleteCode
    ]);

    $pdo->prepare("
        UPDATE admins
        SET is_active = 0,
            deleted_at = NOW()
        WHERE id = ?
    ")->execute([$adminId]);

    return [true, "Admin account deleted temporarily. It can be restored within 3 months."];
}