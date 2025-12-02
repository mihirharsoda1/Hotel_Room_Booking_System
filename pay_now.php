<?php
session_start();
require('admin/inc/db_config.php');
require('admin/inc/essentials.php');
require('razorpay-php/Razorpay.php');

use Razorpay\Api\Api;

// ✅ Replace with your own test/live key
$apiKey = "rzp_test_RUuys0IlvCk3ml";
$apiSecret = "twEXHg6z6Bwenx4ivnXc7BHl";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now']) && isset($_SESSION['room'])) {
    $frm_data = filteration($_POST);

    $room_id = $_SESSION['room']['id'];
    $user_id = $_SESSION['uId'];
    $checkin = $frm_data['checkin'];
    $checkout = $frm_data['checkout'];
    $name = $frm_data['name'];
    $phonenum = $frm_data['phonenum'];
    $address = $frm_data['address'];

    // Total amount calculation
    $checkin_date = new DateTime($checkin);
    $checkout_date = new DateTime($checkout);
    $interval = $checkin_date->diff($checkout_date);
    $total_nights = $interval->days;
    $room_price = $_SESSION['room']['price'];
    $total_amount = $total_nights * $room_price;

    // ✅ Create Razorpay order
    $api = new Api($apiKey, $apiSecret);
    $order = $api->order->create([
        'receipt' => 'order_rcptid_' . time(),
        'amount' => $total_amount * 100,
        'currency' => 'INR'
    ]);

    $orderId = $order['id'];

    // ✅ Store all payment details in session
    $_SESSION['payment'] = [
        'user_id' => $user_id,
        'room_id' => $room_id,
        'checkin' => $checkin,
        'checkout' => $checkout,
        'name' => $name,
        'phonenum' => $phonenum,
        'address' => $address,
        'amount' => $total_amount,
        'order_id' => $orderId
    ];
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Processing Payment</title>
</head>

<body>
    <!-- Razorpay checkout popup -->
    <form action="verify_payment.php" method="POST">
        <script
            src="https://checkout.razorpay.com/v1/checkout.js"
            data-key="<?php echo $apiKey; ?>"
            data-amount="<?php echo $total_amount * 100; ?>"
            data-currency="INR"
            data-order_id="<?php echo $orderId; ?>"
            data-buttontext="Pay with Razorpay"
            data-name="ARORA PALACE"
            data-description="Room Booking Payment"
            data-prefill.name="<?php echo $name; ?>"
            data-prefill.email="test@gmail.com"
            data-theme.color="#3399cc">
        </script>
        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
    </form>
</body>

</html>