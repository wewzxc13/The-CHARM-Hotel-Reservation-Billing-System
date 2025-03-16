<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

include "../conn.php";

class Admin
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getUserData($userId)
    {
        $query = "SELECT UserID, UserFirstName, UserLastName, UserEmail, UserName, UserPass, UserAddress 
                  FROM tbl_user 
                  WHERE UserID = :userId";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();
        return json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function getAllGuests()
    {
        $query = "SELECT 
  u.UserID,
  u.UserFirstName,
  u.UserLastName,
  u.UserEmail,
  COUNT(r.ReservationID) AS TotalReservations,
  COALESCE(SUM(b.BillingTotalAmount), 0) AS TotalSpent
FROM 
  tbl_user u
LEFT JOIN 
  tbl_reservation r ON u.UserID = r.UserID AND r.ReservationStatus NOT IN ('Cancelled', 'Pending')
LEFT JOIN 
  tbl_billing b ON r.ReservationID = b.ReservationID
WHERE 
  u.UserRoleID = 2
GROUP BY 
  u.UserID
ORDER BY 
  TotalSpent DESC;
";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAllRoomTypes()
    {
        $query = "SELECT RoomTypeID, RoomName, RoomPrice, RoomOccupancy, RoomDescription, RoomImage 
                  FROM tbl_roomtype";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getRoomsByType($roomTypeId)
    {
        $query = "SELECT RoomNumber, RoomAvailabilityStatus 
                  FROM tbl_room 
                  WHERE RoomTypeID = :roomTypeId";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':roomTypeId', $roomTypeId);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getOngoingReservations()
    {
        // Update completed reservations
        $this->updateCompletedReservations();

        // Query for ongoing reservations
        $query = "SELECT r.ReservationID, rm.RoomNumber, r.CheckInDate, r.CheckOutDate, 
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
                  WHERE r.ReservationStatus IN ('Pending', 'Confirmed')
                  GROUP BY r.ReservationID
                  ORDER BY r.CheckInDate ASC";

        return $this->fetchReservations($query);
    }

    public function getArchivedReservations()
    {

        $this->updateCompletedReservations();

        $this->updateBillingStatus();


        $query = "SELECT r.ReservationID, rm.RoomNumber, r.CheckInDate, r.CheckOutDate, 
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
                  WHERE r.ReservationStatus IN ('Cancelled', 'Completed')
                  GROUP BY r.ReservationID
                  ORDER BY r.CheckInDate ASC";

        return $this->fetchReservations($query);
    }

    private function updateBillingStatus()
    {
        try {
            $updateQuery = "UPDATE tbl_billing b
                        INNER JOIN tbl_reservation r ON b.ReservationID = r.ReservationID
                        SET b.PaymentStatus = 'Overdue'
                        WHERE b.PaymentStatus = 'Pending' 
                        AND r.CheckOutDate < CURDATE()";
            $updatedRows = $this->conn->exec($updateQuery);
            error_log("Number of billing statuses updated to Overdue: " . $updatedRows);
        } catch (PDOException $e) {
            error_log('Failed to update billing status: ' . $e->getMessage());
            return json_encode(['error' => 'Failed to update billing status. Please try again.']);
        }
    }

    private function fetchReservations($query)
    {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                return json_encode(['message' => 'No reservations found.']);
            }

            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode($reservations);
        } catch (PDOException $e) {
            error_log('Database query failed: ' . $e->getMessage());
            return json_encode(['error' => 'Failed to retrieve reservations. Please try again later.']);
        }
    }

    private function updateCompletedReservations()
    {
        try {
            $updateQuery = "UPDATE tbl_reservation 
                            SET ReservationStatus = 'Completed' 
                            WHERE CheckOutDate < CURDATE() 
                            AND ReservationStatus NOT IN ('Completed', 'Cancelled')";
            $updatedRows = $this->conn->exec($updateQuery);
            error_log("Number of reservations updated to Completed: " . $updatedRows);
        } catch (PDOException $e) {
            error_log('Failed to update reservations: ' . $e->getMessage());
            return json_encode(['error' => 'Failed to update completed reservations. Please try again.']);
        }
    }

    public function getAllAmenities()
    {
        $query = "SELECT AmenityID, AmenityName, AmenityPrice FROM tbl_amenities";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAllRequests()
    {
        $query = "SELECT RequestID, RequestName, RequestPrice FROM tbl_request";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getDashboard()
    {
        // Fetch all guests with UserRoleID = 2
        $guestsQuery = "SELECT COUNT(*) AS TotalGuests FROM tbl_user WHERE UserRoleID = 2";

        // Fetch available rooms
        $availableRoomsQuery = "SELECT COUNT(RoomNumber) AS TotalAvailableRooms 
                             FROM tbl_room 
                             WHERE RoomAvailabilityStatus = 'Available'";

        // Fetch pending or confirmed reservations
        $pendingConfirmedReservationsQuery = "SELECT COUNT(r.RoomNumber) AS TotalPendingConfirmedReservations 
                                          FROM tbl_reservation rv
                                          INNER JOIN tbl_room r ON r.RoomID = rv.RoomID
                                          WHERE rv.ReservationStatus IN ('Pending', 'Confirmed');";

        // Fetch total revenue
        $revenueQuery = "SELECT COALESCE(SUM(b.BillingTotalAmount), 0) AS TotalRevenue 
        FROM tbl_billing b 
        JOIN tbl_reservation r ON b.ReservationID = r.ReservationID 
        WHERE r.ReservationStatus NOT IN ('Cancelled', 'Pending')";

        // Prepare and execute each query
        $stmtGuests = $this->conn->prepare($guestsQuery);
        $stmtGuests->execute();
        $totalGuests = $stmtGuests->fetch(PDO::FETCH_ASSOC)['TotalGuests'];

        $stmtAvailableRooms = $this->conn->prepare($availableRoomsQuery);
        $stmtAvailableRooms->execute();
        $totalAvailableRooms = $stmtAvailableRooms->fetch(PDO::FETCH_ASSOC)['TotalAvailableRooms'];

        $stmtPendingConfirmed = $this->conn->prepare($pendingConfirmedReservationsQuery);
        $stmtPendingConfirmed->execute();
        $totalPendingConfirmedReservations = $stmtPendingConfirmed->fetch(PDO::FETCH_ASSOC)['TotalPendingConfirmedReservations'];

        $stmtRevenue = $this->conn->prepare($revenueQuery);
        $stmtRevenue->execute();
        $totalRevenue = $stmtRevenue->fetch(PDO::FETCH_ASSOC)['TotalRevenue'];

        error_log("Total Guests: " . $totalGuests);
        error_log("Total TotalAvailableRooms: " . $totalAvailableRooms);
        error_log("Total TotalPendingConfirmedReservations: " . $totalPendingConfirmedReservations);
        error_log("Total TotalRevenue: " . $totalRevenue);

        return json_encode([
            'totalGuests' => $totalGuests,
            'totalAvailableRooms' => $totalAvailableRooms,
            'totalPendingConfirmedReservations' => $totalPendingConfirmedReservations,
            'totalRevenue' => $totalRevenue
        ]);
    }


}

$admin = new Admin($conn);

// Get the operation and parameters from the request
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $operation = $_GET['operation'] ?? '';
    $userId = $_GET['userId'] ?? null;
    $roomTypeId = $_GET['roomTypeId'] ?? null;
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? '';
    $userId = $_POST['userId'] ?? null;
    $roomTypeId = $_POST['roomTypeId'] ?? null;
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Switch case to handle different operations
switch ($operation) {
    case "getUserData":
        if ($userId) {
            echo $admin->getUserData($userId);
        } else {
            echo json_encode(['error' => 'User ID is required']);
        }
        break;
    case "getAllGuests":
        echo $admin->getAllGuests();
        break;
    case "getAllRoomTypes":
        echo $admin->getAllRoomTypes();
        break;
    case "getRoomsByType":
        if ($roomTypeId) {
            echo $admin->getRoomsByType($roomTypeId);
        } else {
            echo json_encode(['error' => 'Room Type ID is required']);
        }
        break;
    case "getOngoingReservations":
        echo $admin->getOngoingReservations();
        break;
    case "getArchivedReservations":
        echo $admin->getArchivedReservations();
        break;
    case "getAllAmenities":
        echo $admin->getAllAmenities();
        break;
    case "getAllRequests":
        echo $admin->getAllRequests();
        break;
    // Assuming you have already included the necessary classes and created an instance of your Admin class
    case "getDashboard":
        echo $admin->getDashboard();
        break;

    default:
        echo json_encode(['error' => 'Invalid operation: ' . $operation]); // Log the invalid operation
        break;
}

?>