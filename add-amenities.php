<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

include "../conn.php";


// Get the JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

$amenityName = $data['amenityName'] ?? '';
$amenityDescription = $data['amenityDescription'] ?? '';
$amenityPrice = $data['amenityPrice'] ?? '';

// Validate inputs
if (empty($amenityName) || empty($amenityDescription) || empty($amenityPrice)) {
    echo json_encode([
        "success" => false,
        "message" => "All fields are required."
    ]);
    exit();
}

// Prepare the SQL statement to prevent SQL injection
$sql = "INSERT INTO tbl_amenities (AmenityName, AmenityDescription, AmenityPrice) 
        VALUES (:amenityName, :amenityDescription, :amenityPrice)";

try {
    $stmt = $conn->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':amenityName', $amenityName);
    $stmt->bindParam(':amenityDescription', $amenityDescription);
    $stmt->bindParam(':amenityPrice', $amenityPrice);

    // Execute the statement
    if ($stmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => "Amenity added successfully."
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
