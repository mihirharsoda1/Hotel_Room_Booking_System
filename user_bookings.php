<?php
require('admin/inc/db_config.php');
require('admin/inc/essentials.php');
date_default_timezone_set("Asia/Kolkata");

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    redirect('rooms.php');
}

$user_id = $_SESSION['uId'];
$booking_res = select(
    "SELECT bo.*, bd.room_name, bd.price, bd.total_pay FROM booking_order bo INNER JOIN booking_details bd ON bo.id = bd.booking_id WHERE bo.user_id = ? ORDER BY bo.id DESC",
    [$user_id],
    'i'
);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings</title>
    <?php require('inc/links.php'); ?>
</head>

<body class="bg-light">
    <?php require('inc/header.php'); ?>

    <div class="container my-5">
        <h2 class="fw-bold h-font text-center mb-4">My Bookings</h2>

        <?php
        if (mysqli_num_rows($booking_res) > 0) {
            while ($row = mysqli_fetch_assoc($booking_res)) {
                echo <<<HTML
                <div class="card mb-3 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-2">{$row['room_name']}</h5>
                        <p class="card-text mb-1"><strong>Check-in:</strong> {$row['check_in']}</p>
                        <p class="card-text mb-1"><strong>Check-out:</strong> {$row['check_out']}</p>
                        <p class="card-text mb-1"><strong>Amount Paid:</strong> ₹{$row['total_pay']}</p>
                        <p class="card-text mb-1"><strong>Status:</strong> {$row['booking_status']}</p>
                        <p class="card-text mb-0"><strong>Booking Date:</strong> {$row['datentime']}</p>
                    </div>
                </div>
                HTML;
            }
        } else {
            echo "<div class='alert alert-info text-center'>No bookings found.</div>";
        }
        ?>
    </div>

    <?php require('inc/footer.php'); ?>
</body>

</html>