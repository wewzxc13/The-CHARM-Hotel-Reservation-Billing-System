<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include database connection
include "../conn.php";

// Get the JSON payload from the request
$json = json_decode(file_get_contents("php://input"), true);

// Extract the variables from the JSON
$reservationId = $json['reservationId'] ?? null;
$paymentMethodId = $json['paymentMethodId'] ?? null;
$payName = $json['payName'] ?? null;
$payNumber = $json['payNumber'] ?? null;
$referenceNumber = $json['referenceNumber'] ?? null;

// Validate input
if (empty($reservationId) || empty($paymentMethodId)) {
    echo json_encode(['success' => false, 'error' => 'Reservation ID and Payment Method ID are required.']);
    exit;
}

try {
    $conn->beginTransaction(); // Begin a transaction

    // Fetch the current billing information based on the reservation ID
    $sqlFetch = "SELECT BillingID, PaymentStatus FROM tbl_billing WHERE ReservationID = :reservationId";
    $stmtFetch = $conn->prepare($sqlFetch);
    $stmtFetch->bindParam(':reservationId', $reservationId);
    $stmtFetch->execute();
    $billingData = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if (!$billingData) {
        throw new Exception("No billing information found for the given reservation ID.");
    }

    // // Check if payment is already made
    // if ($billingData['PaymentStatus'] === 'Paid') {
    //     throw new Exception("This reservation has already been paid.");
    // }

    // Prepare to update payment status
    $paymentStatus = 'Paid'; // For Pay Now
    $paymentDate = 'NOW()'; // Update with current date

    // Update payment status and payment method in tbl_billing
    $sqlUpdate = "UPDATE tbl_billing 
                  SET PaymentStatus = :paymentStatus, 
                      PaymentDate = $paymentDate, 
                      PaymentMethodID = :paymentMethodId
                  WHERE ReservationID = :reservationId";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindParam(':reservationId', $reservationId);
    $stmtUpdate->bindParam(':paymentStatus', $paymentStatus);
    $stmtUpdate->bindParam(':paymentMethodId', $paymentMethodId);
    $stmtUpdate->execute();

    // Insert online payment details if provided
    if ($payName && $payNumber && $referenceNumber) {
        $billingId = $billingData['BillingID']; // Get the BillingID from the previous query
        $sqlInsertOnlinePay = "INSERT INTO tbl_onlinepay (PayName, PayNo, PaymentRefNo, BillingID, UserID)
                                VALUES (:payName, :payNumber, :referenceNumber, :billingId, :userId)";

        $stmtInsert = $conn->prepare($sqlInsertOnlinePay);
        $stmtInsert->bindParam(':payName', $payName);
        $stmtInsert->bindParam(':payNumber', $payNumber);
        $stmtInsert->bindParam(':referenceNumber', $referenceNumber);
        $stmtInsert->bindParam(':billingId', $billingId);
        $stmtInsert->bindParam(':userId', $userId); // Assuming you have UserID available
        $stmtInsert->execute();
    }

    $conn->commit(); // Commit the transaction if all is successful
    echo json_encode(["success" => true, "message" => "Payment processed successfully."]);
} catch (Exception $e) {
    $conn->rollBack(); // Rollback the transaction on any error
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} catch (PDOException $e) {
    $conn->rollBack(); // Rollback the transaction for any database-related error
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
