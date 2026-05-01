<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit(json_encode(['status' => 'error', 'message' => 'Invalid email address']));
}

// Check user exists
$stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    exit(json_encode(['status' => 'error', 'message' => 'No account found with that email']));
}

// Generate OTP + 15-min expiry
$otp    = sprintf("%06d", mt_rand(100000, 999999));
$expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

$upd = $conn->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE email = ?");
$upd->bind_param("sss", $otp, $expiry, $email);
$upd->execute();

// Send email via PHPMailer
try {
    require_once '../PHPMailer/src/PHPMailer.php';
    require_once '../PHPMailer/src/SMTP.php';
    require_once '../PHPMailer/src/Exception.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($email, $user['first_name']);
    $mail->isHTML(true);
    $mail->Subject = '🔑 Elegance Bakes — Password Reset OTP';
    $mail->Body    = "
    <!DOCTYPE html>
    <html><body style='margin:0;padding:0;background:#f5ecea;font-family:Georgia,serif;'>
      <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5ecea;padding:30px 0;'>
        <tr><td align='center'>
          <table width='520' cellpadding='0' cellspacing='0' style='background:white;border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(106,60,60,.12);'>
            <tr>
              <td style='background:linear-gradient(135deg,#6a3c3c,#a0724a);padding:32px 36px;text-align:center;'>
                <h1 style='color:white;font-size:28px;margin:0;font-family:Georgia,serif;'>Elegance Bakes 🎂</h1>
                <p style='color:rgba(255,255,255,.8);margin:8px 0 0;font-size:15px;'>Password Reset Request</p>
              </td>
            </tr>
            <tr>
              <td style='padding:32px 40px 10px;'>
                <p style='font-size:17px;color:#6a3c3c;font-weight:700;margin:0;'>Hi {$user['first_name']}! 👋</p>
                <p style='color:#8b5b5b;font-size:15px;margin:12px 0 24px;'>Someone requested a password reset for your account. Use the OTP below to continue:</p>
              </td>
            </tr>
            <tr>
              <td style='padding:0 40px 10px;text-align:center;'>
                <div style='background:linear-gradient(135deg,#f8e8d9,#fdf3ee);border:2px dashed #d4a574;border-radius:16px;padding:28px;display:inline-block;width:100%;box-sizing:border-box;'>
                  <p style='font-size:11px;color:#a0724a;text-transform:uppercase;letter-spacing:2px;margin:0 0 10px;font-weight:700;'>Your One-Time Password</p>
                  <span style='font-size:52px;font-weight:700;color:#6a3c3c;letter-spacing:14px;font-family:monospace;'>{$otp}</span>
                  <p style='font-size:13px;color:#b09080;margin:12px 0 0;'>Valid for <strong>15 minutes</strong>. Do not share this code.</p>
                </div>
              </td>
            </tr>
            <tr>
              <td style='padding:24px 40px 32px;'>
                <p style='font-size:13px;color:#b09080;margin:0;'>If you didn't request this, you can safely ignore this email. Your password will not change.</p>
              </td>
            </tr>
            <tr>
              <td style='background:#f5ecea;padding:20px 40px;text-align:center;'>
                <p style='color:#c7a9a0;font-size:12px;margin:0;'>© 2026 Elegance Bakes, Mumbai</p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body></html>";

    $mail->AltBody = "Hi {$user['first_name']}! Your Elegance Bakes password reset OTP is: {$otp}. Valid for 15 minutes.";
    $mail->send();

    echo json_encode(['status' => 'success', 'message' => 'OTP sent to your email']);
} catch (Exception $e) {
    error_log("Forgot password email error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to send email. Please try again.']);
}
?>
