<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

include "../conn.php";

class Staff
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
        $query = "SELECT UserFirstName, UserLastName, UserEmail, UserName, UserAddress 
                  FROM tbl_user";
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


    public function getRoomsByType($roomTypeId, $checkInDate, $checkOutDate)
    {
        $query = "SELECT RoomNumber, RoomAvailabilityStatus 
                  FROM tbl_room 
                  WHERE RoomTypeID = :roomTypeId
                  AND RoomID NOT IN (
                      SELECT RoomID FROM tbl_reservation 
                      WHERE (CheckInDate <= :checkOutDate AND CheckOutDate >= :checkInDate)
                  )";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':roomTypeId', $roomTypeId);
        $stmt->bindParam(':checkInDate', $checkInDate);
        $stmt->bindParam(':checkOutDate', $checkOutDate);
        $stmt->execute();

        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    public function getAllReservations()
    {
        $query = "SELECT r.ReservationID, rm.RoomNumber, r.CheckInDate, r.CheckOutDate, 
                             r.NumberOfDays, r.ReservationStatus, b.BillingTotalAmount, 
                             b.AmenitiesFee, b.RequestFee, b.BillingID, b.PaymentStatus, rt.RoomPrice,
                             g.UserLastName, g.UserFirstName,  /* Selecting Guest's First and Last Name */
                             GROUP_CONCAT(DISTINCT a.AmenityName SEPARATOR ', ') AS Amenities,
                             GROUP_CONCAT(DISTINCT a.AmenityPrice SEPARATOR ', ') AS AmenityPrices,
                             GROUP_CONCAT(DISTINCT req.RequestName SEPARATOR ', ') AS Requests,
                             GROUP_CONCAT(DISTINCT req.RequestPrice SEPARATOR ', ') AS RequestPrices
                      FROM tbl_reservation r 
                      INNER JOIN tbl_room rm ON rm.RoomID = r.RoomID
                      INNER JOIN tbl_billing b ON b.ReservationID = r.ReservationID
                      INNER JOIN tbl_roomtype rt ON rt.RoomTypeID = rm.RoomTypeID 
                      LEFT JOIN tbl_reserve_amenities ra ON ra.ReservationID = r.ReservationID
                      LEFT JOIN tbl_amenities a ON a.AmenityID = ra.AmenityID
                      LEFT JOIN tbl_reserve_request rr ON rr.ReservationID = r.ReservationID
                      LEFT JOIN tbl_request req ON req.RequestID = rr.RequestID
                      LEFT JOIN tbl_user g ON g.UserID = r.UserID /* Joining tbl_user to get Guest Name */
                      GROUP BY r.ReservationID
                      ORDER BY r.ReservationStatus DESC, r.CheckInDate ASC";  // Grouping by ReservationID to aggregate amenities/requests
                      
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    
}

$staff = new Staff($conn);

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $operation = $_GET['operation'] ?? '';
    $userId = $_GET['userId'] ?? null;
    $roomTypeId = $_GET['roomTypeId'] ?? null;
    $roomNumber = $_GET['roomNumber'] ?? null; // Get roomNumber if it's a GET request
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $operation = $_POST['operation'] ?? '';
    $userId = $_POST['userId'] ?? null;
    $roomTypeId = $_POST['roomTypeId'] ?? null;
    $roomNumber = $_POST['roomNumber'] ?? null;
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

switch ($operation) {
    case "getUserData":
        if ($userId) {
            echo $staff->getUserData($userId);
        } else {
            echo json_encode(['error' => 'User ID is required']);
        }
        break;
    case "getAllGuests":
        echo $staff->getAllGuests();
        break;
  
    case "getAllReservations":
        echo $staff->getAllReservations();
        break;
        case 'getAllRoomTypes':
            echo $staff->getAllRoomTypes();
            break;
            case 'getAllAmenities':
                echo $staff->getAllAmenities();
                break;
            case 'getAllRequests':
                echo $staff->getAllRequests();
                break;   
      
        case 'getRoomsByType':
            $roomTypeId = isset($_GET['roomTypeId']) ? $_GET['roomTypeId'] : null;
            $checkInDate = isset($_GET['checkInDate']) ? $_GET['checkInDate'] : null;
            $checkOutDate = isset($_GET['checkOutDate']) ? $_GET['checkOutDate'] : null;
            echo $staff->getRoomsByType($roomTypeId, $checkInDate, $checkOutDate);
            break;
    default:
        echo json_encode(['error' => 'Invalid operation']);
        break;
}
?>