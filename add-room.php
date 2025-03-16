<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Sample validation to ensure request is received
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['json'])) {
        $data = json_decode($_POST['json'], true);

        if (isset($data['RoomNumber']) && isset($data['RoomTypeID'])) {
            $roomNumber = $data['RoomNumber'];
            $roomTypeID = $data['RoomTypeID'];

            // Assuming connection to the database
            include "../conn.php"; // Ensure this path is correct

            // Check if connection was successful
            if (!isset($conn)) {
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }

            // SQL query to insert the new room
            $query = "INSERT INTO tbl_room (RoomNumber, RoomTypeID) VALUES (:roomNumber, :roomTypeID)";
            $stmt = $conn->prepare($query);
            $stmt->execute([':roomNumber' => $roomNumber, ':roomTypeID' => $roomTypeID]);

            // Check if the insertion was successful
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Room added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add room']);
            }

            $conn = null; // Close the connection
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '"json" key not set']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
