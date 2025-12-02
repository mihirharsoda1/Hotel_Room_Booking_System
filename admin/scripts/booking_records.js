// admin/scripts/booking_records.js

let current_page = 1;
let current_search = "";

// Fetch and display bookings
function get_bookings(search = '', page = 1) {
    current_search = search;
    current_page = page;

    const params = new URLSearchParams();
    params.append('get_bookings', '1');
    params.append('search', search);
    params.append('page', page);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/booking_records.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest'); // lets server detect AJAX

    xhr.onload = function () {
        if (this.status !== 200) {
            console.error('Server error:', this.status, this.responseText);
            alert('Server error. Check console for details.');
            return;
        }

        if (!this.responseText) {
            console.error('Empty response from server');
            return;
        }

        try {
            const res = JSON.parse(this.responseText);

            if (res.status && res.status === 'logout') {
                alert('Session expired. Redirecting to login.');
                window.location.href = 'index.php';
                return;
            }

            document.getElementById('table-data').innerHTML = res.table_data || "<tr><td colspan='6' class='text-center fw-bold'>No Bookings Found!</td></tr>";
            document.getElementById('pagination').innerHTML = res.pagination || "";
        } catch (e) {
            console.error('Invalid JSON response', e);
            console.log('Raw response:', this.responseText);
            alert('Invalid server response. Check console.');
        }
    };

    xhr.onerror = function () {
        console.error('Request failed');
    };

    xhr.send(params.toString());
}

// download PDF of booking
function download_pdf(id) {
    window.location.href = 'generate_pdf.php?gen_pdf&id=' + id;
}

// initial load
window.onload = function () {
    get_bookings('', 1);
};
