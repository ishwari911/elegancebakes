<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── CONFIG — same keys as create_razorpay_order.php ──────────────
define('RZP_KEY_ID',     'rzp_live_SPDKcQNd1ecgXx');   // ← paste your rzp_live_xxx key
define('RZP_KEY_SECRET', 'Q1Q0Zhwb96tEvhlgAGdbvML4'); // ← paste your secret

// SMTP
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      'elegancebakes22@gmail.com');
define('SMTP_PASS',      'vcnlyghkyagruevr');
define('SMTP_FROM',      'elegancebakes22@gmail.com');
define('SMTP_FROM_NAME', 'Elegance Bakes');

// ── DB ────────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/database.php';
// $conn is now globally instantiated by database.php, and will be used below.
if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(array('status' => 'error', 'message' => 'DB failed: ' . $conn->connect_error));
    exit;
}
$conn->set_charset('utf8');

// ── SESSION ───────────────────────────────────────────────────────
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 3600);
session_start();

function jExit($arr) { ob_end_clean(); echo json_encode($arr); exit; }

// Read POST body
$data = json_decode(file_get_contents('php://input'), true);

// Session fallback for InfinityFree
if (!isset($_SESSION['user_id'])) {
    if (!empty($data['user_id']) && is_numeric($data['user_id'])) {
        $_SESSION['user_id'] = (int)$data['user_id'];
    } else {
        jExit(array('status' => 'error', 'message' => 'Session expired. Please log in again.'));
    }
}

// ── READ PAYMENT DATA ─────────────────────────────────────────────
$rzp_order_id   = isset($data['razorpay_order_id'])   ? trim($data['razorpay_order_id'])   : '';
$rzp_payment_id = isset($data['razorpay_payment_id']) ? trim($data['razorpay_payment_id']) : '';
$rzp_signature  = isset($data['razorpay_signature'])  ? trim($data['razorpay_signature'])  : '';
$items          = isset($data['items'])           ? $data['items']           : array();
$delivery_addr  = isset($data['delivery_address']) ? trim($data['delivery_address']) : '';
$delivery_date  = !empty($data['delivery_date'])   ? $data['delivery_date']  : null;

if (!$rzp_order_id || !$rzp_payment_id || !$rzp_signature) {
    jExit(array('status' => 'error', 'message' => 'Missing payment details'));
}
if (empty($items)) {
    jExit(array('status' => 'error', 'message' => 'Cart is empty'));
}

// ── VERIFY RAZORPAY SIGNATURE ─────────────────────────────────────
$expected = hash_hmac('sha256', $rzp_order_id . '|' . $rzp_payment_id, RZP_KEY_SECRET);
if (!hash_equals($expected, $rzp_signature)) {
    jExit(array('status' => 'error', 'message' => 'Payment signature mismatch. Payment ID: ' . $rzp_payment_id));
}

// ── SIGNATURE VALID — get user info ──────────────────────────────
$user_id = (int)$_SESSION['user_id'];

$uStmt = $conn->prepare("SELECT first_name, email FROM users WHERE id = ?");
$uStmt->bind_param("i", $user_id);
$uStmt->execute();
$uRow       = $uStmt->get_result()->fetch_assoc();
$user_name  = isset($uRow['first_name']) ? $uRow['first_name'] : 'Customer';
$user_email = isset($uRow['email'])      ? $uRow['email']      : '';

// Delivery address fallback
if (empty($delivery_addr)) {
    $aStmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
    $aStmt->bind_param("i", $user_id);
    $aStmt->execute();
    $aRow         = $aStmt->get_result()->fetch_assoc();
    $delivery_addr = isset($aRow['address']) ? $aRow['address'] : 'Address not provided';
}

// ── SAVE ORDERS ───────────────────────────────────────────────────
$inserted   = 0;
$item_names = array();

foreach ($items as $item) {
    $item_name = isset($item['name'])  ? trim($item['name'])  : 'Unknown Item';
    $price     = isset($item['price']) ? floatval($item['price']) : 0;
    $qty       = isset($item['qty'])   ? max(1, intval($item['qty'])) : 1;
    $total     = round($price * $qty * 1.05, 2);

    if ($price <= 0) continue;

    $pay_status = 'paid';
    $ins = $conn->prepare(
        "INSERT INTO orders (user_id, cake_name, quantity, total_price, delivery_address, delivery_date, payment_status, razorpay_payment_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->bind_param("isidssss", $user_id, $item_name, $qty, $total, $delivery_addr, $delivery_date, $pay_status, $rzp_payment_id);

    if ($ins->execute()) {
        $inserted++;
        $item_names[] = array('name' => $item_name, 'qty' => $qty, 'price' => $price, 'total' => $total);
    } else {
        error_log("Order insert failed: " . $conn->error . " | Item: " . $item_name);
    }
}

if ($inserted > 0) {
    // Send confirmation email
    if ($user_email) {
        sendConfirmEmail($user_email, $user_name, $item_names, $delivery_addr, $delivery_date, $rzp_payment_id);
    }
    jExit(array(
        'status'         => 'success',
        'message'        => 'Payment verified & order placed! 🎂',
        'orders_created' => $inserted,
        'payment_id'     => $rzp_payment_id,
        'email'          => $user_email
    ));
} else {
    jExit(array(
        'status'  => 'error',
        'message' => 'Payment verified but order save failed. Contact support with payment ID: ' . $rzp_payment_id . '. DB: ' . $conn->error
    ));
}

// ── EMAIL ─────────────────────────────────────────────────────────
function sendConfirmEmail($to, $name, $items, $address, $delivery_date, $payment_id) {
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
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = '🎂 Your Elegance Bakes Order is Confirmed!';

        $rows = '';
        $grand = 0;
        foreach ($items as $item) {
            $rows  .= "<tr>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8'>" . htmlspecialchars($item['name']) . "</td>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8;text-align:center'>" . $item['qty'] . "</td>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8;text-align:right'>₹" . number_format($item['price'], 0) . "</td>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8;text-align:right;font-weight:700'>₹" . number_format($item['total'], 2) . "</td>
            </tr>";
            $grand += $item['total'];
        }

        $dateStr = $delivery_date ? date('l, d F Y', strtotime($delivery_date)) : 'To be confirmed';
        $payRef  = $payment_id ? "<p style='color:#a0724a;font-size:13px;margin:8px 0 0'>Payment ID: <code>$payment_id</code></p>" : '';

        $mail->Body = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#f5ecea;font-family:Georgia,serif'>
          <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5ecea;padding:30px 0'>
            <tr><td align='center'>
              <table width='600' cellpadding='0' cellspacing='0' style='background:white;border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(106,60,60,0.12)'>
                <tr><td style='background:linear-gradient(135deg,#6a3c3c,#a0724a);padding:36px 40px;text-align:center'>
                  <h1 style='color:white;font-size:32px;margin:0'>Elegance Bakes 🎂</h1>
                  <p style='color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:16px'>Order Confirmed!</p>
                </td></tr>
                <tr><td style='padding:32px 40px 10px'>
                  <p style='font-size:18px;color:#6a3c3c;font-weight:700;margin:0'>Hi " . htmlspecialchars($name) . "! 👋</p>
                  <p style='color:#8b5b5b;font-size:15px;margin:10px 0 0'>Thank you! Your payment was successful. 🍰</p>
                  $payRef
                </td></tr>
                <tr><td style='padding:20px 40px'>
                  <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border:1px solid #f0e0d8;border-radius:12px;overflow:hidden'>
                    <thead><tr style='background:#fdf3ee'>
                      <th style='padding:12px 14px;text-align:left;color:#6a3c3c;font-size:13px'>Item</th>
                      <th style='padding:12px 14px;text-align:center;color:#6a3c3c;font-size:13px'>Qty</th>
                      <th style='padding:12px 14px;text-align:right;color:#6a3c3c;font-size:13px'>Price</th>
                      <th style='padding:12px 14px;text-align:right;color:#6a3c3c;font-size:13px'>Subtotal</th>
                    </tr></thead>
                    <tbody>$rows</tbody>
                    <tfoot><tr style='background:#fdf3ee'>
                      <td colspan='3' style='padding:14px;font-weight:700;color:#6a3c3c;font-size:16px'>Grand Total (incl. 5% GST)</td>
                      <td style='padding:14px;font-weight:700;color:#6a3c3c;font-size:18px;text-align:right'>₹" . number_format($grand, 2) . "</td>
                    </tr></tfoot>
                  </table>
                </td></tr>
                <tr><td style='padding:10px 40px 30px'>
                  <table width='100%' cellpadding='0' cellspacing='0' style='background:#fdf8f5;border-radius:14px;padding:20px;border:1px solid #f0e0d8'>
                    <tr><td style='padding:8px 0'><strong style='color:#6a3c3c'>📍 Delivery Address:</strong><br>
                      <span style='color:#8b5b5b;font-size:14px'>" . htmlspecialchars($address) . "</span></td></tr>
                    <tr><td style='padding:8px 0'><strong style='color:#6a3c3c'>🗓️ Expected Delivery:</strong><br>
                      <span style='color:#8b5b5b;font-size:14px'>$dateStr</span></td></tr>
                  </table>
                </td></tr>
                <tr><td style='background:#f5ecea;padding:24px 40px;text-align:center'>
                  <p style='color:#a0724a;font-size:14px;margin:0'>Questions? Call us: <strong>+91 7715972816</strong></p>
                  <p style='color:#c7a9a0;font-size:12px;margin:10px 0 0'>© 2026 Elegance Bakes, Mumbai</p>
                </td></tr>
              </table>
            </td></tr>
          </table>
        </body></html>";

        $mail->AltBody = "Hi $name! Your Elegance Bakes order is confirmed. Total: ₹" . number_format($grand, 2) . ". Delivery to: $address on $dateStr. Payment ID: $payment_id";
        $mail->send();
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
    }
}
?>
