<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include database connection
include "../conn.php"; // Ensure this file uses PDO

// Retrieve the raw POST data and decode it as JSON
$data = json_decode(file_get_contents('php://input'), true);
$reservationID = $data['reservationID'] ?? ''; // Access the reservationID from the decoded JSON

if ($reservationID) {
    try {
        // Prepare SQL query to update reservation status to 'Cancelled'
        $updateStatusSql = "UPDATE tbl_reservation SET ReservationStatus = 'Cancelled' WHERE ReservationID = :reservationID";
        $stmt = $conn->prepare($updateStatusSql);
        $stmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);

        // Execute the query to update the reservation status
        if ($stmt->execute()) {
            echo json_encode(["success" => true]); // Return success
        } else {
            echo json_encode(["error" => "Error cancelling reservation: " . implode(", ", $stmt->errorInfo())]);
        }
    } catch (Exception $e) {
        echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "No reservationID provided"]);
}

// No need to explicitly close the connection with PDO; it will be closed when the script ends
?>
