<?php
// ---- LIVE (InfinityFree) ----
$host     = 'sql200.infinityfree.com';
$dbname   = '**********';
$username = '***************';
$password = '*********';

// ---- LOCAL (XAMPP) — uncomment these and comment out the above when testing locally ----
// $host     = 'localhost';
// $dbname   = 'elegance_bakes';
// $username = 'root';
// $password = '';

// Detect if caller is an API (expects JSON) or a browser page (expects HTML)
// Note: using strpos() instead of str_contains() for PHP 7.x compatibility (InfinityFree)
function _is_api_request() {
    $ct  = $_SERVER['HTTP_ACCEPT']  ?? '';
    $uri = $_SERVER['REQUEST_URI']  ?? '';
    return (strpos($ct, 'application/json') !== false) || (strpos($uri, '/api/') !== false);
}

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        // Removed http_response_code(500) to stop Apache overriding JSON with HTML
        if (_is_api_request()) {
            die(json_encode(["status" => "error", "message" => "DB connection failed: " . $conn->connect_error]));
        } else {
            die("<h2 style='font-family:sans-serif;color:#c0392b;padding:40px'>⚠️ Database connection failed.<br><small style='color:#888'>" . htmlspecialchars($conn->connect_error) . "</small><br><br><a href='javascript:history.back()'>← Go back</a></h2>");
        }
    }
    $conn->set_charset("utf8");
} catch (Exception $e) {
    // Removed http_response_code(500) to stop Apache overriding JSON with HTML
    if (_is_api_request()) {
        die(json_encode(["status" => "error", "message" => "DB error: " . $e->getMessage()]));
    } else {
        die("<h2 style='font-family:sans-serif;color:#c0392b;padding:40px'>⚠️ Database error.<br><small style='color:#888'>" . htmlspecialchars($e->getMessage()) . "</small><br><br><a href='javascript:history.back()'>← Go back</a></h2>");
    }
}


// ===== SMTP CONFIG =====
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'elegancebakes22@gmail.com');
define('SMTP_PASS',     '****************');
define('SMTP_FROM',     'elegancebakes22@gmail.com');
define('SMTP_FROM_NAME','Elegance Bakes');

// ===== RAZORPAY KEYS =====
// ⚠️  REPLACE THESE WITH YOUR REAL KEYS from https://dashboard.razorpay.com → Settings → API Keys
// Without real keys, payments WILL fail with "Payment not verified"!
define('RAZORPAY_KEY_ID',     '*******************');
define('RAZORPAY_KEY_SECRET', '*********************');

// ===== ADMIN CREDENTIALS =====
// Username: admin  |  Password: EleganceBakes2026
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '***********************');
?>

