<?php

// Frontend purpose data
if (!defined('SITE_URL'))
    define('SITE_URL', 'http://localhost/website/');
if (!defined('ABOUT_IMG_PATH'))
    define('ABOUT_IMG_PATH', SITE_URL . 'images/about/');
if (!defined('CAROUSEL_IMG_PATH'))
    define('CAROUSEL_IMG_PATH', SITE_URL . 'images/carousel/');
if (!defined('FACILITIES_IMG_PATH'))
    define('FACILITIES_IMG_PATH', SITE_URL . 'images/facilities/');
if (!defined('ROOMS_IMG_PATH'))
    define('ROOMS_IMG_PATH', SITE_URL . 'images/rooms/');
if (!defined('USERS_IMG_PATH'))
    define('USERS_IMG_PATH', SITE_URL . 'images/users/');

// Backend upload process
if (!defined('UPLOAD_IMAGE_PATH'))
    define('UPLOAD_IMAGE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/website/images/');
if (!defined('ABOUT_FOLDER'))
    define('ABOUT_FOLDER', 'about/');
if (!defined('CAROUSEL_FOLDER'))
    define('CAROUSEL_FOLDER', 'carousel/');
if (!defined('FACILITIES_FOLDER'))
    define('FACILITIES_FOLDER', 'facilities/');
if (!defined('ROOMS_FOLDER'))
    define('ROOMS_FOLDER', 'rooms/');
if (!defined('USERS_FOLDER'))
    define('USERS_FOLDER', 'users/');

// Send mail
if (!defined('SENDGRID_API_KEY'))
    define('SENDGRID_API_KEY', "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");
if (!defined('SENDGRID_EMAIL'))
    define('SENDGRID_EMAIL', "iammihirpatelofficial@gmail.com");
if (!defined('SENDGRID_NAME'))
    define('SENDGRID_NAME', "Arora Palace");

if (!function_exists('adminLogin')) {
    // function adminLogin()
    // {
    //     session_start();
    //     if (!(isset($_SESSION['adminLogin']) && $_SESSION['adminLogin'] == true)) {
    //         echo "<script>window.location.href='index.php';</script>";
    //         exit;
    //     }
    // }
    function adminLogin()
    {
        // ensure session started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!(isset($_SESSION['adminLogin']) && $_SESSION['adminLogin'] === true)) {

            // Detect AJAX request (standard X-Requested-With header)
            $isAjax = false;
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                $isAjax = true;
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'logout']);
                exit;
            }

            // normal page -> redirect using JS to avoid sending headers if already output
            echo "<script>window.location.href='index.php';</script>";
            exit;
        }
    }
}

if (!function_exists('redirect')) {
    function redirect($url)
    {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

if (!function_exists('alert')) {
    function alert($type, $msg)
    {
        $bs_class = ($type == "success") ? "alert-success" : "alert-danger";
        echo <<<alert
            <div class="alert $bs_class alert-dismissible fade show custom-alert" role="alert">
                <strong class="me-3">$msg</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        alert;
    }
}

if (!function_exists('uploadImage')) {
    function uploadImage($image, $folder)
    {
        $valid_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $img_mime = $image['type'];
        if (!in_array($img_mime, $valid_mime))
            return 'inv_img';
        if (($image['size'] / (1024 * 1024)) > 2)
            return 'inv_size';

        $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
        $rname = 'ING_' . random_int(11111, 99999) . ".$ext";
        $img_path = UPLOAD_IMAGE_PATH . $folder . $rname;

        return move_uploaded_file($image['tmp_name'], $img_path) ? $rname : 'upd_failed';
    }
}

if (!function_exists('deleteImage')) {
    function deleteImage($image, $folder)
    {
        return unlink(UPLOAD_IMAGE_PATH . $folder . $image);
    }
}

if (!function_exists('uploadSVGImage')) {
    function uploadSVGImage($image, $folder)
    {
        $valid_mime = ['image/svg+xml'];
        $img_mime = $image['type'];
        if (!in_array($img_mime, $valid_mime))
            return 'inv_img';
        if (($image['size'] / (1024 * 1024)) > 1)
            return 'inv_size';

        $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
        $rname = 'ING_' . random_int(11111, 99999) . ".$ext";
        $img_path = UPLOAD_IMAGE_PATH . $folder . $rname;

        return move_uploaded_file($image['tmp_name'], $img_path) ? $rname : 'upd_failed';
    }
}

if (!function_exists('uploadUserImage')) {
    function uploadUserImage($image)
    {
        $valid_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $img_mime = $image['type'];
        if (!in_array($img_mime, $valid_mime))
            return 'inv_img';

        $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
        $rname = 'ING_' . random_int(11111, 99999) . ".jpeg";
        $img_path = UPLOAD_IMAGE_PATH . USERS_FOLDER . $rname;

        switch (strtolower($ext)) {
            case 'png':
                $img = imagecreatefrompng($image['tmp_name']);
                break;
            case 'webp':
                $img = imagecreatefromwebp($image['tmp_name']);
                break;
            default:
                $img = imagecreatefromjpeg($image['tmp_name']);
        }

        return imagejpeg($img, $img_path, 75) ? $rname : 'upd_failed';
    }
}
