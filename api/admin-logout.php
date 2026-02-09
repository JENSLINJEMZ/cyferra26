<?php
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

function sendResponse($success, $message = '') {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        sendResponse(false, 'Database connection failed');
    }
    
    $token = $_POST['token'] ?? '';
    
    if (empty($token)) {
        sendResponse(false, 'No session token provided');
    }
    
    // Get session info before deleting
    $get_query = "SELECT user_id FROM admin_sessions WHERE session_token = :token";
    $get_stmt = $db->prepare($get_query);
    $get_stmt->execute([':token' => $token]);
    
    if ($get_stmt->rowCount() > 0) {
        $session = $get_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log activity
        $log_query = "INSERT INTO admin_activity_logs 
                      (user_id, action_type, description, ip_address, user_agent) 
                      VALUES (:user_id, 'logout', 'User logged out', :ip, :ua)";
        
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            ':user_id' => $session['user_id'],
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    // Delete session
    $delete_query = "DELETE FROM admin_sessions WHERE session_token = :token";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->execute([':token' => $token]);
    
    sendResponse(true, 'Logged out successfully');
    
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    sendResponse(false, 'An error occurred');
}
?>