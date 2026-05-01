<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json');
session_start();
require_once '../config/database.php';


// Must be logged in
if (!isset($_SESSION['user_id'])) {
    exit(json_encode(["status" => "error", "message" => "Please login to place an order"]));
}

$data  = json_decode(file_get_contents('php://input'), true);
$items = $data['items'] ?? [];

if (empty($items)) {
    exit(json_encode(["status" => "error", "message" => "Cart is empty"]));
}

$user_id = (int) $_SESSION['user_id'];

// Use address + date from the POST body (user filled in checkout form)
// Fall back to DB address if user somehow bypassed the form
$delivery_address = trim($data['delivery_address'] ?? '');
$delivery_date    = !empty($data['delivery_date']) ? $data['delivery_date'] : null;

if (empty($delivery_address)) {
    // fallback: fetch from DB
    $stmt = $conn->prepare("SELECT address FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $delivery_address = !empty($user['address']) ? $user['address'] : 'Address not provided';
}

// Fetch user email for confirmation
$uStmt = $conn->prepare("SELECT first_name, email FROM users WHERE id = ?");
$uStmt->bind_param("i", $user_id);
$uStmt->execute();
$userRow = $uStmt->get_result()->fetch_assoc();
$user_name  = $userRow['first_name'] ?? 'Customer';
$user_email = $userRow['email'] ?? '';

$inserted   = 0;
$order_ids  = [];
$item_names = []; // for the email summary

foreach ($items as $item) {
    $item_name = trim($item['name']  ?? 'Unknown Item');
    $price     = floatval($item['price'] ?? 0);
    $qty       = max(1, intval($item['qty'] ?? 1));
    $total     = round($price * $qty * 1.05, 2);

    if ($price <= 0) continue;

    // Try to match a cake in the cakes table
    $base_name = trim(preg_replace('/\s*\([\d.]+\s*kg\)/i', '', $item_name));
    $cake_id   = null;

    $cakeStmt = $conn->prepare("SELECT id FROM cakes WHERE name LIKE ? LIMIT 1");
    $like     = '%' . $base_name . '%';
    $cakeStmt->bind_param("s", $like);
    $cakeStmt->execute();
    $cakeRow = $cakeStmt->get_result()->fetch_assoc();
    if ($cakeRow) {
        $cake_id = (int) $cakeRow['id'];
    }

    // Insert order row (cake_name stored directly so orders are self-contained)
    if ($cake_id !== null) {
        $ins = $conn->prepare(
            "INSERT INTO orders (user_id, cake_id, cake_name, quantity, total_price, delivery_address, delivery_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param("iisidss", $user_id, $cake_id, $item_name, $qty, $total, $delivery_address, $delivery_date);
    } else {
        $ins = $conn->prepare(
            "INSERT INTO orders (user_id, cake_name, quantity, total_price, delivery_address, delivery_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param("isidss", $user_id, $item_name, $qty, $total, $delivery_address, $delivery_date);
    }

    if ($ins->execute()) {
        $inserted++;
        $order_ids[]  = $conn->insert_id;
        $item_names[] = ['name' => $item_name, 'qty' => $qty, 'price' => $price, 'total' => $total];
    }
}

if ($inserted > 0) {
    // ── Send confirmation email ──
    $emailSent = false;
    if ($user_email) {
        $emailSent = sendOrderConfirmation(
            $user_email,
            $user_name,
            $item_names,
            $delivery_address,
            $delivery_date
        );
    }

    echo json_encode([
        "status"         => "success",
        "message"        => "Order placed successfully! Thank you 🎂",
        "orders_created" => $inserted,
        "email_sent"     => $emailSent
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to save order to database"]);
}

// ─────────────────────────────────────────────────────────
// sendOrderConfirmation — uses PHPMailer + Gmail SMTP
// ─────────────────────────────────────────────────────────
function sendOrderConfirmation($to_email, $to_name, $items, $address, $delivery_date) {
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
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = '🎂 Your Elegance Bakes Order is Confirmed!';

        // Build items table rows
        $rowsHtml  = '';
        $grandTotal = 0;
        foreach ($items as $item) {
            $rowsHtml  .= "
            <tr>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8;'>{$item['name']}</td>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8;text-align:center;'>{$item['qty']}</td>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8;text-align:right;'>₹" . number_format($item['price'], 0) . "</td>
              <td style='padding:10px 14px;border-bottom:1px solid #f0e0d8;text-align:right;font-weight:700;'>₹" . number_format($item['total'], 2) . "</td>
            </tr>";
            $grandTotal += $item['total'];
        }

        $dateStr = $delivery_date
            ? date('l, d F Y', strtotime($delivery_date))
            : 'To be confirmed';

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#f5ecea;font-family:Georgia,serif;'>
          <table width='100%' cellpadding='0' cellspacing='0' style='background:#f5ecea;padding:30px 0;'>
            <tr><td align='center'>
              <table width='600' cellpadding='0' cellspacing='0' style='background:white;border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(106,60,60,0.12);'>

                <!-- Header -->
                <tr>
                  <td style='background:linear-gradient(135deg,#6a3c3c,#a0724a);padding:36px 40px;text-align:center;'>
                    <h1 style='color:white;font-size:32px;margin:0;font-family:Georgia,serif;'>Elegance Bakes 🎂</h1>
                    <p style='color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:16px;'>Order Confirmed!</p>
                  </td>
                </tr>

                <!-- Greeting -->
                <tr>
                  <td style='padding:32px 40px 10px;'>
                    <p style='font-size:18px;color:#6a3c3c;font-weight:700;margin:0;'>Hi {$to_name}! 👋</p>
                    <p style='color:#8b5b5b;font-size:15px;margin:10px 0 0;'>
                      Thank you for your order! We've received it and will begin baking with love. 🍰<br>
                      Here's a summary of what you ordered:
                    </p>
                  </td>
                </tr>

                <!-- Items Table -->
                <tr>
                  <td style='padding:20px 40px;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='border-collapse:collapse;border:1px solid #f0e0d8;border-radius:12px;overflow:hidden;'>
                      <thead>
                        <tr style='background:#fdf3ee;'>
                          <th style='padding:12px 14px;text-align:left;color:#6a3c3c;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;'>Item</th>
                          <th style='padding:12px 14px;text-align:center;color:#6a3c3c;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;'>Qty</th>
                          <th style='padding:12px 14px;text-align:right;color:#6a3c3c;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;'>Price/unit</th>
                          <th style='padding:12px 14px;text-align:right;color:#6a3c3c;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;'>Subtotal</th>
                        </tr>
                      </thead>
                      <tbody>{$rowsHtml}</tbody>
                      <tfoot>
                        <tr style='background:#fdf3ee;'>
                          <td colspan='3' style='padding:14px;font-weight:700;color:#6a3c3c;font-size:16px;'>Grand Total (incl. 5% tax)</td>
                          <td style='padding:14px;font-weight:700;color:#6a3c3c;font-size:18px;text-align:right;'>₹" . number_format($grandTotal, 2) . "</td>
                        </tr>
                      </tfoot>
                    </table>
                  </td>
                </tr>

                <!-- Delivery Info -->
                <tr>
                  <td style='padding:10px 40px 30px;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='background:#fdf8f5;border-radius:14px;padding:20px;border:1px solid #f0e0d8;'>
                      <tr>
                        <td style='padding:8px 0;'><strong style='color:#6a3c3c;'>📍 Delivery Address:</strong><br>
                          <span style='color:#8b5b5b;font-size:14px;'>{$address}</span></td>
                      </tr>
                      <tr>
                        <td style='padding:8px 0;'><strong style='color:#6a3c3c;'>🗓️ Expected Delivery:</strong><br>
                          <span style='color:#8b5b5b;font-size:14px;'>{$dateStr}</span></td>
                      </tr>
                    </table>
                  </td>
                </tr>

                <!-- Footer -->
                <tr>
                  <td style='background:#f5ecea;padding:24px 40px;text-align:center;'>
                    <p style='color:#a0724a;font-size:14px;margin:0;'>Questions? Call us: <strong>+91 7715972816</strong> or email elegancebakes@email.com</p>
                    <p style='color:#c7a9a0;font-size:12px;margin:10px 0 0;'>© 2026 Elegance Bakes, Mumbai</p>
                  </td>
                </tr>

              </table>
            </td></tr>
          </table>
        </body>
        </html>";

        $mail->AltBody = "Hi $to_name! Your Elegance Bakes order has been confirmed. Grand Total: ₹" . number_format($grandTotal, 2) . ". Delivery to: $address on $dateStr. Questions? Call +91 7715972816.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Don't fail the order if email fails — just log silently
        error_log("Mailer error: " . $e->getMessage());
        return false;
    }
}
?>
