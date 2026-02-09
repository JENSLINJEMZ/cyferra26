<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

// This endpoint is for admins to verify payments
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $registration_id = $_POST['registration_id'] ?? '';
    $status = $_POST['status'] ?? ''; // 'verified' or 'rejected'
    
    if (!$registration_id || !$status) {
        throw new Exception('Missing required parameters');
    }
    
    $query = "UPDATE registrations 
              SET payment_status = :status 
              WHERE registration_id = :registration_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':status' => $status,
        ':registration_id' => $registration_id
    ]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment status updated successfully'
        ]);
    } else {
        throw new Exception('Registration not found');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
