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

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    exit(json_encode(["status" => "error", "message" => "Invalid input"]));
}

$name    = trim($input['name']     ?? '');
$email   = trim($input['email']    ?? '');
$phone   = trim($input['phone']    ?? '');
$address = trim($input['address']  ?? '');
$pincode = trim($input['pincode']  ?? '');
$password = trim($input['password'] ?? '');

// VALIDATION
if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($pincode) || empty($password)) {
    exit(json_encode(["status" => "error", "message" => "All fields required"]));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit(json_encode(["status" => "error", "message" => "Invalid email"]));
}
if (!preg_match('/^[0-9]{10}$/', $phone)) {
    exit(json_encode(["status" => "error", "message" => "Phone: 10 digits"]));
}
$pinNum = intval($pincode);
if ($pinNum < 421201 || $pinNum > 421405) {
    exit(json_encode(["status" => "error", "message" => "Delivery only in pincodes 421201–421405"]));
}
if (strlen($password) < 8) {
    exit(json_encode(["status" => "error", "message" => "Password must be 8+ characters"]));
}

// CHECK DUPLICATE EMAIL
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    exit(json_encode(["status" => "error", "message" => "Email already registered"]));
}

// CREATE USER + OTP
$otp    = sprintf("%06d", mt_rand(100000, 999999));
$hash   = password_hash($password, PASSWORD_DEFAULT);
$expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$stmt = $conn->prepare("INSERT INTO users (first_name, email, phone, address, pincode, password, otp, otp_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssss", $name, $email, $phone, $address, $pincode, $hash, $otp, $expiry);

if ($stmt->execute()) {
    $_SESSION['user_id']      = $conn->insert_id;
    $_SESSION['verify_email'] = $email;
    $_SESSION['name']         = $name;

    // Send OTP via email (separate from the JSON response — OTP is NOT returned)
    sendOTP($email, $name, $otp);

    echo json_encode(["status" => "success", "message" => "Account created! Please check your email for the OTP."]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
}

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
        $mail->Subject = 'Elegance Bakes — OTP Verification';
        $mail->Body    = "
            <h2 style='color:#d4a574'>Welcome $name! 🍰</h2>
            <div style='background:#f8e8d9;padding:30px;border-radius:15px;text-align:center;border:2px solid #d4a574'>
                <h1 style='font-size:48px;color:#d4a574;letter-spacing:10px'>$otp</h1>
                <p>Your OTP — valid for <strong>10 minutes</strong>. Do not share it with anyone.</p>
            </div>
        ";
        $mail->send();
    } catch (Exception $e) {
        error_log("Email failed: {$mail->ErrorInfo}");
    }
}
?>
