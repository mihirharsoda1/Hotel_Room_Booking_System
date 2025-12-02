<?php
ob_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require('../inc/db_config.php');
require('../inc/essentials.php');
adminLogin();

$response = ['table_data' => '', 'pagination' => ''];

try {

    if (!isset($_POST['get_bookings'])) {
        echo json_encode($response);
        exit;
    }

    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = 5;
    $start = ($page - 1) * $limit;

    // show all bookings (REMOVED FILTER)
    $where = " WHERE 1 ";

    if ($search !== '') {
        $like = '%' . $search . '%';

        $count_sql = "SELECT COUNT(*) AS cnt FROM booking_order bo 
                      INNER JOIN booking_details bd ON bo.booking_id = bd.booking_id 
                      $where AND (bo.order_id LIKE ? OR bd.user_name LIKE ? OR bd.phonenum LIKE ?)";

        $stmt = $con->prepare($count_sql);
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $cnt_res = $stmt->get_result()->fetch_assoc();
        $total_rows = (int)$cnt_res['cnt'];
        $stmt->close();

        $data_sql = "SELECT bo.*, bd.* FROM booking_order bo 
                     INNER JOIN booking_details bd ON bo.booking_id = bd.booking_id 
                     $where AND (bo.order_id LIKE ? OR bd.user_name LIKE ? OR bd.phonenum LIKE ?) 
                     ORDER BY bo.booking_id DESC LIMIT ?, ?";

        $stmt = $con->prepare($data_sql);
        $stmt->bind_param('sssii', $like, $like, $like, $start, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {

        $count_sql = "SELECT COUNT(*) AS cnt FROM booking_order bo 
                      INNER JOIN booking_details bd ON bo.booking_id = bd.booking_id $where";

        $res = $con->query($count_sql);
        $cnt_res = $res->fetch_assoc();
        $total_rows = (int)$cnt_res['cnt'];

        $data_sql = "SELECT bo.*, bd.* FROM booking_order bo 
                     INNER JOIN booking_details bd ON bo.booking_id = bd.booking_id 
                     $where ORDER BY bo.booking_id DESC LIMIT $start, $limit";

        $result = $con->query($data_sql);
    }

    if ($total_rows === 0) {
        $response['table_data'] = "<tr><td colspan='6' class='text-center fw-bold'>No Bookings Found!</td></tr>";
        echo json_encode($response);
        exit;
    }

    $i = $start + 1;
    $table_data = '';

    while ($row = $result->fetch_assoc()) {

        $date = isset($row['datentime']) ? date("d-m-Y", strtotime($row['datentime'])) : '';
        $checkin = isset($row['check_in']) ? date("d-m-Y", strtotime($row['check_in'])) : '';
        $checkout = isset($row['check_out']) ? date("d-m-Y", strtotime($row['check_out'])) : '';

        $status = $row['booking_status'] ?? '';

        if ($status == 'booked') $status_bg = 'bg-success';
        elseif ($status == 'cancelled') $status_bg = 'bg-danger';
        elseif ($status == 'failed') $status_bg = 'bg-warning text-dark';
        else $status_bg = 'bg-secondary';

        $table_data .= "
            <tr>
                <td>{$i}</td>
                <td>
                    <span class='badge bg-primary'>Order ID: {$row['order_id']}</span><br>
                    <b>Name:</b> {$row['user_name']}<br>
                    <b>Phone No:</b> {$row['phonenum']}
                </td>
                <td>
                    <b>Room:</b> {$row['room_name']}<br>
                    <b>Price:</b> ₹{$row['price']}
                </td>
                <td>
                    <b>Amount:</b> ₹{$row['trans_amt']}<br>
                    <b>Date:</b> {$date}
                </td>
                <td><span class='badge {$status_bg}'>{$status}</span></td>
                <td>
                    <button type='button' onclick='download_pdf({$row['booking_id']})' 
                    class='btn btn-outline-success btn-sm fw-bold shadow-none'>
                        <i class='bi bi-file-earmark-arrow-down-fill'></i>
                    </button>
                </td>
            </tr>
        ";
        $i++;
    }

    // PAGINATION STYLE REQUESTED BY YOU
    $total_pages = ceil($total_rows / $limit);
    $pagination = '<nav aria-label="Page navigation example"><ul class="pagination">';

    $prev_page = $page - 1;
    $next_page = $page + 1;

    $disabled_prev = ($page <= 1) ? 'disabled' : '';
    $disabled_next = ($page >= $total_pages) ? 'disabled' : '';

    $pagination .= "<li class='page-item $disabled_prev'>
                        <a class='page-link' href='#' onclick='get_bookings(current_search, $prev_page)'>Prev</a>
                    </li>";

    for ($p = 1; $p <= $total_pages; $p++) {
        $active = ($p == $page) ? 'active' : '';
        $pagination .= "<li class='page-item $active'>
                            <a class='page-link' href='#' onclick='get_bookings(current_search, $p)'>$p</a>
                        </li>";
    }

    $pagination .= "<li class='page-item $disabled_next'>
                        <a class='page-link' href='#' onclick='get_bookings(current_search, $next_page)'>Next</a>
                    </li>";

    $pagination .= "</ul></nav>";

    $response['table_data'] = $table_data;
    $response['pagination'] = $pagination;

    echo json_encode($response);
    exit;
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
