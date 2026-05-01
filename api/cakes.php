<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once '../config/database.php';

$stmt = $conn->prepare("SELECT id, name, description, price, image, category FROM cakes WHERE is_available = 1");
$stmt->execute();
$cakes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode(["status" => "success", "cakes" => $cakes]);
?>
