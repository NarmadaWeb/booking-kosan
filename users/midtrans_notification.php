<?php
require_once '../config/database.php';

// Server Key
$serverKey = MIDTRANS_SERVER_KEY;

// Dapatkan JSON Notifikasi dari Midtrans
$json_result = file_get_contents('php://input');
$result = json_decode($json_result, true);

if(!$result){
    http_response_code(400);
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
    http_response_code(403);
    die("Signature invalid");
}

// Order ID kita formatnya: BERKAH-{id_reservasi}-{timestamp}
$parts = explode('-', $order_id);
if (count($parts) < 3) {
    http_response_code(400);
    die("Format order_id tidak valid");
}

$id_reservasi = (int)$parts[1];
$payment_type = $result['payment_type'] ?? '';
$fraud_status = $result['fraud_status'] ?? '';
$pesan_status = json_encode($result);

// Cek apakah record pembayaran sudah ada
$check_stmt = $conn->prepare("SELECT id_pembayaran FROM pembayaran WHERE id_reservasi = ? AND kode_pesanan = ?");
$check_stmt->bind_param("is", $id_reservasi, $order_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    // Update existing record
    $p_stmt = $conn->prepare("UPDATE pembayaran SET status_transaksi = ?, jenis_pembayaran = ?, status_penipuan = ?, pesan_status = ?, dibayar_pada = CURRENT_TIMESTAMP WHERE id_reservasi = ? AND kode_pesanan = ?");
    $p_stmt->bind_param("ssssis", $transaction_status, $payment_type, $fraud_status, $pesan_status, $id_reservasi, $order_id);
    $p_stmt->execute();
} else {
    // Insert new record jika belum ada
    $p_stmt = $conn->prepare("INSERT INTO pembayaran (id_reservasi, kode_pesanan, jumlah_bayar, jenis_pembayaran, status_transaksi, status_penipuan, pesan_status, dibayar_pada) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $gross = (float)$gross_amount;
    $p_stmt->bind_param("isdssss", $id_reservasi, $order_id, $gross, $payment_type, $transaction_status, $fraud_status, $pesan_status);
    $p_stmt->execute();
}

// Update status reservasi berdasarkan status transaksi
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
} else if ($transaction_status == 'pending') {
    // Menunggu Pembayaran
    $stmt = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Menunggu Pembayaran' WHERE id_reservasi = ?");
    $stmt->bind_param("i", $id_reservasi);
    $stmt->execute();
}

http_response_code(200);
echo "OK";
?>
