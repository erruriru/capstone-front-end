<?php
include "../conn.php"; // ✅ add this to connect to DB

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Decode JSON POST request
    $_POST = json_decode(file_get_contents("php://input"), true);

    $bookingId = $_POST["booking_id"] ?? null;
    $newStatusId = $_POST["new_status_id"] ?? null;

    if (!$bookingId || !$newStatusId) {
        echo json_encode(["success" => false, "message" => "Missing booking_id or new_status_id"]);
        exit;
    }

    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $newStatusId, $bookingId);

    if ($stmt->execute()) {
        // Fetch updated booking with joined data
        $query = "SELECT 
                    b.id as booking_id, 
                    b.name AS full_name, 
                    b.email, 
                    b.phone_number, 
                    b.status_id, 
                    s.name AS status_name, 
                    p.name AS package_name, 
                    ps.name AS payment_status_name,
                    b.start_date, 
                    b.end_date
                  FROM bookings b
                  INNER JOIN statuses s ON b.status_id = s.id
                  INNER JOIN packages p ON b.package_id = p.id
                  INNER JOIN payment_statuses ps ON b.payment_status_id = ps.id
                  WHERE b.id = ?";
        $fetchStmt = $conn->prepare($query);
        $fetchStmt->bind_param("i", $bookingId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedBooking = $result->fetch_assoc();

        echo json_encode([
            "success" => true,
            "message" => "Status updated successfully",
            "booking" => $updatedBooking
        ]);

        $fetchStmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update status"]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
}
