<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Verify admin session
function verifyAdminSession($token) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        return false;
    }
    
    $query = "SELECT s.*, u.username, u.full_name, u.role 
              FROM admin_sessions s 
              JOIN admin_users u ON s.user_id = u.id 
              WHERE s.session_token = :token 
              AND s.expires_at > NOW() 
              AND u.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':token' => $token]);
    
    return $stmt->rowCount() > 0;
}

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
    }
    
    // Get token from POST data
    $token = $_POST['token'] ?? '';
    
    if (empty($token)) {
        sendResponse(false, 'Authentication required', null, 401);
    }
    
    // Verify admin session
    if (!verifyAdminSession($token)) {
        sendResponse(false, 'Invalid or expired session', null, 401);
    }
    
    // Get registration ID
    $registration_id = $_POST['registration_id'] ?? '';
    
    if (empty($registration_id)) {
        sendResponse(false, 'Registration ID is required', null, 400);
    }
    
    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        sendResponse(false, 'Database connection failed', null, 500);
    }
    
    // Get registration details
    $query = "SELECT r.*, 
              ps.file_name as screenshot_file,
              (SELECT COUNT(*) FROM check_ins WHERE registration_id = r.registration_id) as checked_in,
              (SELECT check_in_time FROM check_ins WHERE registration_id = r.registration_id ORDER BY id DESC LIMIT 1) as checked_in_time
              FROM registrations r 
              LEFT JOIN payment_screenshots ps ON r.registration_id = ps.registration_id 
              WHERE r.registration_id = :registration_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':registration_id' => $registration_id]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(false, 'Registration not found', null, 404);
    }
    
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get selected events
    $eventsQuery = "SELECT event_type, event_name 
                   FROM registration_events 
                   WHERE registration_id = :registration_id 
                   ORDER BY event_type, event_name";
    
    $eventsStmt = $db->prepare($eventsQuery);
    $eventsStmt->execute([':registration_id' => $registration_id]);
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Categorize events
    $technical_events = [];
    $non_technical_events = [];
    
    foreach ($events as $event) {
        if ($event['event_type'] === 'technical') {
            $technical_events[] = $event['event_name'];
        } else {
            $non_technical_events[] = $event['event_name'];
        }
    }
    
    // Format response
    $response = [
        'registration' => [
            'registration_id' => $registration['registration_id'],
            'full_name' => $registration['full_name'],
            'email' => $registration['email'],
            'mobile' => $registration['mobile'],
            'whatsapp' => $registration['whatsapp'],
            'college_name' => $registration['college_name'],
            'department' => $registration['department'],
            'year_of_study' => $registration['year_of_study'],
            'gender' => $registration['gender'],
            'food_preference' => $registration['food_preference'],
            'payment_status' => $registration['payment_status'],
            'registration_date' => $registration['registration_date'],
            'transaction_id' => $registration['transaction_id'],
            'has_screenshot' => !empty($registration['screenshot_file']),
            'checked_in' => (bool)$registration['checked_in'],
            'checked_in_time' => $registration['checked_in_time']
        ],
        'events' => [
            'technical' => $technical_events,
            'non_technical' => $non_technical_events,
            'count' => count($technical_events) + count($non_technical_events)
        ],
        'verification' => [
            'status' => $registration['payment_status'],
            'is_verified' => $registration['payment_status'] === 'verified',
            'is_pending' => $registration['payment_status'] === 'pending',
            'is_rejected' => $registration['payment_status'] === 'rejected',
            'status_text' => getStatusText($registration['payment_status'])
        ]
    ];
    
    // Log the scan activity
    logScanActivity($db, $registration_id, $token);
    
    sendResponse(true, 'Registration found', $response);
    
} catch (Exception $e) {
    error_log("Get registration error: " . $e->getMessage());
    sendResponse(false, 'An error occurred while processing your request', null, 500);
}

function getStatusText($status) {
    switch ($status) {
        case 'verified':
            return 'Verified';
        case 'pending':
            return 'Pending';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Unknown';
    }
}

function logScanActivity($db, $registration_id, $token) {
    try {
        // Get user ID from token
        $userQuery = "SELECT user_id FROM admin_sessions WHERE session_token = :token";
        $userStmt = $db->prepare($userQuery);
        $userStmt->execute([':token' => $token]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Log to admin_activity_logs
            $logQuery = "INSERT INTO admin_activity_logs 
                        (user_id, action_type, description, ip_address, user_agent) 
                        VALUES (:user_id, 'scan', 'Scanned QR code for registration: :reg_id', :ip, :ua)";
            
            $logStmt = $db->prepare($logQuery);
            $logStmt->execute([
                ':user_id' => $user['user_id'],
                ':reg_id' => $registration_id,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
    } catch (Exception $e) {
        // Silently fail for logging
        error_log("Failed to log scan activity: " . $e->getMessage());
    }
}
?>