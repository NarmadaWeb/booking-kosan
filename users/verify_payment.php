<?php
/**
 * verify_payment.php
 * Endpoint untuk mengecek status transaksi di Midtrans API
 * dan update database secara lokal.
 * Digunakan karena webhook Midtrans tidak bisa menjangkau localhost.
 */
require_once '../config/database.php';

header('Content-Type: application/json');

$id_reservasi = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_reservasi) {
    echo json_encode(['success' => false, 'message' => 'ID reservasi tidak valid']);
    exit();
}

// Ambil data pembayaran berdasarkan id_reservasi
$stmt = $conn->prepare("SELECT * FROM pembayaran WHERE id_reservasi = ? ORDER BY id_pembayaran DESC LIMIT 1");
$stmt->bind_param("i", $id_reservasi);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    echo json_encode(['success' => false, 'message' => 'Data pembayaran tidak ditemukan']);
    exit();
}

$order_id = $payment['kode_pesanan'];
$serverKey = MIDTRANS_SERVER_KEY;
$isProduction = MIDTRANS_IS_PRODUCTION;

// Cek status transaksi via Midtrans API
$url = $isProduction
    ? "https://api.midtrans.com/v2/{$order_id}/status"
    : "https://api.sandbox.midtrans.com/v2/{$order_id}/status";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Basic ' . base64_encode($serverKey . ':')
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (!$response || $httpCode !== 200) {
    echo json_encode(['success' => false, 'message' => 'Gagal menghubungi Midtrans API', 'http_code' => $httpCode]);
    exit();
}

$result = json_decode($response, true);
$transaction_status = $result['transaction_status'] ?? '';
$payment_type = $result['payment_type'] ?? '';
$fraud_status = $result['fraud_status'] ?? '';

// Update tabel pembayaran
$pesan_status = json_encode($result);
$p_stmt = $conn->prepare("UPDATE pembayaran SET status_transaksi = ?, jenis_pembayaran = ?, status_penipuan = ?, pesan_status = ?, dibayar_pada = CURRENT_TIMESTAMP WHERE id_pembayaran = ?");
$p_stmt->bind_param("ssssi", $transaction_status, $payment_type, $fraud_status, $pesan_status, $payment['id_pembayaran']);
$p_stmt->execute();

// Update status reservasi berdasarkan status transaksi Midtrans
if ($transaction_status == 'capture' || $transaction_status == 'settlement') {
    $r_stmt = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Menunggu' WHERE id_reservasi = ?");
    $r_stmt->bind_param("i", $id_reservasi);
    $r_stmt->execute();

    echo json_encode(['success' => true, 'status' => 'paid', 'transaction_status' => $transaction_status]);
} elseif ($transaction_status == 'cancel' || $transaction_status == 'deny' || $transaction_status == 'expire') {
    $r_stmt = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Dibatalkan' WHERE id_reservasi = ?");
    $r_stmt->bind_param("i", $id_reservasi);
    $r_stmt->execute();

    echo json_encode(['success' => true, 'status' => 'failed', 'transaction_status' => $transaction_status]);
} elseif ($transaction_status == 'pending') {
    echo json_encode(['success' => true, 'status' => 'pending', 'transaction_status' => $transaction_status]);
} else {
    echo json_encode(['success' => true, 'status' => 'unknown', 'transaction_status' => $transaction_status]);
}
?>
