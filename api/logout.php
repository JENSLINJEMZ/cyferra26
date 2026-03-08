<?php
// api/logout.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

$token = null;

if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token && isset($_POST['token'])) {
    $token = $_POST['token'];
}

if ($token) {
    $database = new Database();
    $db = $database->getConnection();
    
    // Delete the session
    $query = "DELETE FROM admin_sessions WHERE session_token = :token";
    $stmt = $db->prepare($query);
    $stmt->execute([':token' => $token]);
}

echo json_encode(['success' => true]);
?>