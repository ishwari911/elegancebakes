<?php
// change-password.php - COPY TO ROOT
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$current = $input['current_password'] ?? '';
$new = $input['new_password'] ?? '';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=elegance_bakes", "root", "");
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!password_verify($current, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password wrong']);
        exit();
    }
    
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Password changed!']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
