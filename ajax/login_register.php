<?php

require('../admin/inc/db_config.php');
require('../admin/inc/essentials.php');
require('../inc/sendgrid/sendgrid-php.php');
date_default_timezone_set("Asia/Kolkata");

function send_mail($uemail, $token_or_subject, $type, $name = 'User', $otp_message = '')
{
    $email = new \SendGrid\Mail\Mail();
    $email->setFrom(SENDGRID_EMAIL, SENDGRID_NAME);

    if ($type == "otp") {
        $email->setSubject($token_or_subject);
        $email->addTo($uemail, $name);
        $email->addContent("text/html", $otp_message);
    }
    else {
        if ($type == "email_confirmation") {
            $page = 'email_confirm.php';
            $subject = "Account Verification Link - Arora Palace";
            $content = "verify your account";
        } else {
            $page = 'index.php';
            $subject = "Account Reset Link - Arora Palace";
            $content = "reset your account";
        }

        $email->setSubject($subject);
        $email->addTo($uemail, $name);
        $email->addContent(
            "text/html",
            "
                Click the link to $content : <br>
                <a href='" . SITE_URL . "$page?$type&email=$uemail&token=$token_or_subject" . "'>CLICK ME</a>
            "
        );
    }

    $sendgrid = new \SendGrid(SENDGRID_API_KEY);
    try {
        $sendgrid->send($email);
        return 1;
    } catch (Exception $e) {
        return 0;
    }
}

// register requirment
if (isset($_POST['register'])) {
    $data = filteration($_POST);
    // match password and confirm password field
    if ($data['pass'] != $data['cpass']) {
        echo 'pass_mismatch';
        exit;
    }
    // check user exists or not
    $u_exist = select(
        "SELECT * FROM `user_cred` WHERE `email`=? OR `phonenum`=? LIMIT 1",
        [$data['email'], $data['phonenum']],
        "ss",
    );
    if (mysqli_num_rows($u_exist) != 0) {
        $u_exist_fetch = mysqli_fetch_assoc($u_exist);
        echo ($u_exist_fetch['email'] == $data['email']) ? 'email_already' : 'phone_already';
        exit;
    }
    // upload user image to server
    $img = uploadUserImage($_FILES['profile']);
    if ($img == 'inv_img') {
        echo 'inv_img';
        exit;
    } elseif ($img == 'upd_failed') {
        echo 'upd_failed';
        exit;
    }
    //send confirmation link to user's email
    $token = bin2hex(random_bytes(16));
    if (!send_mail($data['email'], $token, "email_confirmation", $data['name'])) {
        echo 'mail_failed';
        exit;
    }
    $enc_pass = password_hash($data['pass'], PASSWORD_BCRYPT);
    $query = "INSERT INTO `user_cred`(`name`, `email`, `address`, `phonenum`, `pincode`, `dob`, `profile`, `password`, `token`) VALUES (?,?,?,?,?,?,?,?,?)";
    $values = [$data['name'], $data['email'], $data['address'], $data['phonenum'], $data['pincode'], $data['dob'], $img, $enc_pass, $token];
    if (insert($query, $values, 'sssssssss')) {
        echo 1;
        exit;
    } else {
        echo 'ins_failed';
    }
}

// login requirment
if (isset($_POST['login'])) {
    $data = filteration($_POST);
    $u_exist = select(
        "SELECT * FROM `user_cred` WHERE `email`=? OR `phonenum`=? LIMIT 1",
        [$data['email_mob'], $data['email_mob']],
        "ss",
    );
    if (mysqli_num_rows($u_exist) == 0) {
        echo 'inv_email_mob';
        exit;
    } else {
        $u_fetch = mysqli_fetch_assoc($u_exist);
        if ($u_fetch['is_verified'] == 0) {
            echo 'not_verified';
        } else if ($u_fetch['status'] == 0) {
            echo 'inactive';
        } else {
            if (!password_verify($data['pass'], $u_fetch['password'])) {
                echo 'invalid_pass';
            } else {
                session_start();
                $_SESSION['login'] = true;
                $_SESSION['uId'] = $u_fetch['id'];
                $_SESSION['uName'] = $u_fetch['name'];
                $_SESSION['uPic'] = $u_fetch['profile'];
                $_SESSION['uPhone'] = $u_fetch['phonenum'];
                echo 1;
            }
        }
    }
}

if (isset($_POST['forgot_pass'])) {
    $data = filteration($_POST);
    $email = $data['email'];

    // Check if email exists
    $query = select("SELECT * FROM `user_cred` WHERE `email`=? LIMIT 1", [$email], 's');
    if (mysqli_num_rows($query) == 0) {
        echo "inv_email";  // ⚠️ same response text used in JS
        exit;
    }

    $u_fetch = mysqli_fetch_assoc($query);
    if ($u_fetch['is_verified'] == 0) {
        echo 'not_verified';
        exit;
    } else if ($u_fetch['status'] == 0) {
        echo 'inactive';
        exit;
    }

    // Generate 6 digit OTP
    $otp = rand(100000, 999999);

    // Save OTP in database (optional: with expiry)
    $update = update("UPDATE `user_cred` SET `otp`=? WHERE `email`=?", [$otp, $email], 'ss');

    // Send Email with OTP
    $subject = "Your OTP Code - Arora Palace";
    $message = "Your OTP for password reset is: <b>$otp</b>";
    $mail_status = send_mail($email, $subject, "otp", $u_fetch['name'], $message);

    if ($update && $mail_status) {
        echo "otp_sent";  // ⚠️ matches your JS check
    } else {
        echo "mail_failed";
    }
}

if (isset($_POST['recover_user'])) {
    $data = filteration($_POST);
    $enc_pass = password_hash($data['pass'], PASSWORD_BCRYPT);

    $check = select("SELECT * FROM `user_cred` WHERE `email`=? AND `otp`=? LIMIT 1", [$data['email'], $data['otp']], 'ss');

    if (mysqli_num_rows($check) == 0) {
        echo 'inv_otp';
    } else {
        $upd = update("UPDATE `user_cred` SET `password`=?, `otp`=NULL WHERE `email`=?", [$enc_pass, $data['email']], 'ss');
        echo $upd ? 1 : 'failed';
    }
}
