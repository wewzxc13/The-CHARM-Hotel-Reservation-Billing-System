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
        // Start a transaction
        $conn->beginTransaction();

        // Prepare SQL queries to delete records from payment tables before deleting billing records
        $deleteOnsitePaySql = "DELETE FROM tbl_onsitepay WHERE BillingID IN (SELECT BillingID FROM tbl_billing WHERE ReservationID = :reservationID)";
        $onsitePayStmt = $conn->prepare($deleteOnsitePaySql);
        $onsitePayStmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);
        $onsitePayStmt->execute();

        $deleteGcashPaySql = "DELETE FROM tbl_gcashpay WHERE BillingID IN (SELECT BillingID FROM tbl_billing WHERE ReservationID = :reservationID)";
        $gcashPayStmt = $conn->prepare($deleteGcashPaySql);
        $gcashPayStmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);
        $gcashPayStmt->execute();

        $deleteCardPaySql = "DELETE FROM tbl_cardpay WHERE BillingID IN (SELECT BillingID FROM tbl_billing WHERE ReservationID = :reservationID)";
        $cardPayStmt = $conn->prepare($deleteCardPaySql);
        $cardPayStmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);
        $cardPayStmt->execute();

        // Delete related billing records
        $deleteBillingSql = "DELETE FROM tbl_billing WHERE ReservationID = :reservationID";
        $billingStmt = $conn->prepare($deleteBillingSql);
        $billingStmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);
        $billingStmt->execute();

        // Delete related reserve amenities records
        $deleteAmenitiesSql = "DELETE FROM tbl_reserve_amenities WHERE ReservationID = :reservationID";
        $amenitiesStmt = $conn->prepare($deleteAmenitiesSql);
        $amenitiesStmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);
        $amenitiesStmt->execute();

        // Delete related reserve request records
        $deleteRequestSql = "DELETE FROM tbl_reserve_request WHERE ReservationID = :reservationID";
        $requestStmt = $conn->prepare($deleteRequestSql);
        $requestStmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);
        $requestStmt->execute();

        // Finally, delete the reservation record
        $deleteReservationSql = "DELETE FROM tbl_reservation WHERE ReservationID = :reservationID";
        $reservationStmt = $conn->prepare($deleteReservationSql);
        $reservationStmt->bindParam(':reservationID', $reservationID, PDO::PARAM_INT);

        // Execute the query to delete the reservation
        if ($reservationStmt->execute()) {
            // Commit the transaction
            $conn->commit();
            echo json_encode(["success" => true]); // Return success
        } else {
            echo json_encode(["error" => "Error deleting reservation: " . implode(", ", $reservationStmt->errorInfo())]);
        }
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollBack();
        echo json_encode(["error" => "An error occurred: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "No reservationID provided"]);
}
