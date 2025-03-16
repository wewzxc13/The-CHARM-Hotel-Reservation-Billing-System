<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include database connection
include "../conn.php"; // Adjust path as necessary

// Get the JSON payload from the request
$json = json_decode(file_get_contents("php://input"), true);

// Extract variables from the JSON
$reservationId = $json['reservationId'] ?? null;
$paymentMethodId = $json['paymentMethodId'] ?? null;
$cardHolderName = $json['cardHolderName'] ?? null;
$cardAddress = $json['cardAddress'] ?? null;
$cardNo = $json['cardNo'] ?? null; // This will be the full card number
$expireDate = $json['expireDate'] ?? null;
$cvv = $json['cvv'] ?? null;
$userID = $json['userID'] ?? null;

// Initialize an array to hold the names of empty fields
$emptyFields = [];

// Check which fields are empty and add to the array
if (empty($reservationId)) {
    $emptyFields[] = 'reservationId';
}
if (empty($paymentMethodId)) {
    $emptyFields[] = 'paymentMethodId';
}
if (empty($cardHolderName)) {
    $emptyFields[] = 'cardHolderName';
}
if (empty($cardAddress)) {
    $emptyFields[] = 'cardAddress';
}
if (empty($cardNo)) {
    $emptyFields[] = 'cardNo';
}
if (empty($expireDate)) {
    $emptyFields[] = 'expireDate';
}
if (empty($cvv)) {
    $emptyFields[] = 'cvv';
}
if (empty($userID)) {
    $emptyFields[] = 'userID';
}

// If there are any empty fields, return an error response
if (!empty($emptyFields)) {
    $errorMessage = 'The following fields are required: ' . implode(', ', $emptyFields);
    echo json_encode(['success' => false, 'error' => $errorMessage]);
    exit;
}

// Validate card number format
if (!preg_match('/^\d{4} \d{4} \d{4} \d{4}$/', $cardNo)) {
    echo json_encode(['success' => false, 'error' => 'Invalid card number format. Use: 1234 5678 9012 3456']);
    exit;
}

// Mask the card number
// Mask the card number
// Validate card number format
if (!preg_match('/^\d{4} \d{4} \d{4} \d{4}$/', $cardNo)) {
    echo json_encode(['success' => false, 'error' => 'Invalid card number format. Use: 1234 5678 9012 3456']);
    exit;
}


function maskCardNumber($cardNo) {

    $noSpaces = str_replace(' ', '', $cardNo);
    
    
    $masked = str_repeat('*', strlen($noSpaces) - 4) . substr($noSpaces, -4);
    
   
    $maskedWithSpaces = str_repeat('*', 10) . ' ' . substr($masked, -4); // e.g., **** **** **** 1443

    return $maskedWithSpaces;
}


$maskedCardNo = maskCardNumber($cardNo);  
$maskedCVV = str_repeat('*', strlen($cvv)); 


$cardRefNo = uniqid("CARDREF_", true); 

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

        // Insert into tbl_cardpay including CardRefNo
        $sqlInsert = "INSERT INTO tbl_cardpay (CardHolderName, CardAddress, CardNo, ExpireDate, CVV, BillingID, UserID, PaymentRefNo) 
                      VALUES (:cardHolderName, :cardAddress, :maskedCardNo, :expireDate, :maskedCVV, :billingId, :userID, :cardRefNo)";
        
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->bindParam(':cardHolderName', $cardHolderName);
        $stmtInsert->bindParam(':cardAddress', $cardAddress);
        $stmtInsert->bindParam(':maskedCardNo', $maskedCardNo);  // Use the masked card number
        $stmtInsert->bindParam(':expireDate', $expireDate);
        $stmtInsert->bindParam(':maskedCVV', $maskedCVV);  // Use the masked CVV
        $stmtInsert->bindParam(':billingId', $billingId); // Bind the fetched BillingID
        $stmtInsert->bindParam(':userID', $userID);
        $stmtInsert->bindParam(':cardRefNo', $cardRefNo); // Bind the CardRefNo
        $stmtInsert->execute();

        $conn->commit(); // Commit the transaction if all is successful
        echo json_encode(["success" => true, "message" => "Payment processed successfully."]);
    } else {
        throw new Exception("Payment status could not be updated. Please try again.");
    }
} catch (Exception $e) {
    $conn->rollBack(); // Rollback the transaction on any error
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
} catch (PDOException $e) {
    $conn->rollBack(); // Rollback the transaction for any database-related error
    echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
}
?>
