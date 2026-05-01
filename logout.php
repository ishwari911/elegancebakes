<?php
session_start();
// Destroy ALL session data
session_unset();
session_destroy();
// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>
