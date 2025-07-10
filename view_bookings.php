<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT b.booking_id, v.vehicle_name, b.booking_date, b.start_date, b.end_date, b.status
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.vehicle_id
        WHERE b.user_id = '$user_id'";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Bookings</title>
    <style>
        body { font-family: Arial; background: #f2f2f2; }
        table { width: 90%; margin: 20px auto; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
        th { background-color: #333; color: white; }
    </style>
</head>
<body>

<h2 style="text-align:center;">My Bookings</h2>

<table>
    <tr>
        <th>Booking ID</th>
        <th>Vehicle</th>
        <th>Booking Date</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Status</th>
    </tr>

    <?php
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>".$row['booking_id']."</td>";
            echo "<td>".$row['vehicle_name']."</td>";
            echo "<td>".$row['booking_date']."</td>";
            echo "<td>".$row['start_date']."</td>";
            echo "<td>".$row['end_date']."</td>";
            echo "<td>".$row['status']."</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='6'>No bookings found</td></tr>";
    }
    ?>
</table>

</body>
</html>
