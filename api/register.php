<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Response function
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

try {
    // Database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        sendResponse(false, 'Database connection failed');
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Generate unique registration ID
    $registration_id = 'CYF26-' . strtoupper(uniqid());
    
    // Get form data
    $full_name = filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_STRING);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $mobile = filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_STRING);
    $whatsapp = filter_input(INPUT_POST, 'whatsapp', FILTER_SANITIZE_STRING);
    $year = filter_input(INPUT_POST, 'year', FILTER_SANITIZE_STRING);
    $college = filter_input(INPUT_POST, 'college', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $food_preference = filter_input(INPUT_POST, 'food_preference', FILTER_SANITIZE_STRING);
    $transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_SANITIZE_STRING);
    
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Validate required fields
    if (!$full_name || !$gender || !$email || !$mobile || !$whatsapp || 
        !$year || !$college || !$department || !$food_preference) {
        sendResponse(false, 'All required fields must be filled');
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email address');
    }
    
    // Check if email already exists
    $check_query = "SELECT id FROM registrations WHERE email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        sendResponse(false, 'This email is already registered');
    }
    
    // Insert main registration data
    $query = "INSERT INTO registrations 
              (registration_id, full_name, gender, email, mobile, whatsapp, 
               year_of_study, college_name, department, food_preference, 
               transaction_id, ip_address) 
              VALUES 
              (:registration_id, :full_name, :gender, :email, :mobile, :whatsapp, 
               :year, :college, :department, :food_preference, 
               :transaction_id, :ip_address)";
    
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':registration_id', $registration_id);
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':gender', $gender);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':mobile', $mobile);
    $stmt->bindParam(':whatsapp', $whatsapp);
    $stmt->bindParam(':year', $year);
    $stmt->bindParam(':college', $college);
    $stmt->bindParam(':department', $department);
    $stmt->bindParam(':food_preference', $food_preference);
    $stmt->bindParam(':transaction_id', $transaction_id);
    $stmt->bindParam(':ip_address', $ip_address);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert registration data');
    }
    
    // Insert selected events
    $technical_events = isset($_POST['technical_events']) ? $_POST['technical_events'] : [];
    $non_technical_events = isset($_POST['non_technical_events']) ? $_POST['non_technical_events'] : [];
    
    $event_query = "INSERT INTO registration_events 
                    (registration_id, event_type, event_name) 
                    VALUES (:registration_id, :event_type, :event_name)";
    $event_stmt = $db->prepare($event_query);
    
    // Insert technical events
    foreach ($technical_events as $event) {
        $event_stmt->execute([
            ':registration_id' => $registration_id,
            ':event_type' => 'technical',
            ':event_name' => $event
        ]);
    }
    
    // Insert non-technical events
    foreach ($non_technical_events as $event) {
        $event_stmt->execute([
            ':registration_id' => $registration_id,
            ':event_type' => 'non-technical',
            ':event_name' => $event
        ]);
    }
    
    // Handle payment screenshot upload
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/payment_screenshots/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_tmp = $_FILES['payment_proof']['tmp_name'];
        $file_name = $_FILES['payment_proof']['name'];
        $file_size = $_FILES['payment_proof']['size'];
        $file_type = $_FILES['payment_proof']['type'];
        
        // Validate file size (5MB max)
        if ($file_size > 5242880) {
            throw new Exception('File size must be less than 5MB');
        }
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG, and PDF allowed');
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $new_filename = $registration_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $new_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file_tmp, $file_path)) {
            // Save file info to database
            $file_query = "INSERT INTO payment_screenshots 
                          (registration_id, file_name, file_path, file_size, file_type) 
                          VALUES 
                          (:registration_id, :file_name, :file_path, :file_size, :file_type)";
            
            $file_stmt = $db->prepare($file_query);
            $file_stmt->execute([
                ':registration_id' => $registration_id,
                ':file_name' => $new_filename,
                ':file_path' => $file_path,
                ':file_size' => $file_size,
                ':file_type' => $file_type
            ]);
        } else {
            throw new Exception('Failed to upload payment screenshot');
        }
    } else {
        throw new Exception('Payment screenshot is required');
    }
    
    // Commit transaction
    $db->commit();
    
    // Send confirmation email (optional)
    sendConfirmationEmail($email, $full_name, $registration_id);
    
    // Return success response
    sendResponse(true, 'Registration successful!', [
        'registration_id' => $registration_id,
        'message' => 'Your registration has been received. Please wait for payment verification.'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db && $db->inTransaction()) {
        $db->rollback();
    }
    
    // Log error
    error_log('Registration error: ' . $e->getMessage());
    
    // Send error response
    sendResponse(false, 'Registration failed: ' . $e->getMessage());
}

// Email function
function sendConfirmationEmail($email, $name, $registration_id) {
    $subject = "CYFERRA26 Registration Confirmation";
    $message = "
    <html>
    <head>
        <title>Registration Confirmation</title>
    </head>
    <body>
        <h2>Welcome to CYFERRA26!</h2>
        <p>Dear $name,</p>
        <p>Your registration has been successfully received.</p>
        <p><strong>Registration ID:</strong> $registration_id</p>
        <p>Please keep this ID for future reference.</p>
        <p>Your payment will be verified within 24 hours.</p>
        <br>
        <p>Best regards,<br>CYFERRA26 Team</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: noreply@cyferra26.com' . "\r\n";
    
    mail($email, $subject, $message, $headers);
}
?>
