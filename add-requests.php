<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

include "../conn.php";


// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$requestName = $data['requestName'] ?? '';
$requestDescription = $data['requestDescription'] ?? '';
$requestPrice = $data['requestPrice'] ?? '';

// Validate inputs
if (empty($requestName) || empty($requestDescription) || empty($requestPrice)) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required."
    ]);
    exit();
}

// Prepare the SQL statement to prevent SQL injection
$sql = "INSERT INTO tbl_request (RequestName, RequestDescription, RequestPrice) 
        VALUES (:requestName, :requestDescription, :requestPrice)";

try {
    $stmt = $conn->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':requestName', $requestName);
    $stmt->bindParam(':requestDescription', $requestDescription);
    $stmt->bindParam(':requestPrice', $requestPrice);

    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Request added successfully."
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
