<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get filter parameters
    $payment_status = $_GET['payment_status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $query = "SELECT r.*, 
              GROUP_CONCAT(DISTINCT CONCAT(re.event_type, ':', re.event_name) SEPARATOR ', ') as events,
              ps.file_name as screenshot_file
              FROM registrations r
              LEFT JOIN registration_events re ON r.registration_id = re.registration_id
              LEFT JOIN payment_screenshots ps ON r.registration_id = ps.registration_id
              WHERE 1=1";
    
    $params = [];
    
    if ($payment_status) {
        $query .= " AND r.payment_status = :payment_status";
        $params[':payment_status'] = $payment_status;
    }
    
    if ($search) {
        $query .= " AND (r.full_name LIKE :search OR r.email LIKE :search OR r.registration_id LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " GROUP BY r.registration_id ORDER BY r.registration_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $registrations,
        'total' => count($registrations)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
