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
$email = trim($data['email'] ?? '');

// Fall back to session if no email in body
if (!$email && isset($_SESSION['verify_email'])) {
    $email = $_SESSION['verify_email'];
}

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit(json_encode(["status" => "error", "message" => "Email is required. Please go back and register again."]));
}

// Look up unverified user
$stmt = $conn->prepare("SELECT id, first_name, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    exit(json_encode(["status" => "error", "message" => "Account not found or already verified."]));
}

// Enforce 3-minute cooldown: if existing OTP was created less than 3 min ago, block resend
// otp_expiry = issued_time + 10 min, so issued_time = otp_expiry - 10 min
// cooldown passes when: now > issued_time + 3 min  →  now > otp_expiry - 7 min
if ($result['otp_expiry']) {
    $expiryTime = strtotime($result['otp_expiry']);
    $cooldownEnd = $expiryTime - (7 * 60); // 10 min expiry - 7 min = 3 min after issue
    if (time() < $cooldownEnd) {
        $waitSec = $cooldownEnd - time();
        exit(json_encode([
            "status"   => "error",
            "message"  => "Please wait {$waitSec} seconds before requesting a new OTP.",
            "wait_sec" => $waitSec
        ]));
    }
}

// Generate new OTP and expiry
$newOtp    = sprintf("%06d", mt_rand(100000, 999999));
$newExpiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$upd = $conn->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE id = ?");
$upd->bind_param("ssi", $newOtp, $newExpiry, $result['id']);

if (!$upd->execute()) {
    exit(json_encode(["status" => "error", "message" => "Could not generate new OTP. Try again."]));
}

// Send OTP email
sendOTP($email, $result['first_name'], $newOtp);

echo json_encode([
    "status"  => "success",
    "message" => "New OTP sent! Check your inbox (or spam folder)."
]);

function sendOTP($email, $name, $otp) {
    require_once '../PHPMailer/src/PHPMailer.php';
    require_once '../PHPMailer/src/SMTP.php';
    require_once '../PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Elegance Bakes — New OTP Verification';
        $mail->Body    = "
            <h2 style='color:#d4a574'>Hello $name! 🍰</h2>
            <p>You requested a new OTP. Your previous one has been invalidated.</p>
            <div style='background:#f8e8d9;padding:30px;border-radius:15px;text-align:center;border:2px solid #d4a574'>
                <h1 style='font-size:48px;color:#d4a574;letter-spacing:10px'>$otp</h1>
                <p>Valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>
            </div>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("Resend OTP email failed: {$mail->ErrorInfo}");
    }
}
?>
