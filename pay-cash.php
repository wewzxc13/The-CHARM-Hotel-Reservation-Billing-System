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
$userId = $json['userId'] ?? null;
$billingId = $json['billingId'] ?? null;
$cashPay = $json['cashPay'] ?? null;
$cashChange = $json['cashChange'] ?? null;

// Validate input
if (empty($reservationId) || empty($userId) || empty($billingId) || empty($cashPay) || empty($cashChange)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

try {
    $conn->beginTransaction(); // Begin a transaction

    // Update tbl_billing
    $updateBilling = $conn->prepare("
        UPDATE tbl_billing
        SET PaymentStatus = 'Paid', 
            PaymentDate = NOW(),
            PaymentMethodID = 2  -- Set PaymentMethodID to 2 for Cash
        WHERE ReservationID = :reservationId
    ");
    $updateBilling->bindParam(':reservationId', $reservationId);
    $updateBilling->execute();

    // Check if the update was successful
    if ($updateBilling->rowCount() > 0) {
        // Check if billingId is valid
        $billingCheck = $conn->prepare("SELECT BillingID FROM tbl_billing WHERE BillingID = :billingId");
        $billingCheck->bindParam(':billingId', $billingId);
        $billingCheck->execute();

        if ($billingCheck->rowCount() > 0) {
            // Generate CashRefNo (you can customize this logic as needed)
            $cashRefNo = 'REF-' . time() . '-' . rand(1000, 9999); // Format: REF-{timestamp}-{random_number}

            // Insert into tbl_onsitepay
            $insertPayment = $conn->prepare("
                INSERT INTO tbl_onsitepay (CashPay, CashChange, BillingID, UserID, PaymentRefNo)
                VALUES (:cashPay, :cashChange, :billingId, :userId, :cashRefNo)
            ");
            $insertPayment->bindParam(':cashPay', $cashPay);
            $insertPayment->bindParam(':cashChange', $cashChange);
            $insertPayment->bindParam(':billingId', $billingId);
            $insertPayment->bindParam(':userId', $userId);
            $insertPayment->bindParam(':cashRefNo', $cashRefNo); // Bind the CashRefNo
            $insertPayment->execute();

            $conn->commit(); // Commit the transaction if all is successful
            echo json_encode(['success' => true, 'message' => 'Payment processed successfully.', 'cashRefNo' => $cashRefNo]);
        } else {
            throw new Exception("Invalid BillingID.");
        }
    } else {
        throw new Exception("Payment could not be processed. Please try again.");
    }
} catch (Exception $e) {
    $conn->rollBack(); // Rollback the transaction on any error
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (PDOException $e) {
    $conn->rollBack(); // Rollback the transaction for any database-related error
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
