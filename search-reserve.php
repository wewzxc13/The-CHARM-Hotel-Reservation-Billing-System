<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/json');

include "../conn.php";

// Retrieve search key and reservation type from query parameters
$searchKey = isset($_GET['searchKey']) ? $_GET['searchKey'] : '';
$reservationType = isset($_GET['reservationType']) ? $_GET['reservationType'] : 'ongoing';

// Build the SQL query based on reservation type
$statusCondition = ($reservationType === 'ongoing') 
    ? "WHERE r.ReservationStatus IN ('Pending', 'Confirmed')"
    : "WHERE r.ReservationStatus IN ('Cancelled', 'Completed')";

// Append search condition to the status condition
$sql = "SELECT r.ReservationID, rm.RoomNumber, r.CheckInDate, r.CheckOutDate, 
r.NumberOfDays, r.ReservationStatus, b.BillingTotalAmount, 
b.AmenitiesFee, b.RequestFee, b.BillingID, b.PaymentStatus, 
rt.RoomPrice, g.UserLastName, g.UserFirstName, 
GROUP_CONCAT(DISTINCT a.AmenityName ORDER BY a.AmenityName ASC SEPARATOR ', ') AS Amenities,
GROUP_CONCAT(DISTINCT a.AmenityPrice ORDER BY a.AmenityName ASC SEPARATOR ', ') AS AmenityPrices,
GROUP_CONCAT(DISTINCT req.RequestName ORDER BY req.RequestName ASC SEPARATOR ', ') AS Requests,
GROUP_CONCAT(DISTINCT req.RequestPrice ORDER BY req.RequestName ASC SEPARATOR ', ') AS RequestPrices
FROM tbl_reservation r 
INNER JOIN tbl_room rm ON rm.RoomID = r.RoomID
INNER JOIN tbl_billing b ON b.ReservationID = r.ReservationID
INNER JOIN tbl_roomtype rt ON rt.RoomTypeID = rm.RoomTypeID 
LEFT JOIN tbl_reserve_amenities ra ON ra.ReservationID = r.ReservationID
LEFT JOIN tbl_amenities a ON a.AmenityID = ra.AmenityID
LEFT JOIN tbl_reserve_request rr ON rr.ReservationID = r.ReservationID
LEFT JOIN tbl_request req ON req.RequestID = rr.RequestID
LEFT JOIN tbl_user g ON g.UserID = r.UserID
$statusCondition AND (g.UserFirstName LIKE :searchKey OR g.UserLastName LIKE :searchKey)
GROUP BY r.ReservationID
ORDER BY r.CheckInDate ASC";

// Prepare the statement
$stmt = $conn->prepare($sql);

// Wildcard for searching
$searchKeyParam = "%$searchKey%"; 

// Bind parameters
$stmt->bindParam(':searchKey', $searchKeyParam, PDO::PARAM_STR);

// Execute the statement
$stmt->execute();

// Fetch results
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clean up
$stmt = null; // Close the statement
$conn = null; // Close the connection

// Return the results as JSON
echo json_encode($result);
?>
