<?php
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

try {
    cleanupExpiredCodes($pdo);

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $uniqueCode = trim($_POST['unique_code'] ?? '');

    if ($username === '' || $email === '' || $password === '' || $uniqueCode === '') {
        echo json_encode([
            "ok" => false,
            "message" => "All fields are required."
        ]);
        exit;
    }

    $checkUser = $pdo->prepare("
        SELECT id
        FROM admins
        WHERE (username = ? OR email = ?)
          AND is_active = 1
        LIMIT 1
    ");
    $checkUser->execute([$username, $email]);
    if ($checkUser->fetch()) {
        echo json_encode([
            "ok" => false,
            "message" => "Username or email already exists."
        ]);
        exit;
    }

    $countAdmins = adminCount($pdo);

    if ($countAdmins === 0) {
        if ($uniqueCode !== '123456') {
            echo json_encode([
                "ok" => false,
                "message" => "First admin must use code 123456."
            ]);
            exit;
        }

        [$ok, $result] = createAdmin($pdo, $username, $email, $password, 1, null);
        if (!$ok) {
            echo json_encode([
                "ok" => false,
                "message" => $result
            ]);
            exit;
        }

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int)$result;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_role'] = 1;

        addNotification(
            $pdo,
            'Main Admin Created',
            "Main admin account created. Username: {$username}, Email: {$email}.",
            'all',
            '/kinatwa/admin.html'
        );

        echo json_encode([
            "ok" => true,
            "message" => "Main admin account created successfully.",
            "auto_login" => true,
            "redirect" => "/kinatwa/admin.html"
        ]);
        exit;
    }

    if (!validateCodeForRegistration($pdo, $username, $email, $uniqueCode)) {
        echo json_encode([
            "ok" => false,
            "message" => "Invalid or expired unique code."
        ]);
        exit;
    }

    $mainAdmin = $pdo->query("
        SELECT id
        FROM admins
        WHERE role = 1 AND is_active = 1
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    [$ok, $result] = createAdmin($pdo, $username, $email, $password, 2, $mainAdmin['id'] ?? null);
    if (!$ok) {
        echo json_encode([
            "ok" => false,
            "message" => $result
        ]);
        exit;
    }

    $newAdminId = (int)$result;
    consumeCode($pdo, $username, $email, $uniqueCode, $newAdminId);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $newAdminId;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_role'] = 2;

    addNotification(
        $pdo,
        'New Admin Registered',
        "New admin registered. Username: {$username}, Email: {$email}.",
        'all',
        '/kinatwa/admin.html'
    );

    echo json_encode([
        "ok" => true,
        "message" => "Admin account created successfully.",
        "auto_login" => true,
        "redirect" => "/kinatwa/admin.html"
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "ok" => false,
        "message" => $e->getMessage()
    ]);
}