<?php
header("Content-Type: application/json; charset=UTF-8");

// Include database config
require_once '../config/database.php';

// Simple response function
function sendResponse($success, $message = '', $data = null) {
    $response = ['success' => $success];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    if ($data) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

try {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        sendResponse(false, 'Database connection failed');
    }
    
    // Get form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username) || empty($password)) {
        sendResponse(false, 'Username and password are required');
    }
    
    // Get user from database
    $query = "SELECT id, username, password_hash, full_name, role, status 
              FROM admin_users 
              WHERE username = :username AND status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        sendResponse(false, 'Invalid username or password');
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        sendResponse(false, 'Invalid username or password');
    }
    
    // Get client info
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Generate session token
    $session_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+8 hours'));
    
    // Create session
    $session_query = "INSERT INTO admin_sessions 
                      (user_id, session_token, ip_address, user_agent, expires_at) 
                      VALUES (:user_id, :token, :ip, :ua, :expires)";
    
    $session_stmt = $db->prepare($session_query);
    $session_stmt->execute([
        ':user_id' => $user['id'],
        ':token' => $session_token,
        ':ip' => $ip_address,
        ':ua' => $user_agent,
        ':expires' => $expires_at
    ]);
    
    // Update last login
    $update_query = "UPDATE admin_users 
                     SET last_login = NOW(), last_login_ip = :ip 
                     WHERE id = :id";
    
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([
        ':ip' => $ip_address,
        ':id' => $user['id']
    ]);
    
    // Log activity
    $log_query = "INSERT INTO admin_activity_logs 
                  (user_id, action_type, description, ip_address, user_agent) 
                  VALUES (:user_id, 'login', 'User logged in', :ip, :ua)";
    
    $log_stmt = $db->prepare($log_query);
    $log_stmt->execute([
        ':user_id' => $user['id'],
        ':ip' => $ip_address,
        ':ua' => $user_agent
    ]);
    
    // Prepare response
    $response_data = [
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ],
        'session' => [
            'token' => $session_token,
            'expires_at' => $expires_at
        ]
    ];
    
    sendResponse(true, 'Login successful', $response_data);
    
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    sendResponse(false, 'An error occurred. Please try again.');
}
?>