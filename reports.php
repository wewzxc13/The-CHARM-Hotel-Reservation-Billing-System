<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

include "../conn.php"; // Assuming your connection setup

class Reports
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    private function executeQuery($query, $params = [])
    {
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$value) {
            $stmt->bindParam($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getDailyRevenueReport($date)
    {
        $query = "SELECT
                    rt.RoomName,
                    COALESCE(SUM(b.BillingTotalAmount), 0.00) AS TotalRevenue,
                    COALESCE(SUM(b.AmenitiesFee + b.RequestFee), 0.00) AS AmenitiesRequestsRevenue
                  FROM
                    tbl_roomtype rt
                  LEFT JOIN tbl_room rm ON rt.RoomTypeID = rm.RoomTypeID
                  LEFT JOIN tbl_reservation r ON rm.RoomID = r.RoomID
                  LEFT JOIN tbl_billing b ON r.ReservationID = b.ReservationID AND b.BillingDate = :billingDate
                  GROUP BY
                    rt.RoomName";
        
        $result = $this->executeQuery($query, [':billingDate' => $date]);
        return ['status' => 'success', 'data' => $result];
    }
    
    public function getOccupancyReport()
    {
        $query = "SELECT
                    rt.RoomName,
                    COUNT(r.ReservationID) AS OccupiedRooms
                  FROM
                    tbl_roomtype rt
                  LEFT JOIN tbl_room rm ON rt.RoomTypeID = rm.RoomTypeID
                  LEFT JOIN tbl_reservation r ON rm.RoomID = r.RoomID AND r.ReservationStatus = 'Confirmed'
                  GROUP BY
                    rt.RoomName";
        
        $result = $this->executeQuery($query);
        return ['status' => 'success', 'data' => $result];
    }

    public function getPerformanceReport()
    {
        $query = "SELECT
                    rt.RoomName,                  
                    SUM(b.BillingTotalAmount) AS TotalRevenue
                  FROM
                    tbl_roomtype rt
                  LEFT JOIN tbl_room rm ON rt.RoomTypeID = rm.RoomTypeID
                  LEFT JOIN tbl_reservation r ON rm.RoomID = r.RoomID 
                  LEFT JOIN tbl_billing b ON r.ReservationID = b.ReservationID
                  WHERE NOT r.ReservationStatus = 'Cancelled'
                  GROUP BY
                    rt.RoomName";
        
        $result = $this->executeQuery($query);
        return ['status' => 'success', 'data' => $result];
    }

 
}

$reports = new Reports($conn);

$operation = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_GET['operation'] ?? '') : ($_POST['operation'] ?? '');

switch ($operation) {
    case 'getDailyRevenueReport':
        $date = htmlspecialchars($_GET['date'] ?? date('Y-m-d')); // Default to today if no date provided
        echo json_encode($reports->getDailyRevenueReport($date));
        break;
    
    case "getOccupancyReport":
        echo json_encode($reports->getOccupancyReport());
        break;

    case "getPerformanceReport":
        echo json_encode($reports->getPerformanceReport());
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid operation']);
        break;
}
?>
