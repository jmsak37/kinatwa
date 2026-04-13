<?php
require_once __DIR__ . '/../kinatwadb.php';
header('Content-Type: application/json');

$count = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE is_active = 1")->fetchColumn();
echo json_encode(["has_user" => $count > 0]);