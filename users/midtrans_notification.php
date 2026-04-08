<?php
require_once '../config/database.php';

// Server Key (Harus sama dengan yang di pesan_kamar.php)
$serverKey = MIDTRANS_SERVER_KEY;

// Dapatkan JSON Notifikasi dari Midtrans
$json_result = file_get_contents('php://input');
$result = json_decode($json_result, true);

if(!$result){
    die("Akses ditolak");
}

$order_id = $result['order_id'];
$status_code = $result['status_code'];
$gross_amount = $result['gross_amount'];
$server_signature = $result['signature_key'];
$transaction_status = $result['transaction_status'];

// Buat hash untuk verifikasi
$my_signature = hash("sha512", $order_id.$status_code.$gross_amount.$serverKey);

if ($my_signature != $server_signature) {
    die("Signature invalid");
}

// Order ID kita formatnya: BERKAH-{id_reservasi}-{timestamp}
$parts = explode('-', $order_id);
if (count($parts) < 2) {
    die("Format order_id tidak valid");
}

$id_reservasi = (int)$parts[1];

if ($transaction_status == 'capture' || $transaction_status == 'settlement') {
    // Pembayaran Sukses
    $stmt = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Menunggu' WHERE id_reservasi = ?");
    $stmt->bind_param("i", $id_reservasi);
    $stmt->execute();
} else if ($transaction_status == 'cancel' || $transaction_status == 'deny' || $transaction_status == 'expire') {
    // Pembayaran Gagal/Expired
    $stmt = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Dibatalkan' WHERE id_reservasi = ?");
    $stmt->bind_param("i", $id_reservasi);
    $stmt->execute();
}

http_response_code(200);
echo "OK";
?>
