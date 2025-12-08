<?php
require('admin/inc/essentials.php');
require('admin/inc/db_config.php');
session_start();

if (!(isset($_SESSION['login']) && $_SESSION['login'] === true)) {
    redirect('index.php');
}

if (!(isset($_GET['id']) && isset($_GET['gen_pdf']))) {
    header('location: booking_records.php');
    exit;
}

$frm_data = filteration($_GET);
$id = $frm_data['id'];

$query = "SELECT bo.*, bd.*, bd.room_no AS room_number, uc.email 
FROM `booking_order` bo
INNER JOIN `booking_details` bd ON bo.booking_id = bd.booking_id
INNER JOIN `user_cred` uc ON bo.user_id = uc.id
WHERE (
    (bo.booking_status = 'booked' AND bo.arrival = 1)
    OR (bo.booking_status = 'cancelled' AND bo.refund = 1)
    OR (bo.booking_status = 'failed')
) 
AND bo.booking_id = '$id'";

$res = mysqli_query($con, $query);

if (mysqli_num_rows($res) == 0) {
    header('location: index.php');
    exit;
}

$data = mysqli_fetch_assoc($res);

$date = date("h:ia | d-m-Y", strtotime($data['datentime']));
$checkin = date("d-m-Y", strtotime($data['check_in']));
$checkout = date("d-m-Y", strtotime($data['check_out']));
$room_no = $data['room_number'] ?? 'Not Assigned';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt</title>
    <?php require('inc/links.php'); ?>

    <style>
        body {
            background: #f8f9fa;
        }

        .receipt-wrapper {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 20px 10px;
        }

        .receipt-card {
            background: #fff;
            padding: 25px 25px 10px 25px;
            border-radius: 12px;
            width: 100%;
            max-width: 850px;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.15);
        }

        .receipt-title {
            font-size: 22px;
            font-weight: 600;
            text-align: center;
        }

        .regards-box {
            text-align: center;
            border-top: 1px dashed #aaa;
            padding-top: 12px;
            margin-top: 20px;
        }

        .no-print {
            display: block;
        }

        @media print {

            .no-print,
            .no-print * {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .receipt-card {
                box-shadow: none !important;
            }
        }
    </style>
</head>

<body>

    <?php require('inc/header.php'); ?>

    <!-- ✅ Back Button moved outside the container -->
    <div class="no-print d-flex justify-content-end px-3 mt-3">
        <a href="bookings.php" class="btn btn-dark btn-sm">
            <i class="bi bi-skip-backward-fill"></i> Back
        </a>
    </div>

    <div class="receipt-wrapper">

        <div class="receipt-card" id="receipt-container">

            <h5 class="receipt-title mb-3">BOOKING RECEIPT</h5>
            <hr>

            <div class="row mb-3">
                <div class="col-sm-6"><strong>Order ID:</strong> <?= $data['order_id'] ?></div>
                <div class="col-sm-6"><strong>Booking Date:</strong> <?= $date ?></div>
            </div>

            <div class="row mb-3">
                <div class="col-12"><strong>Status:</strong> <?= ucfirst($data['booking_status']) ?></div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-6"><strong>Name:</strong> <?= $data['user_name'] ?></div>
                <div class="col-sm-6"><strong>Email:</strong> <?= $data['email'] ?></div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-6"><strong>Phone:</strong> <?= $data['phonenum'] ?></div>
                <div class="col-sm-6"><strong>Address:</strong> <?= $data['address'] ?></div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-6"><strong>Room:</strong> <?= $data['room_name'] ?></div>
                <div class="col-sm-6"><strong>Cost per Night:</strong> ₹<?= $data['price'] ?></div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-6"><strong>Check-in:</strong> <?= $checkin ?></div>
                <div class="col-sm-6"><strong>Check-out:</strong> <?= $checkout ?></div>
            </div>

            <?php if ($data['booking_status'] == 'cancelled'): ?>
                <div class="row mb-3">
                    <div class="col-sm-6"><strong>Amount Paid:</strong> ₹<?= $data['trans_amt'] ?></div>
                    <div class="col-sm-6"><strong>Refund:</strong> <?= ($data['refund']) ? 'Refunded' : 'Not Refunded Yet' ?></div>
                </div>
            <?php elseif ($data['booking_status'] == 'failed'): ?>
                <div class="row mb-3">
                    <div class="col-sm-6"><strong>Transaction Amount:</strong> ₹<?= $data['trans_amt'] ?></div>
                    <div class="col-sm-6"><strong>Reason:</strong> <?= $data['trans_resp_msg'] ?></div>
                </div>
            <?php else: ?>
                <div class="row mb-3">
                    <div class="col-sm-6"><strong>Room Number:</strong> <?= $room_no ?></div>
                    <div class="col-sm-6"><strong>Amount Paid:</strong> ₹<?= $data['trans_amt'] ?></div>
                </div>
            <?php endif; ?>

            <div class="regards-box">
                <p>Thank you for choosing <strong>CROWN HOTEL</strong>.<br>
                    We hope you enjoy your stay!</p>
                <strong>Warm Regards,<br>CROWN HOTEL TEAM</strong>
            </div>

            <!-- ✅ Button stays visible on screen but hidden in PDF -->
            <div class="d-flex justify-content-center mt-3 mb-4 no-print">
                <button class="btn btn-dark btn-sm shadow-none download-button" onclick="downloadReceiptPDF()">Download PDF</button>
            </div>

        </div>

    </div>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script>
        async function downloadReceiptPDF() {
            const {
                jsPDF
            } = window.jspdf;
            const receipt = document.getElementById("receipt-container");

            // ✅ Hide download button before capturing
            const downloadBtn = document.querySelector(".download-button");
            downloadBtn.style.display = "none";

            const canvas = await html2canvas(receipt, {
                scale: 3
            });
            const imgData = canvas.toDataURL("image/png", 1.0);

            const pdf = new jsPDF("p", "mm", "a4");
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

            pdf.addImage(imgData, "PNG", 0, 5, pdfWidth, pdfHeight);
            pdf.save("Booking_<?= $data['order_id'] ?>.pdf");

            // ✅ Show button again after PDF generation
            downloadBtn.style.display = "block";
        }
    </script>

</body>

</html>