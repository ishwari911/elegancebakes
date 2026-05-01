<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();
require_once 'config/database.php';

function jsonExit($arr) {
    ob_end_clean();
    echo json_encode($arr);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    jsonExit(['success' => false, 'message' => 'Not logged in']);
}

$user_id = (int) $_SESSION['user_id'];
$action  = $_GET['action'] ?? 'profile';

if ($action === 'orders') {
    // Detect if payment columns exist
    $hasPaymentCols = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_status'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasPaymentCols = true;
    }

    if ($hasPaymentCols) {
        $sql = "SELECT o.id, o.quantity, o.total_price, o.delivery_address, o.delivery_date,
                       o.status, o.order_date, o.payment_status,
                       COALESCE(o.razorpay_payment_id, '') AS razorpay_payment_id,
                       COALESCE(o.cake_name, 'Custom Item') AS cake_name
                FROM orders o
                WHERE o.user_id = ?
                ORDER BY o.order_date DESC";
    } else {
        $sql = "SELECT o.id, o.quantity, o.total_price, o.delivery_address, o.delivery_date,
                       o.status, o.order_date, 'pending' AS payment_status, '' AS razorpay_payment_id,
                       COALESCE(o.cake_name, 'Custom Item') AS cake_name
                FROM orders o
                WHERE o.user_id = ?
                ORDER BY o.order_date DESC";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        jsonExit(['success' => true, 'orders' => [], 'db_error' => $conn->error]);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    jsonExit(['success' => true, 'orders' => $orders]);
}

// Default: return profile info
$stmt = $conn->prepare(
    "SELECT first_name, email, phone, address, pincode FROM users WHERE id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    jsonExit([
        'success' => true,
        'user_id' => $user_id,
        'name'    => $user['first_name'],
        'email'   => $user['email'],
        'phone'   => $user['phone'],
        'address' => $user['address'],
        'pincode' => $user['pincode']
    ]);
} else {
    jsonExit(['success' => false, 'message' => 'User not found']);
}
?>

