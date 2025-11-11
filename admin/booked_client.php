<?php
include "../conn.php";

session_start();

if (!isset($_SESSION["username"])) {
    header("location: login.php");
}

// Retrieve all possible statuses
$statusStmt = $conn->prepare("SELECT id, name FROM statuses");
$statusStmt->execute();
$statusesResult = $statusStmt->get_result();
$statuses = [];
while ($statusRow = $statusesResult->fetch_assoc()) {
    $statuses[] = $statusRow;
}
$statusStmt->close();

$limit = 20; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Modify the query to include the search condition
$searchCondition = $search ? "WHERE bookings.name LIKE CONCAT('%', ?, '%') OR bookings.email LIKE CONCAT('%', ?, '%') OR bookings.phone_number LIKE CONCAT('%', ?, '%')" : '';

// Get total records for pagination
$totalStmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings $searchCondition");
if ($search) {
    $totalStmt->bind_param('sss', $search, $search, $search);
}
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRecords = $totalResult->fetch_assoc()['count'];
$totalStmt->close();
$totalPages = ceil($totalRecords / $limit);

// Get records for the current page
$query = "SELECT bookings.id as booking_id, bookings.name AS full_name, bookings.email, bookings.phone_number, bookings.status_id, statuses.name AS status_name, packages.name AS package_name, packages.price as package_price, payment_statuses.name as payment_status_name, bookings.time_in, bookings.time_out
          FROM bookings 
          INNER JOIN statuses ON bookings.status_id = statuses.id
          INNER JOIN packages ON bookings.package_id = packages.id
          INNER JOIN payment_statuses ON bookings.payment_status_id = payment_statuses.id
          $searchCondition
          LIMIT ?, ?";

$stmt = $conn->prepare($query);

if ($search) {
    $stmt->bind_param('sssii', $search, $search, $search, $offset, $limit);
} else {
    $stmt->bind_param('ii', $offset, $limit);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>J.M. Apilado Resort - Bookings</title>
    <link rel="stylesheet" href="../tailwind.css">
    <link rel="stylesheet" href="../css/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/2.0.8/js/dataTables.js"></script>
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.css" rel="stylesheet">
</head>
<body>
<div class="flex min-h-screen bg-secondary">
    <?php include "../components/admin_navbar.php"; ?>
    <div class="flex-1 p-8 bg-gradient-to-br bg-secondary h-screen" style="padding-left: 300px;">
        <div class="p-5">
            <table id="bookings-table" class="stripe">
                <thead>
                    <tr>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>CONTACT NUMBER</th>
                        <th>PACKAGE</th>
                        <th>STATUS</th>
                        <th>PAYMENT STATUS</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr id="client-row-<?= $row['booking_id'] ?>">
                        <td><?= htmlspecialchars($row["full_name"]) ?></td>
                        <td><?= htmlspecialchars($row["email"]) ?></td>
                        <td><?= htmlspecialchars($row["phone_number"]) ?></td>
                        <td><?= htmlspecialchars($row["package_name"]) ?></td>
                        <td class="client-status"><?= htmlspecialchars($row["status_name"]) ?></td>
                        <td><?= htmlspecialchars($row["payment_status_name"]) ?></td>
                        <td>
                            <?php if ($row["payment_status_name"] == "Paid" && $row["status_name"] !== "Booked"): ?>
                                <button onclick="confirmBooking(<?= $row['booking_id'] ?>)" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Confirm</button>
                            <?php else: ?>
                                <span class="text-gray-500">No Action</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Confirm button click
document.querySelectorAll(".confirm-btn").forEach(button => {
    button.addEventListener("click", function () {
        const bookingId = this.getAttribute("data-id");

        fetch("api/update_status.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                booking_id: bookingId,
                new_status_id: 3 // Example: 3 = Booked
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire("Updated!", "Booking status has been updated.", "success");
                const booking = data.booking;
                const row = document.querySelector(`#booking-${booking.booking_id}`);

                if (row) {
                    row.querySelector(".customer-cell").textContent = booking.full_name;
                    row.querySelector(".package-cell").textContent = booking.package_name;
                    row.querySelector(".status-cell").textContent = booking.status_name;
                    row.querySelector(".payment-cell").textContent = booking.payment_status_name;
                    row.querySelector(".checkin-cell").textContent = booking.start_date;
                    row.querySelector(".checkout-cell").textContent = booking.end_date;
                }
            } else {
                Swal.fire("Error!", data.message, "error");
            }
        })
        .catch(err => Swal.fire("Error!", "Something went wrong.", "error"));
    });
});

// Cancel button click
document.querySelectorAll(".cancel-btn").forEach(button => {
    button.addEventListener("click", function () {
        const bookingId = this.getAttribute("data-id");

        fetch("api/update_status.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                booking_id: bookingId,
                new_status_id: 2 // Example: 2 = Cancelled
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                Swal.fire("Updated!", "Booking has been cancelled.", "success");
                const booking = data.booking;
                const row = document.querySelector(`#booking-${booking.booking_id}`);

                if (row) {
                    row.querySelector(".customer-cell").textContent = booking.full_name;
                    row.querySelector(".package-cell").textContent = booking.package_name;
                    row.querySelector(".status-cell").textContent = booking.status_name;
                    row.querySelector(".payment-cell").textContent = booking.payment_status_name;
                    row.querySelector(".checkin-cell").textContent = booking.start_date;
                    row.querySelector(".checkout-cell").textContent = booking.end_date;
                }
            } else {
                Swal.fire("Error!", data.message, "error");
            }
        })
        .catch(err => Swal.fire("Error!", "Something went wrong.", "error"));
    });
});


new DataTable('#bookings-table');
</script>
</body>
</html>