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

// Extract the room number and availability status from the JSON
$roomNumber = $json['RoomNumber'] ?? null;
$roomAvailabilityStatus = $json['RoomAvailabilityStatus'] ?? null;

// Validate input
if (empty($roomNumber) || empty($roomAvailabilityStatus)) {
    echo json_encode(['success' => false, 'error' => 'Room number and availability status are required']);
    exit;
}

try {
    // Check if the room has any active reservations with status 'Confirmed' or 'Pending'
    $sqlCheckReservation = "
        SELECT COUNT(*) as reservationCount 
        FROM tbl_reservation r
        INNER JOIN tbl_room rm ON r.RoomID = rm.RoomID
        WHERE rm.RoomNumber = ? AND r.ReservationStatus IN ('Confirmed', 'Pending')
    ";
    $stmtCheck = $conn->prepare($sqlCheckReservation);
    $stmtCheck->bindParam(1, $roomNumber);
    $stmtCheck->execute();
    $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($result['reservationCount'] > 0) {
        // If there are reservations, return an error message
        echo json_encode(['success' => false, 'error' => 'Cannot update the room status because it has a reservation with status Confirmed or Pending']);
        exit;
    }

    // If no active reservations, proceed with the update
    $sqlUpdate = "UPDATE tbl_room SET RoomAvailabilityStatus = ? WHERE RoomNumber = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindParam(1, $roomAvailabilityStatus);
    $stmtUpdate->bindParam(2, $roomNumber);
    $stmtUpdate->execute();

    // Check if the update was successful
    if ($stmtUpdate->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Room status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Room status could not be updated']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Close the connection
$conn = null;
?>
