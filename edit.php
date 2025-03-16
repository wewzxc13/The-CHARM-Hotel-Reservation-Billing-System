<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include database connection (assuming $conn is a PDO instance)
include "../conn.php";

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

// Extract variables from the data array
$first_name = $data['first_name'] ?? '';
$last_name = $data['last_name'] ?? '';
$email = $data['email'] ?? '';
$username = $data['username'] ?? '';
$address = $data['address'] ?? '';
$old_password = $data['old_password'] ?? '';
$new_password = $data['new_password'] ?? '';
$user_id = $data['user_id'] ?? '';

// Check if the user ID is provided
if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User ID is required."]);
    exit();
}

// Check if old password and new password are provided for a password change
if (!empty($old_password) && !empty($new_password)) {
    // Select current hashed password for validation
    $stmt = $conn->prepare("SELECT UserPass FROM tbl_user WHERE UserID = :user_id");
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $current_password = $stmt->fetchColumn();

    // Validate the old password using password_verify()
    if (!password_verify($old_password, $current_password)) {
        echo json_encode(["success" => false, "message" => "Old password is incorrect."]);
        exit();
    }

    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update the password
    $update_password_stmt = $conn->prepare("UPDATE tbl_user SET UserPass = :new_password WHERE UserID = :user_id");
    $update_password_stmt->bindParam(":new_password", $hashed_password);
    $update_password_stmt->bindParam(":user_id", $user_id);
    if (!$update_password_stmt->execute()) {
        echo json_encode(["success" => false, "message" => "Error updating password: " . $update_password_stmt->errorInfo()[2]]);
        $conn = null; // Close connection
        exit();
    }
}

// Prepare the SQL statement to update the user information
$update_sql = "UPDATE tbl_user SET UserFirstName = :first_name, UserLastName = :last_name, UserEmail = :email, UserName = :username, UserAddress = :address WHERE UserID = :user_id";
$update_stmt = $conn->prepare($update_sql);

// Bind parameters for the user info update
$update_stmt->bindParam(":first_name", $first_name);
$update_stmt->bindParam(":last_name", $last_name);
$update_stmt->bindParam(":email", $email);
$update_stmt->bindParam(":username", $username);
$update_stmt->bindParam(":address", $address);
$update_stmt->bindParam(":user_id", $user_id);

// Execute the update statement
if ($update_stmt->execute()) {
    echo json_encode(["success" => true, "message" => "User information updated successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Error updating user information: " . $update_stmt->errorInfo()[2]]);
}

$conn = null; // Close connection
?>
