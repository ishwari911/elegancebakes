<?php
// Suppress warnings/notices that can corrupt JSON output on shared hosts
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Ensure session cookie covers the whole domain, not just /api/
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();
require_once '../config/database.php';


$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    exit(json_encode(["status" => "error", "message" => "Email & password required"]));
}

$stmt = $conn->prepare("SELECT id, first_name, password, is_verified FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password']) && $user['is_verified'] == 1) {
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['first_name'];
    echo json_encode(["status" => "success", "message" => "Login successful", "user_name" => $user['first_name']]);
} else if ($user && password_verify($password, $user['password']) && !$user['is_verified']) {
    echo json_encode(["status" => "error", "message" => "Please verify your email with OTP first"]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
}
?>
