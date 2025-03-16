<?php
// Allow CORS for all origins
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

// Include database connection
include "../conn.php";

$json = json_decode(file_get_contents("php://input"), true); // Get the JSON payload from the request

$checkInDate = $json['checkInDate'];
$checkOutDate = $json['checkOutDate'];
$roomNumber = $json['roomId']; // Use roomNumber instead of roomId from the form
$userId = $json['userId'];
$roomTypeId = $json['roomTypeId']; // Assuming room type is also being passed
$selectedAmenities = $json['selectedAmenities']; // Get selected amenities
$selectedRequests = $json['selectedRequests']; // Get selected requests

try {
    // Validate that the CheckInDate is not before today
    $currentDate = date('Y-m-d');
    if ($checkInDate < $currentDate) {
        echo json_encode(["success" => false, "error" => "Check-in date cannot be before today."]);
        exit;
    }

    // Validate that CheckOutDate is not before CheckInDate
    if ($checkOutDate <= $checkInDate) {
        echo json_encode(["success" => false, "error" => "Check-out date must be after check-in date."]);
        exit;
    }

    // Check for overlapping reservations
    $roomIdQuery = "SELECT RoomID FROM tbl_room WHERE RoomNumber = :roomNumber";
    $stmt = $conn->prepare($roomIdQuery);
    $stmt->bindParam(':roomNumber', $roomNumber);
    $stmt->execute();
    $roomData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roomData) {
        echo json_encode(["success" => false, "error" => "Room not found."]);
        exit;
    }
    $roomId = $roomData['RoomID'];

    // Check for existing reservations
    // Updated Check for existing reservations query
    $overlapCheckQuery = "SELECT * FROM tbl_reservation 
    WHERE RoomID = :roomId 
    AND (CheckInDate <= :checkOutDate AND CheckOutDate >= :checkInDate) 
    AND ReservationStatus NOT IN ('Cancelled', 'Completed')";;
    $stmtOverlap = $conn->prepare($overlapCheckQuery);
    $stmtOverlap->bindParam(':roomId', $roomId);
    $stmtOverlap->bindParam(':checkInDate', $checkInDate);
    $stmtOverlap->bindParam(':checkOutDate', $checkOutDate);
    $stmtOverlap->execute();

    if ($stmtOverlap->rowCount() > 0) {
        echo json_encode(["success" => false, "error" => "Room is already booked for the selected dates."]);
        exit;
    }


    // Calculate the number of days
    $checkInDateTime = new DateTime($checkInDate);
    $checkOutDateTime = new DateTime($checkOutDate);
    $interval = $checkInDateTime->diff($checkOutDateTime);
    $numberOfDays = $interval->days;

    // Fetch room price for the selected room type
    $sql = "SELECT RoomPrice FROM tbl_roomtype WHERE RoomTypeID = :roomTypeId";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':roomTypeId', $roomTypeId);
    $stmt->execute();
    $roomPriceData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$roomPriceData) {
        echo json_encode(["success" => false, "error" => "Invalid room type."]);
        exit;
    }
    $roomPrice = $roomPriceData['RoomPrice'];

    // Calculate total price for the room reservation
    $totalAmount = $roomPrice * $numberOfDays;

    // Initialize amenities and requests fee
    $amenitiesTotal = 0.00;
    $requestsTotal = 0.00;

    // Add selected amenities price to total
    if (!empty($selectedAmenities)) {
        $amenitiesIds = implode(',', array_map('intval', $selectedAmenities));
        $sqlAmenities = "SELECT SUM(AmenityPrice) AS TotalAmenityPrice FROM tbl_amenities WHERE AmenityID IN ($amenitiesIds)";
        $stmtAmenities = $conn->prepare($sqlAmenities);
        $stmtAmenities->execute();
        $amenitiesTotal = $stmtAmenities->fetch(PDO::FETCH_ASSOC)['TotalAmenityPrice'] ?? 0;
    }

    // Add selected requests price to total
    if (!empty($selectedRequests)) {
        $requestsIds = implode(',', array_map('intval', $selectedRequests));
        $sqlRequests = "SELECT SUM(RequestPrice) AS TotalRequestPrice FROM tbl_request WHERE RequestID IN ($requestsIds)";
        $stmtRequests = $conn->prepare($sqlRequests);
        $stmtRequests->execute();
        $requestsTotal = $stmtRequests->fetch(PDO::FETCH_ASSOC)['TotalRequestPrice'] ?? 0;
    }

    // Add amenities and requests fees to total amount
    $totalAmount += $amenitiesTotal + $requestsTotal;

    // Insert reservation into the database
    $sqlInsert = "INSERT INTO tbl_reservation (UserID, RoomID, CheckInDate, CheckOutDate, NumberOfDays, ReservationStatus) 
                  VALUES (:userId, :roomId, :checkInDate, :checkOutDate, :numberOfDays, 'Pending')";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bindParam(':userId', $userId);
    $stmtInsert->bindParam(':roomId', $roomId); // Use the fetched room ID here
    $stmtInsert->bindParam(':checkInDate', $checkInDate);
    $stmtInsert->bindParam(':checkOutDate', $checkOutDate);
    $stmtInsert->bindParam(':numberOfDays', $numberOfDays);
    $stmtInsert->execute();

    // Get the last inserted reservation ID
    $reservationId = $conn->lastInsertId();

    // Generate a random 5-digit ReceiptNo
    $receiptNo = rand(10000, 99999);

    // Insert billing details into tbl_billing
    $sqlBilling = "INSERT INTO tbl_billing (ReceiptNo, ReservationID, BillingTotalAmount, PaymentDate, BillingDate, AmenitiesFee, RequestFee) 
                   VALUES (:receiptNo, :reservationId, :totalAmount, '0000-00-00', NOW(), :amenitiesFee, :requestsFee)";
    $stmtBilling = $conn->prepare($sqlBilling);
    $stmtBilling->bindParam(':receiptNo', $receiptNo);
    $stmtBilling->bindParam(':reservationId', $reservationId);
    $stmtBilling->bindParam(':totalAmount', $totalAmount);
    $stmtBilling->bindParam(':amenitiesFee', $amenitiesTotal); // Use calculated amenities fee
    $stmtBilling->bindParam(':requestsFee', $requestsTotal); // Use calculated requests fee
    $stmtBilling->execute();

    // If amenities or requests are selected, insert them into the reservation details
    if (!empty($selectedAmenities)) {
        foreach ($selectedAmenities as $amenityId) {
            $sqlInsertAmenity = "INSERT INTO tbl_reserve_amenities (ReservationID, AmenityID) VALUES (:reservationId, :amenityId)";
            $stmtAmenity = $conn->prepare($sqlInsertAmenity);
            $stmtAmenity->bindParam(':reservationId', $reservationId);
            $stmtAmenity->bindParam(':amenityId', $amenityId);
            $stmtAmenity->execute();
        }
    }

    if (!empty($selectedRequests)) {
        foreach ($selectedRequests as $requestId) {
            $sqlInsertRequest = "INSERT INTO tbl_reserve_request (ReservationID, RequestID) VALUES (:reservationId, :requestId)";
            $stmtRequest = $conn->prepare($sqlInsertRequest);
            $stmtRequest->bindParam(':reservationId', $reservationId);
            $stmtRequest->bindParam(':requestId', $requestId);
            $stmtRequest->execute();
        }
    }

    echo json_encode(["success" => true, "reservationId" => $reservationId, "receiptNo" => $receiptNo]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>