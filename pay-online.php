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
$payNumber = $json['payNumber'] ?? null;  // This will be the full number (e.g., 09203914980)
$referenceNumber = $json['referenceNumber'] ?? null;
$userId = $json['userId'] ?? null; // Retrieve UserID from the request

// Validate input
if (empty($reservationId) || empty($paymentMethodId) || empty($payName) || empty($payNumber) || empty($referenceNumber) || empty($userId)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

// Mask the payNumber
$maskedPayNumber = str_repeat('*', strlen($payNumber) - 4) . substr($payNumber, -4);

try {
    $conn->beginTransaction(); // Begin a transaction

    // Update payment status, payment date in tbl_billing
    $sqlUpdate = "UPDATE tbl_billing 
                  SET PaymentStatus = 'Paid', 
                      PaymentDate = CURDATE(),
                      PaymentMethodID = :paymentMethodId
                  WHERE ReservationID = :reservationId";

    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bindParam(':reservationId', $reservationId);
    $stmtUpdate->bindParam(':paymentMethodId', $paymentMethodId);
    $stmtUpdate->execute();

    // Check if the update was successful
    if ($stmtUpdate->rowCount() > 0) {
        // Fetch BillingID
        $sqlSelect = "SELECT BillingID FROM tbl_billing WHERE ReservationID = :reservationId";
        $stmtSelect = $conn->prepare($sqlSelect);
        $stmtSelect->bindParam(':reservationId', $reservationId);
        $stmtSelect->execute();
        $billing = $stmtSelect->fetch(PDO::FETCH_ASSOC);
        $billingId = $billing['BillingID'] ?? null;

        if (!$billingId) {
            throw new Exception("BillingID not found for the provided ReservationID.");
        }

        // Insert into tbl_gcashpay with the masked pay number
        $sqlInsert = "INSERT INTO tbl_gcashpay (PayName, PayNo, PaymentRefNo, BillingID, UserID) 
                      VALUES (:payName, :maskedPayNumber, :referenceNumber, :billingId, :userId)";
        
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bindParam(':payName', $payName);
        $stmtInsert->bindParam(':maskedPayNumber', $maskedPayNumber);  // Use the masked pay number
        $stmtInsert->bindParam(':referenceNumber', $referenceNumber);
        $stmtInsert->bindParam(':billingId', $billingId);
        $stmtInsert->bindParam(':userId', $userId); // Make sure to fetch user ID accordingly
        $stmtInsert->execute();

        $conn->commit(); // Commit the transaction if all is successful
        echo json_encode(["success" => true, "message" => "Payment processed successfully."]);
    } else {
        throw new Exception("Payment could not be processed. Please try again.");
    }
} catch (Exception $e) {
    $conn->rollBack(); // Rollback the transaction on any error
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} catch (PDOException $e) {
    $conn->rollBack(); // Rollback the transaction for any database-related error
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
