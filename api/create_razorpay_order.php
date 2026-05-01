<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!empty($body['user_id']) && is_numeric($body['user_id'])) {
        $_SESSION['user_id'] = (int)$body['user_id'];
    } else {
        ob_end_clean();
        exit(json_encode(array("status" => "error", "message" => "Please login to place an order")));
    }
}

$data   = json_decode(file_get_contents('php://input'), true);
$amount = isset($data['amount']) ? intval($data['amount'] * 100) : 0;

if ($amount <= 0) {
    ob_end_clean();
    exit(json_encode(array("status" => "error", "message" => "Invalid amount")));
}

$user_id = (int)$_SESSION['user_id'];
$payload = json_encode(array(
    'amount'          => $amount,
    'currency'        => 'INR',
    'receipt'         => 'order_' . time() . '_' . $user_id,
    'payment_capture' => 1
));

$context = stream_context_create(array(
    'http' => array(
        'method'  => 'POST',
        'header'  => array(
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET)
        ),
        'content' => $payload,
        'timeout' => 15
    )
));

$response = @file_get_contents('https://api.razorpay.com/v1/orders', false, $context);

if ($response === false) {
    ob_end_clean();
    exit(json_encode(array("status" => "error", "message" => "Could not connect to Razorpay. Check your API keys.")));
}

$rzpOrder = json_decode($response, true);

if (empty($rzpOrder['id'])) {
    $errMsg = isset($rzpOrder['error']['description']) ? $rzpOrder['error']['description'] : 'Razorpay order creation failed';
    ob_end_clean();
    exit(json_encode(array("status" => "error", "message" => $errMsg)));
}

ob_end_clean();
echo json_encode(array(
    "status"   => "success",
    "order_id" => $rzpOrder['id'],
    "amount"   => $amount,
    "currency" => "INR",
    "key_id"   => RAZORPAY_KEY_ID
));
?>
