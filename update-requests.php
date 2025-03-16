<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include database connection
include "../conn.php";

// Get JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

// Extract variables from the data array
$requestID = $data['RequestID'] ?? '';
$requestName = $data['RequestName'] ?? '';
$requestDescription = $data['RequestDescription'] ?? '';
$requestPrice = $data['RequestPrice'] ?? 0;

// Check if the Request ID is provided
if (empty($requestID)) {
    echo json_encode(["success" => false, "message" => "Request ID is required."]);
    exit();
}

// Prepare the SQL statement to update the request
$update_sql = "UPDATE tbl_request SET RequestName = :request_name, RequestDescription = :request_description, RequestPrice = :request_price WHERE RequestID = :request_id";
$update_stmt = $conn->prepare($update_sql);

// Bind parameters for the request update
$update_stmt->bindParam(":request_name", $requestName);
$update_stmt->bindParam(":request_description", $requestDescription);
$update_stmt->bindParam(":request_price", $requestPrice);
$update_stmt->bindParam(":request_id", $requestID);

// Execute the update statement
if ($update_stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Request updated successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Error updating request: " . $update_stmt->errorInfo()[2]]);
}

// Close the database connection
$conn = null;
