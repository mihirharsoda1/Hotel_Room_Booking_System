<?php
session_start();
require('admin/inc/db_config.php');
require('admin/inc/essentials.php');
date_default_timezone_set("Asia/Kolkata");

if (!(isset($_SESSION['login']) && $_SESSION['login'] === true)) {
    redirect('index.php');
}

$user_id = $_SESSION['uId'];

// fetch latest booking (remove pending filter)
$booking_q = "SELECT bo.*, bd.* FROM `booking_order` bo 
INNER JOIN `booking_details` bd ON bo.booking_id = bd.booking_id
WHERE bo.user_id = ? ORDER BY bo.booking_id DESC LIMIT 1";

$booking_res = select($booking_q, [$user_id], 'i');

if (mysqli_num_rows($booking_res) == 0) {
    redirect('index.php');
}

$booking = mysqli_fetch_assoc($booking_res);

require('inc/links.php');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment Status</title>
</head>

<body class="bg-light">
    <?php require('inc/header.php'); ?>

    <div class="container">
        <div class="row">
            <div class="col-12 my-5 mb-4 px-4">
                <h2 class="fw-bold">PAYMENT STATUS</h2>
            </div>

            <?php
            if ($booking['booking_status'] === 'booked') {
                echo "
                <div class='col-12 px-4'>
                    <p class='fw-bold alert alert-success'>
                        ✅ Payment Successful! Booking Confirmed.
                        <br><br>
                        <a href='bookings.php'>Go to My Bookings</a>
                    </p>
                </div>";
            } else {
                $err = $booking['trans_resp_msg'] ?? 'Unknown Error';
                echo "
                <div class='col-12 px-4'>
                    <p class='fw-bold alert alert-danger'>
                        ❌ Payment Failed<br>
                        <strong>Reason:</strong><br>
                        <code>$err</code>
                        <br><br>
                        <a href='bookings.php'>Go to My Bookings</a>
                    </p>
                </div>";
            }
            ?>
        </div>
    </div>

    <?php require('inc/footer.php'); ?>
</body>

</html>