<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include the database connection
include "../conn.php";

// Get the JSON payload from the request
$json = json_decode(file_get_contents("php://input"), true);

// Extract the reservation ID from the JSON
$reservationId = $json['reservationId'] ?? null;

// Validate input
if (empty($reservationId)) {
    echo json_encode(['success' => false, 'error' => 'Reservation ID is required']);
    exit;
}

try {
    // Update the ReservationStatus to "Confirmed"
    $sqlUpdate = "UPDATE tbl_reservation SET ReservationStatus = 'Confirmed' WHERE ReservationID = :reservationId";
    $stmt = $conn->prepare($sqlUpdate);
    $stmt->bindParam(':reservationId', $reservationId);
    $stmt->execute();

    // Check if the update was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Reservation confirmed successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Reservation could not be updated']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
