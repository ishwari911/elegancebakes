<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/database.php';

$input       = json_decode(file_get_contents('php://input'), true);
$email       = trim($input['email']        ?? '');
$otp         = trim($input['otp']          ?? '');
$new_password = trim($input['new_password'] ?? '');

if (!$email || !$otp || !$new_password) {
    exit(json_encode(['status' => 'error', 'message' => 'All fields are required']));
}

if (strlen($new_password) < 8) {
    exit(json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']));
}

// Fetch user's OTP + expiry
$stmt = $conn->prepare("SELECT id, otp, otp_expiry FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    exit(json_encode(['status' => 'error', 'message' => 'Account not found']));
}

// Validate OTP
if ($user['otp'] !== $otp) {
    exit(json_encode(['status' => 'error', 'message' => 'Invalid OTP. Please check your email.']));
}

// Check expiry
if (strtotime($user['otp_expiry']) < time()) {
    exit(json_encode(['status' => 'error', 'message' => 'OTP has expired. Please request a new one.']));
}

// Update password + clear OTP
$hash = password_hash($new_password, PASSWORD_DEFAULT);
$upd  = $conn->prepare("UPDATE users SET password = ?, otp = NULL, otp_expiry = NULL WHERE id = ?");
$upd->bind_param("si", $hash, $user['id']);

if ($upd->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Password reset successfully!']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
}
?>
