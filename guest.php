<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit; // End the script for preflight requests
}

include "../conn.php"; // Adjust the path if necessary

class Guest
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

    public function getAllRoomTypes()
    {
        $query = "SELECT RoomTypeID, RoomName, RoomPrice, RoomOccupancy, RoomDescription, RoomImage 
                  FROM tbl_roomtype";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getPaymentMethods()
    {
        $query = "SELECT PaymentMethodID, PaymentName FROM tbl_paymentmethod
                  WHERE PaymentMethodID != 1"; // Exclude PaymentMethodID equal to 1
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        // Fetch payment methods
        $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($paymentMethods);
    }

    public function getRoomsByType($roomTypeId, $checkInDate, $checkOutDate)
    {
        $query = "SELECT RoomNumber, RoomAvailabilityStatus 
                  FROM tbl_room 
                  WHERE RoomTypeID = :roomTypeId AND RoomAvailabilityStatus = 'Available'
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


    public function getReservations($userId)
    {
        $query = "
            SELECT 
                r.ReservationID, 
                u.UserFirstName,
                u.UserLastName,
                u.UserEmail,
                rm.RoomNumber, 
                r.CheckInDate, 
                r.CheckOutDate, 
                r.NumberOfDays, 
                r.ReservationStatus, 
                b.BillingTotalAmount,  
                b.AmenitiesFee, 
                b.RequestFee,
                 rt.RoomPrice,
                b.ReceiptNo,
                 b.PaymentStatus,
                b.PaymentMethodID, 
                pm.PaymentName,
                b.BillingID, 
                (SELECT CONCAT(c.UserFirstName, ' ', c.UserLastName) 
                 FROM tbl_user c 
                 INNER JOIN tbl_userrole ur ON ur.UserRoleID = c.UserRoleID 
                 WHERE c.UserRoleID = 3) AS CashierName
            FROM 
                tbl_reservation r
            INNER JOIN 
                tbl_user u ON u.UserID = r.UserID
            INNER JOIN 
                tbl_billing b ON r.ReservationID = b.ReservationID
            INNER JOIN 
                tbl_paymentmethod pm ON pm.PaymentMethodID = b.PaymentMethodID
            INNER JOIN 
                tbl_room rm ON r.RoomID = rm.RoomID
            INNER JOIN 
                tbl_roomtype rt ON rt.RoomTypeID = rm.RoomTypeID 
            WHERE 
                r.UserID = :userId";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();

        // Fetch all reservations
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare to fetch amenities and requests
        foreach ($reservations as &$reservation) {
            // Fetch amenities
            $reservation['Amenities'] = $this->getAmenitiesByReservation($reservation['ReservationID']);

            // Fetch requests
            $reservation['Requests'] = $this->getRequestsByReservation($reservation['ReservationID']);

            // Fetch payment details
            $reservation['CardPayment'] = $this->getCardPayment($reservation['BillingID']);
            $reservation['GCashPayment'] = $this->getGCashPayment($reservation['BillingID']);
            $reservation['OnsitePayment'] = $this->getOnsitePayment($reservation['BillingID']);

            // Determine Payment Status Color
            $reservation['PaymentStatusColor'] = ($reservation['ReservationStatus'] === 'Unpaid') ? 'red' : 'green';
        }

        return json_encode($reservations);
    }

    private function getAmenitiesByReservation($reservationId)
    {
        $query = "
        SELECT 
            a.AmenityName, 
            a.AmenityPrice 
        FROM 
            tbl_reserve_amenities ra
        INNER JOIN 
            tbl_amenities a ON ra.AmenityID = a.AmenityID
        WHERE 
            ra.ReservationID = :reservationId";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reservationId', $reservationId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRequestsByReservation($reservationId)
    {
        $query = "
        SELECT 
            r.RequestName, 
            r.RequestPrice 
        FROM 
            tbl_reserve_request rr 
        INNER JOIN 
            tbl_request r ON rr.RequestID = r.RequestID 
        WHERE 
            rr.ReservationID = :reservationId";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reservationId', $reservationId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAmenities()
    {
        $query = "SELECT AmenityID, AmenityName, AmenityDescription, AmenityPrice FROM tbl_amenities";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAllRequests()
    {
        $query = "SELECT RequestID, RequestName, RequestDescription, RequestPrice FROM tbl_request";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getCardPayment($billingId)
    {
        $query = "SELECT * FROM tbl_cardpay WHERE BillingID = :billingId";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':billingId', $billingId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getGCashPayment($billingId)
    {
        $query = "SELECT * FROM tbl_gcashpay WHERE BillingID = :billingId";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':billingId', $billingId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getOnsitePayment($billingId)
    {
        $query = "SELECT * FROM tbl_onsitepay WHERE BillingID = :billingId";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':billingId', $billingId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
$guest = new Guest($conn);

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $operation = isset($_GET['operation']) ? $_GET['operation'] : '';

    switch ($operation) {
        case 'getUserData':
            $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
            echo $guest->getUserData($userId);
            break;
        case 'getAllRoomTypes':
            echo $guest->getAllRoomTypes();
            break;
        case 'getPaymentMethods':
            echo $guest->getPaymentMethods();
            break;
        case 'getRoomsByType':
            $roomTypeId = isset($_GET['roomTypeId']) ? $_GET['roomTypeId'] : null;
            $checkInDate = isset($_GET['checkInDate']) ? $_GET['checkInDate'] : null;
            $checkOutDate = isset($_GET['checkOutDate']) ? $_GET['checkOutDate'] : null;
            echo $guest->getRoomsByType($roomTypeId, $checkInDate, $checkOutDate);
            break;
        case 'getReservations':
            $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
            echo $guest->getReservations($userId);
            break;
        case 'getReservationsPrint':
            $userId = isset($_GET['userId']) ? $_GET['userId'] : null;
            echo $guest->getReservations($userId);
            break;
        case 'getAllAmenities':
            echo $guest->getAllAmenities();
            break;
        case 'getAllRequests':
            echo $guest->getAllRequests();
            break;
        default:
            echo json_encode(["message" => "Invalid operation"]);
            break;
    }
}
