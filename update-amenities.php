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
$amenityID = $data['AmenityID'] ?? '';
$amenityName = $data['AmenityName'] ?? '';
$amenityDescription = $data['AmenityDescription'] ?? '';
$amenityPrice = $data['AmenityPrice'] ?? 0;

// Check if the Amenity ID is provided
if (empty($amenityID)) {
    echo json_encode(["success" => false, "message" => "Amenity ID is required."]);
    exit();
}

// Prepare the SQL statement to update the amenity
$update_sql = "UPDATE tbl_amenities SET AmenityName = :amenity_name, AmenityDescription = :amenity_description, AmenityPrice = :amenity_price WHERE AmenityID = :amenity_id";
$update_stmt = $conn->prepare($update_sql);

// Bind parameters for the amenity update
$update_stmt->bindParam(":amenity_name", $amenityName);
$update_stmt->bindParam(":amenity_description", $amenityDescription);
$update_stmt->bindParam(":amenity_price", $amenityPrice);
$update_stmt->bindParam(":amenity_id", $amenityID);

// Execute the update statement
if ($update_stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Amenity updated successfully."]);
} else {
    echo json_encode(["success" => false, "message" => "Error updating amenity: " . $update_stmt->errorInfo()[2]]);
}

// Close the database connection
$conn = null;
