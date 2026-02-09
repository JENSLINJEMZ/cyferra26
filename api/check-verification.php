<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");

require_once '../config/database.php';

function sendResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Mask email function
function maskEmail($email) {
    if (empty($email)) return '';
    
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $username = $parts[0];
    $domain = $parts[1];
    
    // Keep first and last character of username, mask the rest
    if (strlen($username) <= 2) {
        $maskedUsername = str_repeat('*', strlen($username));
    } else {
        $maskedUsername = $username[0] . str_repeat('*', strlen($username) - 2) . substr($username, -1);
    }
    
    return $maskedUsername . '@' . $domain;
}

// Mask mobile number function
function maskMobile($mobile) {
    if (empty($mobile)) return '';
    
    $length = strlen($mobile);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    
    // Show last 4 digits, mask the rest
    $maskedPart = str_repeat('*', $length - 4);
    $lastFour = substr($mobile, -4);
    
    return $maskedPart . $lastFour;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        sendResponse(false, null, 'Database connection failed', 500);
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // GET request - check by registration ID, email, or mobile
        $registration_id = $_GET['registration_id'] ?? '';
        $email = $_GET['email'] ?? '';
        $mobile = $_GET['mobile'] ?? '';
        
        if (empty($registration_id) && empty($email) && empty($mobile)) {
            sendResponse(false, null, 'Please provide registration ID, email, or mobile number', 400);
        }
        
        $query = "SELECT r.*, p.file_name as screenshot_file 
                  FROM registrations r 
                  LEFT JOIN payment_screenshots p ON r.registration_id = p.registration_id 
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($registration_id)) {
            $query .= " AND r.registration_id = :registration_id";
            $params[':registration_id'] = $registration_id;
        }
        
        if (!empty($email)) {
            $query .= " AND r.email = :email";
            $params[':email'] = $email;
        }
        
        if (!empty($mobile)) {
            $query .= " AND r.mobile = :mobile";
            $params[':mobile'] = $mobile;
        }
        
        $query .= " ORDER BY r.registration_date DESC LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            sendResponse(false, null, 'Registration not found. Please check your details.', 404);
        }
        
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get selected events
        $eventsQuery = "SELECT event_type, event_name 
                       FROM registration_events 
                       WHERE registration_id = :registration_id 
                       ORDER BY event_type, event_name";
        
        $eventsStmt = $db->prepare($eventsQuery);
        $eventsStmt->execute([':registration_id' => $registration['registration_id']]);
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
        
        // Format response with masked email and mobile
        $response = [
            'registration' => [
                'registration_id' => $registration['registration_id'],
                'full_name' => $registration['full_name'],
                'email' => maskEmail($registration['email']), // Masked email
                'original_email' => $registration['email'], // Keep original for validation
                'mobile' => maskMobile($registration['mobile']), // Masked mobile
                'original_mobile' => $registration['mobile'], // Keep original for validation
                'whatsapp' => maskMobile($registration['whatsapp']), // Masked WhatsApp
                'college_name' => $registration['college_name'],
                'department' => $registration['department'],
                'year_of_study' => $registration['year_of_study'],
                'gender' => $registration['gender'],
                'food_preference' => $registration['food_preference'],
                'payment_status' => $registration['payment_status'],
                'registration_date' => $registration['registration_date'],
                'transaction_id' => $registration['transaction_id'],
                'has_screenshot' => !empty($registration['screenshot_file'])
            ],
            'events' => [
                'technical' => $technical_events,
                'non_technical' => $non_technical_events,
                'all' => array_merge($technical_events, $non_technical_events)
            ],
            'verification' => [
                'status' => $registration['payment_status'],
                'is_verified' => $registration['payment_status'] === 'verified',
                'is_pending' => $registration['payment_status'] === 'pending',
                'is_rejected' => $registration['payment_status'] === 'rejected',
                'status_text' => getStatusText($registration['payment_status']),
                'status_color' => getStatusColor($registration['payment_status'])
            ],
            'next_steps' => getNextSteps($registration['payment_status'])
        ];
        
        sendResponse(true, $response, 'Registration details retrieved successfully');
        
    } elseif ($method === 'POST') {
        // POST request - search with multiple criteria (EXCLUDING name and college search)
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            $data = $_POST;
        }
        
        $search_type = $data['search_type'] ?? 'registration_id';
        $search_value = $data['search_value'] ?? '';
        
        if (empty($search_value)) {
            sendResponse(false, null, 'Please provide search value', 400);
        }
        
        $query = "SELECT r.*, p.file_name as screenshot_file 
                  FROM registrations r 
                  LEFT JOIN payment_screenshots p ON r.registration_id = p.registration_id 
                  WHERE 1=1";
        
        switch ($search_type) {
            case 'registration_id':
                $query .= " AND r.registration_id LIKE :value";
                $search_value = '%' . $search_value . '%';
                break;
            case 'email':
                $query .= " AND r.email LIKE :value";
                $search_value = '%' . $search_value . '%';
                break;
            case 'mobile':
                $query .= " AND r.mobile LIKE :value";
                $search_value = '%' . $search_value . '%';
                break;
            // Removed 'name' and 'college' search options to prevent duplicates
            default:
                sendResponse(false, null, 'Invalid search type', 400);
        }
        
        $query .= " ORDER BY r.registration_date DESC LIMIT 20";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':value' => $search_value]);
        
        if ($stmt->rowCount() === 0) {
            sendResponse(false, null, 'No registrations found matching your search', 404);
        }
        
        $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format multiple results with masked data
        $formattedResults = [];
        foreach ($registrations as $reg) {
            $formattedResults[] = [
                'registration_id' => $reg['registration_id'],
                'full_name' => $reg['full_name'],
                'email' => maskEmail($reg['email']), // Masked email
                'mobile' => maskMobile($reg['mobile']), // Masked mobile
                'college_name' => $reg['college_name'],
                'payment_status' => $reg['payment_status'],
                'registration_date' => $reg['registration_date'],
                'verification' => [
                    'status' => $reg['payment_status'],
                    'is_verified' => $reg['payment_status'] === 'verified',
                    'status_text' => getStatusText($reg['payment_status']),
                    'status_color' => getStatusColor($reg['payment_status'])
                ]
            ];
        }
        
        sendResponse(true, [
            'count' => count($formattedResults),
            'results' => $formattedResults,
            'search_type' => $search_type,
            'search_value' => $data['search_value']
        ], 'Search completed successfully');
        
    } else {
        sendResponse(false, null, 'Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Check verification error: " . $e->getMessage());
    sendResponse(false, null, 'An error occurred while processing your request', 500);
}

function getStatusText($status) {
    switch ($status) {
        case 'verified':
            return '✅ Payment Verified - Registration Complete';
        case 'pending':
            return '⏳ Payment Pending Verification';
        case 'rejected':
            return '❌ Payment Rejected - Please contact support';
        default:
            return 'Unknown Status';
    }
}

function getStatusColor($status) {
    switch ($status) {
        case 'verified':
            return '#00ff88';
        case 'pending':
            return '#ffaa00';
        case 'rejected':
            return '#ff4444';
        default:
            return '#666666';
    }
}

function getNextSteps($status) {
    switch ($status) {
        case 'verified':
            return [
                '1' => 'Your registration is complete',
                '2' => 'You will receive event details via email',
                '3' => 'Bring your registration ID to the venue'
            ];
        case 'pending':
            return [
                '1' => 'Your payment is under verification',
                '2' => 'Verification usually takes 24-48 hours',
                '3' => 'You will receive email notification once verified'
            ];
        case 'rejected':
            return [
                '1' => 'Your payment was not verified',
                '2' => 'Please contact support@cyferra26.com',
                '3' => 'Provide your registration ID for assistance'
            ];
        default:
            return ['Contact support for assistance'];
    }
}
?>