<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

session_start();
require_once '../config/database.php';

$data  = json_decode(file_get_contents('php://input'), true);
$otp   = trim($data['otp']   ?? '');
$email = trim($data['email'] ?? '');

// Fall back to session if no email in body (backwards compat)
if (!$email && isset($_SESSION['verify_email'])) {
    $email = $_SESSION['verify_email'];
}

if (!preg_match('/^\d{6}$/', $otp)) {
    exit(json_encode(["status" => "error", "message" => "Invalid OTP"]));
}

if (!$email) {
    exit(json_encode(["status" => "error", "message" => "Session expired. Please register again."]));
}

// Look up user directly by email — no session needed
$stmt = $conn->prepare("SELECT id, first_name, otp, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    exit(json_encode(["status" => "error", "message" => "Account not found or already verified"]));
}

// Check expiry FIRST (before revealing whether OTP is correct)
if (strtotime($result['otp_expiry']) < time()) {
    exit(json_encode(["status" => "error", "message" => "OTP expired. Please request a new one."]));
}

if ($result['otp'] !== $otp) {
    exit(json_encode(["status" => "error", "message" => "Incorrect OTP. Please try again."]));
}

// Mark as verified
$upd = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expiry = NULL WHERE id = ?");
$upd->bind_param("i", $result['id']);
$upd->execute();

// Set session for this server too
$_SESSION['user_id']   = $result['id'];
$_SESSION['user_name'] = $result['first_name'];

echo json_encode([
    "status"    => "success",
    "message"   => "Account verified!",
    "user_name" => $result['first_name']
]);
?>
