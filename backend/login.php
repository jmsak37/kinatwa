<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

$stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if ($admin && password_verify($password, $admin['password_hash'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];

    echo json_encode(["ok" => true, "message" => "Login successful."]);
} else {
    echo json_encode(["ok" => false, "message" => "Invalid username or password."]);
}