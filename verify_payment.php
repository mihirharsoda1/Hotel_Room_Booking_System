<?php
session_start();
require('admin/inc/db_config.php');
require('admin/inc/essentials.php');
require('razorpay-php/Razorpay.php');
date_default_timezone_set("Asia/Kolkata");

use Razorpay\Api\Api;

$apiKey = "rzp_test_RUuys0IlvCk3ml";
$apiSecret = "twEXHg6z6Bwenx4ivnXc7BHl";

$api = new Api($apiKey, $apiSecret);


/* ------------------ HANDLE FAILED PAYMENT (AJAX) ------------------ */
if (isset($_POST['payment_status']) && $_POST['payment_status'] === 'failed') {

    if (!isset($_SESSION['payment'])) {
        http_response_code(400);
        echo "No payment session found.";
        exit;
    }

    $data = $_SESSION['payment'];

    $user_id = $data['user_id'];
    $room_id = $data['room_id'];
    $checkin = $data['checkin'];
    $checkout = $data['checkout'];
    $amount = $data['amount'];
    $orderId = $data['order_id'];

    $error_code = $_POST['error_code'] ?? '';
    $error_desc = $_POST['error_desc'] ?? 'Payment failed (no details)';
    $payment_id = $_POST['payment_id'] ?? '';

    // Store full technical error for debugging
    $err_full_msg = "CODE: $error_code | DESC: $error_desc";

    $q1 = "INSERT INTO booking_order 
        (`user_id`, `room_id`, `check_in`, `check_out`, `booking_status`, `order_id`, `trans_id`, `trans_amt`, `trans_status`, `trans_resp_msg`) 
        VALUES (?, ?, ?, ?, 'failed', ?, ?, ?, 'failed', ?)";

    $v1 = [$user_id, $room_id, $checkin, $checkout, $orderId, $payment_id, $amount, $err_full_msg];
    $res1 = insert($q1, $v1, 'iisssiss');

    if ($res1) {
        $booking_id = mysqli_insert_id($con);

        $q2 = "INSERT INTO booking_details 
        (`booking_id`, `room_name`, `price`, `total_pay`, `user_name`, `phonenum`, `address`) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

        $v2 = [$booking_id, $_SESSION['room']['name'], $_SESSION['room']['price'], $amount, $data['name'], $data['phonenum'], $data['address']];
        insert($q2, $v2, 'isiisss');

        // Keep session so user can retry payment
        echo "failed_logged";
        exit;
    }

    http_response_code(500);
    echo "DB insert failed.";
    exit;
}


/* ------------------ SUCCESS PAYMENT ------------------ */
if (isset($_POST['razorpay_payment_id']) && isset($_SESSION['payment'])) {

    $attributes = [
        'razorpay_order_id' => $_POST['razorpay_order_id'],
        'razorpay_payment_id' => $_POST['razorpay_payment_id'],
        'razorpay_signature' => $_POST['razorpay_signature']
    ];

    try {
        $api->utility->verifyPaymentSignature($attributes);

        $data = $_SESSION['payment'];

        $q1 = "INSERT INTO booking_order 
        (`user_id`, `room_id`, `check_in`, `check_out`, `booking_status`, `order_id`, `trans_id`, `trans_amt`, `trans_status`, `trans_resp_msg`) 
        VALUES (?, ?, ?, ?, 'booked', ?, ?, ?, 'success', 'Payment successful')";

        $v1 = [$data['user_id'], $data['room_id'], $data['checkin'], $data['checkout'], $data['order_id'], $_POST['razorpay_payment_id'], $data['amount']];
        $res1 = insert($q1, $v1, 'iisssis');

        if ($res1) {
            $booking_id = mysqli_insert_id($con);

            $q2 = "INSERT INTO booking_details 
            (`booking_id`, `room_name`, `price`, `total_pay`, `user_name`, `phonenum`, `address`) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

            $v2 = [$booking_id, $_SESSION['room']['name'], $_SESSION['room']['price'], $data['amount'], $data['name'], $data['phonenum'], $data['address']];
            insert($q2, $v2, 'isiisss');

            unset($_SESSION['room'], $_SESSION['payment']);

            echo "<script>window.location.href='pay_status.php?status=success&booking_id=$booking_id';</script>";
            exit;
        }

        echo "<script>alert('Booking DB insert failed.'); window.location.href='rooms.php';</script>";
        exit;
    } catch (Exception $e) {

        $msg = "SIGNATURE FAIL: " . $e->getMessage();

        if (isset($_SESSION['payment'])) {
            $data = $_SESSION['payment'];

            $q1 = "INSERT INTO booking_order 
            (`user_id`, `room_id`, `check_in`, `check_out`, `booking_status`, `order_id`, `trans_id`, `trans_amt`, `trans_status`, `trans_resp_msg`) 
            VALUES (?, ?, ?, ?, 'failed', ?, NULL, ?, 'failed', ?)";

            $v1 = [$data['user_id'], $data['room_id'], $data['checkin'], $data['checkout'], $data['order_id'], $data['amount'], $msg];
            insert($q1, $v1, 'iisssis');
        }

        echo "<script>window.location.href='pay_status.php?status=failed';</script>";
        exit;
    }
}

redirect('rooms.php');
exit;
