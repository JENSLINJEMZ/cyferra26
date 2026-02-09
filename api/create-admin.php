    <?php
// Simple script to create admin user
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed\n");
}

// Default admin credentials
$username = 'admin';
$password = 'Cyf@&ec#Roll_Adm!n'; // Change this!
$full_name = 'System Administrator';
$email = 'admin@cyferra26.com';
$role = 'superadmin';

// Hash password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Check if admin already exists
$check_query = "SELECT id FROM admin_users WHERE username = :username";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute([':username' => $username]);

if ($check_stmt->rowCount() > 0) {
    echo "Admin user already exists!\n";
    exit;
}

// Insert admin user
$query = "INSERT INTO admin_users 
          (username, password_hash, full_name, email, role) 
          VALUES (:username, :password, :full_name, :email, :role)";

$stmt = $db->prepare($query);
$stmt->execute([
    ':username' => $username,
    ':password' => $hashed_password,
    ':full_name' => $full_name,
    ':email' => $email,
    ':role' => $role
]);

echo "========================================\n";
echo "ADMIN USER CREATED SUCCESSFULLY!\n";
echo "========================================\n";
echo "Username: $username\n";
echo "Password: $password\n";
echo "Full Name: $full_name\n";
echo "Email: $email\n";
echo "Role: $role\n";
echo "========================================\n";
echo "IMPORTANT: Change the password immediately after first login!\n";
echo "========================================\n";
?>