<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include the database connection
include "../conn.php"; // Adjust the path as necessary

// Get the JSON payload from the request
$json = json_decode(file_get_contents("php://input"), true);

// Extract the reservation ID and new status from the JSON
$reservationID = $json['reservationID'] ?? null;
$status = $json['status'] ?? null;

// Validate input
if (empty($reservationID) || empty($status)) {
    echo json_encode(['success' => false, 'error' => 'Reservation ID and status are required']);
    exit;
}

try {
    // Update the reservation status
    $sqlUpdate = "UPDATE tbl_reservation SET ReservationStatus = ? WHERE ReservationID = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindParam(1, $status);
    $stmtUpdate->bindParam(2, $reservationID);
    $stmtUpdate->execute();

    // Check if the update was successful
    if ($stmtUpdate->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Reservation status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No rows updated. Check if the reservation ID is valid.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    // Close the connection (optional, depending on your setup)
    $conn = null;
}
?>
