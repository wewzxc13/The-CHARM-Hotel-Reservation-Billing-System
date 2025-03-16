<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "dbhotelbilling";

session_start();

try {
    // Create PDO instance
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'];
    $password = $data['password'];

    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit;
    }

    // Prepare and execute query
    $stmt = $pdo->prepare('SELECT UserID, UserFirstName, UserLastName, UserRoleID, UserPass FROM tbl_user WHERE UserName = :username');
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();

    // Fetch user data
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Check if the password matches the hashed password
        if (password_verify($password, $user['UserPass'])) {
            // Password is correct
            echo json_encode([
                "success" => true,
                "message" => "Login successful.",
                "user" => [
                    "UserID" => $user['UserID'],
                    "UserFirstName" => $user['UserFirstName'],
                    "UserLastName" => $user['UserLastName'],
                    "UserRoleID" => $user['UserRoleID']
                ]
            ]);
        } else {
            // Invalid password
            echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        }
    } else {
        // Invalid username
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }

} catch (PDOException $e) {
    // Database error
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
