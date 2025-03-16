<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

include "conn.php"; // Include your connection file

// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$firstName = $data['firstName'] ?? '';
$lastName = $data['lastName'] ?? '';
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$email = $data['email'] ?? ''; // Capture email
$address = $data['address'] ?? '';  // Capture address

// Validate inputs
if (empty($firstName) || empty($lastName) || empty($username) || empty($password) || empty($email) || empty($address)) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required."
    ]);
    exit();
}

// Hash the password before storing it
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Prepare the SQL statement to prevent SQL injection
$sql = "INSERT INTO tbl_user (UserFirstName, UserLastName, UserEmail, UserName, UserPass, UserAddress, UserRoleID) 
        VALUES (:firstName, :lastName, :email, :username, :password, :address, 2)";

try {
    $stmt = $conn->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':firstName', $firstName);
    $stmt->bindParam(':lastName', $lastName);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $hashedPassword); // Store the hashed password
    $stmt->bindParam(':address', $address);

    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Sign up successful."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Error executing query."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}


// Close connection
$conn = null; // Close the PDO connection
?>
