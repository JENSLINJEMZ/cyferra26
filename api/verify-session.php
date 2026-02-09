<?php
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

function verifySession($token) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        return ['authenticated' => false, 'message' => 'Database error'];
    }
    
    // Check if session exists and is not expired
    $query = "SELECT s.*, u.username, u.full_name, u.role 
              FROM admin_sessions s 
              JOIN admin_users u ON s.user_id = u.id 
              WHERE s.session_token = :token 
              AND s.expires_at > NOW() 
              AND u.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':token' => $token]);
    
    if ($stmt->rowCount() === 0) {
        return ['authenticated' => false, 'message' => 'Invalid or expired session'];
    }
    
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'authenticated' => true,
        'user' => [
            'id' => $session['user_id'],
            'username' => $session['username'],
            'full_name' => $session['full_name'],
            'role' => $session['role']
        ]
    ];
}

// Check if token was provided
$token = null;

// Check Authorization header
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

// Check POST data
if (!$token && isset($_POST['token'])) {
    $token = $_POST['token'];
}

// Check GET data (for debugging only, remove in production)
if (!$token && isset($_GET['token'])) {
    $token = $_GET['token'];
}

if (!$token) {
    echo json_encode(['authenticated' => false, 'message' => 'No token provided']);
    exit;
}

$result = verifySession($token);
echo json_encode($result);
?>