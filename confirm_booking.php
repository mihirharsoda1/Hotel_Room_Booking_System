<?php
// confirm_booking.php
session_start();
require('admin/inc/db_config.php');
require('admin/inc/essentials.php');
require('razorpay-php/Razorpay.php');

use Razorpay\Api\Api;

// Razorpay keys (test keys you used earlier). Replace with live when ready.
$apiKey = "rzp_test_RUuys0IlvCk3ml";
$apiSecret = "twEXHg6z6Bwenx4ivnXc7BHl";

/* AJAX endpoint: create order */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    header('Content-Type: application/json');
    $frm = filteration($_POST);

    if (!isset($_SESSION['room']) || !isset($_SESSION['uId'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Session expired. Please reload.']);
        exit;
    }

    $checkin = $frm['checkin'] ?? '';
    $checkout = $frm['checkout'] ?? '';
    $name = $frm['name'] ?? '';
    $phonenum = $frm['phonenum'] ?? '';
    $address = $frm['address'] ?? '';

    if (!$checkin || !$checkout || !$name || !$phonenum || !$address) {
        echo json_encode(['status' => 'error', 'msg' => 'Missing required fields.']);
        exit;
    }

    try {
        $checkin_date = new DateTime($checkin);
        $checkout_date = new DateTime($checkout);
        $interval = $checkin_date->diff($checkout_date);
        $total_nights = $interval->days;
        if ($total_nights <= 0) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid check-in/out dates.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => 'Invalid dates provided.']);
        exit;
    }

    $room_price = floatval($_SESSION['room']['price']);
    $total_amount = $total_nights * $room_price;

    try {
        $api = new Api($apiKey, $apiSecret);
        $order = $api->order->create([
            'receipt' => 'order_rcptid_' . time(),
            'amount' => intval($total_amount * 100),
            'currency' => 'INR',
            'payment_capture' => 1
        ]);
        $orderId = $order['id'];

        // store payment context in session (will insert to DB only after success or explicit failure report)
        $_SESSION['payment'] = [
            'user_id' => $_SESSION['uId'],
            'room_id' => $_SESSION['room']['id'],
            'checkin' => $checkin,
            'checkout' => $checkout,
            'name' => $name,
            'phonenum' => $phonenum,
            'address' => $address,
            'amount' => $total_amount,
            'order_id' => $orderId
        ];

        echo json_encode([
            'status' => 'ok',
            'order_id' => $orderId,
            'amount' => $total_amount,
            'key' => $apiKey,
            'user_name' => $name,
            'room_name' => $_SESSION['room']['name']
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => 'Razorpay order create failed: ' . $e->getMessage()]);
        exit;
    }
}

/* Render page (GET) */
require('inc/links.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $settings_r['site_title'] ?? 'Site'; ?> - CONFIRM BOOKING</title>
    <style>
        .h-line {
            width: 150px;
            margin: 0 auto;
            height: 1.7px;
        }
    </style>
</head>

<body class="bg-light">
    <?php require('inc/header.php'); ?>

    <?php
    // Guards like your original
    if (!isset($_GET['id']) || ($settings_r['shutdown'] ?? false) == true) {
        redirect('rooms.php');
    } else if (!(isset($_SESSION['login']) && $_SESSION['login'] == true)) {
        redirect('rooms.php');
    }

    $data = filteration($_GET);
    $room_res = select("SELECT * FROM `rooms` WHERE `id`=? AND `status`=? AND `removed`=?", [$data['id'], 1, 0], 'iii');
    if (mysqli_num_rows($room_res) == 0) redirect('rooms.php');
    $room_data = mysqli_fetch_assoc($room_res);
    $_SESSION['room'] = ["id" => $room_data['id'], "name" => $room_data['name'], "price" => $room_data['price'], "payment" => null, "available" => false];
    $user_res = select("SELECT * FROM `user_cred` WHERE `id`=? LIMIT 1", [$_SESSION['uId']], "i");
    $user_data = mysqli_fetch_assoc($user_res);
    ?>

    <div class="container">
        <div class="row">
            <div class="col-12 my-5 mb-4 px-4">
                <h2 class="fw-bold">CONFIRM BOOKING</h2>
                <div style="font-size: 14px;">
                    <a href="index.php" class="text-secondary text-decoration-none">HOME</a>
                    <span class="text-secondary"> > </span>
                    <a href="index.php" class="text-secondary text-decoration-none">CONFIRM BOOKING</a>
                </div>
            </div>

            <div class="col-lg-7 col-md-12 px-4">
                <?php
                $room_thumb = ROOMS_IMG_PATH . "thumbnail.jpg";
                $thumb_q = mysqli_query($con, "SELECT * FROM `room_images` WHERE `room_id`='$room_data[id]' AND `thumb`='1'");
                if (mysqli_num_rows($thumb_q) > 0) {
                    $thumb_res = mysqli_fetch_assoc($thumb_q);
                    $room_thumb = ROOMS_IMG_PATH . $thumb_res['image'];
                }
                echo <<<data
                    <div class="card p-3 shadow-sm rounded">
                        <img src="$room_thumb" class="img-fluid rounded mb-3">
                        <h5>$room_data[name]</h5>
                        <h6>₹$room_data[price] per night</h6>
                    </div>
                data;
                ?>
            </div>

            <div class="col-lg-5 col-md-12 px-4">
                <div class="card mb-4 border-0 shadow-sm rounded-3">
                    <div class="card-body">
                        <form id="booking_form">
                            <h6 class="mb-3">BOOKING DETAILS</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Name</label>
                                    <input name="name" type="text" value="<?php echo $user_data['name']; ?>" class="form-control shadow-none" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input name="phonenum" type="number" value="<?php echo $user_data['phonenum']; ?>" class="form-control shadow-none" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control shadow-none" rows="1" required><?php echo $user_data['address']; ?></textarea>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Check-in</label>
                                    <input name="checkin" onchange="check_availability()" type="date" class="form-control shadow-none" required>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Check-out</label>
                                    <input name="checkout" onchange="check_availability()" type="date" class="form-control shadow-none" required>
                                </div>
                                <div class="col-12">
                                    <div class="spinner-border text-info mb-3 d-none" id="info_loader" role="status"><span class="visually-hidden">Loading...</span></div>
                                    <h6 class="mb-3 text-danger" id="pay_info">Provide check-in & check-out date !</h6>
                                    <button id="pay_now_btn" type="button" class="btn w-100 text-white custom-bg shadow-none mb-1" disabled>Pay Now</button>
                                </div>
                            </div>
                        </form>
                        <div id="debug_msg" class="mt-3 text-muted small d-none"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require('inc/footer.php'); ?>

    <script>
        const bookingForm = document.getElementById('booking_form');
        const infoLoader = document.getElementById('info_loader');
        const payInfo = document.getElementById('pay_info');
        const payNowBtn = document.getElementById('pay_now_btn');
        const debugMsg = document.getElementById('debug_msg');

        function check_availability() {
            let checkin_val = bookingForm.elements['checkin'].value;
            let checkout_val = bookingForm.elements['checkout'].value;
            payNowBtn.setAttribute('disabled', true);
            if (checkin_val !== '' && checkout_val !== '') {
                payInfo.classList.add('d-none');
                payInfo.classList.replace('text-dark', 'text-danger');
                infoLoader.classList.remove('d-none');

                let data = new FormData();
                data.append('check_availability', '');
                data.append('check_in', checkin_val);
                data.append('check_out', checkout_val);

                let xhr = new XMLHttpRequest();
                xhr.open("POST", "ajax/confirm_booking.php", true);
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            let resp = JSON.parse(this.responseText);
                            if (resp.status == 'check_in_out_equal') {
                                payInfo.innerText = "You cannot check-out on the same day!";
                            } else if (resp.status == 'check_out_earlier') {
                                payInfo.innerText = "Check-out date is earlier than check-in date!";
                            } else if (resp.status == 'check_in_earlier') {
                                payInfo.innerText = "Check-in date is earlier than today's date!";
                            } else if (resp.status == 'unavailable') {
                                payInfo.innerText = "Room not available for this check-in date!";
                            } else {
                                payInfo.innerHTML = "No. of Days: " + resp.days + "<br>Total Amount to pay: ₹" + resp.payment;
                                payInfo.classList.replace('text-danger', 'text-dark');
                                payNowBtn.removeAttribute('disabled');
                            }
                        } catch (e) {
                            payInfo.innerText = "Server error while checking availability.";
                        }
                    } else {
                        payInfo.innerText = "Network error.";
                    }
                    payInfo.classList.remove('d-none');
                    infoLoader.classList.add('d-none');
                };
                xhr.send(data);
            }
        }

        // Pay Now -> create order via AJAX then open Razorpay
        payNowBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const name = bookingForm.elements['name'].value.trim();
            const phonenum = bookingForm.elements['phonenum'].value.trim();
            const address = bookingForm.elements['address'].value.trim();
            const checkin = bookingForm.elements['checkin'].value;
            const checkout = bookingForm.elements['checkout'].value;

            if (!name || !phonenum || !address || !checkin || !checkout) {
                alert("Please fill all fields.");
                return;
            }

            payNowBtn.setAttribute('disabled', true);
            debugMsg.classList.remove('d-none');
            debugMsg.innerText = "Creating payment order...";

            let data = new FormData();
            data.append('action', 'create_order');
            data.append('name', name);
            data.append('phonenum', phonenum);
            data.append('address', address);
            data.append('checkin', checkin);
            data.append('checkout', checkout);

            let xhr = new XMLHttpRequest();
            xhr.open("POST", window.location.pathname, true);
            xhr.onload = function() {
                payNowBtn.removeAttribute('disabled');
                if (this.status === 200) {
                    try {
                        let resp = JSON.parse(this.responseText);
                        if (resp.status === 'ok') {
                            window._createdOrderId = resp.order_id;
                            debugMsg.innerText = "Order created. Opening Razorpay...";
                            openRazorpay(resp);
                        } else {
                            debugMsg.innerText = resp.msg || "Order creation failed.";
                            alert(resp.msg || "Order creation failed.");
                        }
                    } catch (err) {
                        debugMsg.innerText = "Invalid server response.";
                        alert("Invalid server response.");
                    }
                } else {
                    debugMsg.innerText = "Network error while creating order.";
                    alert("Network error.");
                }
            };
            xhr.send(data);
        });

        function openRazorpay(data) {
            if (typeof Razorpay === 'undefined') {
                const s = document.createElement('script');
                s.src = "https://checkout.razorpay.com/v1/checkout.js";
                s.onload = function() {
                    launchCheckout(data);
                };
                document.body.appendChild(s);
            } else launchCheckout(data);
        }

        function launchCheckout(data) {
            const options = {
                "key": data.key,
                "amount": parseInt(data.amount * 100),
                "currency": "INR",
                "name": "<?php echo addslashes($settings_r['site_title'] ?? 'ARORA PALACE'); ?>",
                "description": "Room Booking Payment",
                "order_id": data.order_id,
                "handler": function(response) {
                    // On successful payment, submit to verify_payment.php (via POST form)
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'verify_payment.php';

                    const f1 = document.createElement('input');
                    f1.type = 'hidden';
                    f1.name = 'razorpay_payment_id';
                    f1.value = response.razorpay_payment_id;
                    form.appendChild(f1);
                    const f2 = document.createElement('input');
                    f2.type = 'hidden';
                    f2.name = 'razorpay_order_id';
                    f2.value = response.razorpay_order_id;
                    form.appendChild(f2);
                    const f3 = document.createElement('input');
                    f3.type = 'hidden';
                    f3.name = 'razorpay_signature';
                    f3.value = response.razorpay_signature;
                    form.appendChild(f3);

                    document.body.appendChild(form);
                    form.submit();
                },
                "prefill": {
                    "name": data.user_name,
                    "email": "<?php echo ($user_data['email'] ?? 'test@gmail.com'); ?>"
                },
                "theme": {
                    "color": "#3399cc"
                }
            };

            const rzp = new Razorpay(options);

            // Handle payment failure - report to server and then redirect to payment_status.php
            rzp.on('payment.failed', function(response) {
                console.error('Payment failed:', response);

                // friendly user message
                alert("Payment failed: " + (response.error && response.error.description ? response.error.description : "Try again."));

                const sessionOrder = window._createdOrderId || '';

                // Report failed attempt to server
                let fdata = new FormData();
                fdata.append('payment_status', 'failed');
                fdata.append('session_order_id', sessionOrder);
                fdata.append('error_code', response.error && response.error.code ? response.error.code : '');
                fdata.append('error_desc', response.error && response.error.description ? response.error.description : JSON.stringify(response));
                fdata.append('payment_id', (response.error && response.error.metadata && response.error.metadata.payment_id) ? response.error.metadata.payment_id : '');

                fetch('verify_payment.php', {
                        method: 'POST',
                        body: fdata
                    })
                    .then(res => res.text())
                    .then(txt => {
                        // redirect to payment status page to show failure
                        window.location.href = 'pay_status.php?status=failed&order_id=' + encodeURIComponent(sessionOrder);
                    }).catch(err => {
                        // even if logging fails, redirect to failure page
                        window.location.href = 'pay_status.php?status=failed&order_id=' + encodeURIComponent(sessionOrder);
                    });
            });

            rzp.open();
        }
    </script>
</body>

</html>