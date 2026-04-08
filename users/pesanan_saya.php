<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Filter by user id
$user_id = $_SESSION['user_id'];

// Auto-verify: cek status pembayaran yang masih pending ke Midtrans API
$pending_sql = $conn->prepare("SELECT r.id_reservasi, p.kode_pesanan, p.id_pembayaran, p.status_transaksi 
    FROM reservasi r 
    JOIN pembayaran p ON r.id_reservasi = p.id_reservasi 
    WHERE r.id_pengguna = ? AND r.status_reservasi = 'Menunggu Pembayaran' AND p.status_transaksi = 'pending'
    ORDER BY p.id_pembayaran DESC");
$pending_sql->bind_param("i", $user_id);
$pending_sql->execute();
$pending_result = $pending_sql->get_result();

$serverKey = MIDTRANS_SERVER_KEY;
$isProduction = MIDTRANS_IS_PRODUCTION;

while ($prow = $pending_result->fetch_assoc()) {
    $order_id = $prow['kode_pesanan'];
    $api_url = $isProduction
        ? "https://api.midtrans.com/v2/{$order_id}/status"
        : "https://api.sandbox.midtrans.com/v2/{$order_id}/status";

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response && $httpCode == 200) {
        $api_result = json_decode($response, true);
        $ts = $api_result['transaction_status'] ?? '';
        $pt = $api_result['payment_type'] ?? '';
        $fs = $api_result['fraud_status'] ?? '';
        $ps = json_encode($api_result);

        // Update pembayaran
        $up = $conn->prepare("UPDATE pembayaran SET status_transaksi = ?, jenis_pembayaran = ?, status_penipuan = ?, pesan_status = ?, dibayar_pada = CURRENT_TIMESTAMP WHERE id_pembayaran = ?");
        $up->bind_param("ssssi", $ts, $pt, $fs, $ps, $prow['id_pembayaran']);
        $up->execute();

        // Update reservasi
        if ($ts == 'capture' || $ts == 'settlement') {
            $ur = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Menunggu' WHERE id_reservasi = ?");
            $ur->bind_param("i", $prow['id_reservasi']);
            $ur->execute();
        } elseif ($ts == 'cancel' || $ts == 'deny' || $ts == 'expire') {
            $ur = $conn->prepare("UPDATE reservasi SET status_reservasi = 'Dibatalkan' WHERE id_reservasi = ?");
            $ur->bind_param("i", $prow['id_reservasi']);
            $ur->execute();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pesanan Saya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand text-primary fw-bold" href="../index.php"><i class="fas fa-home me-2"></i>Kos Berkah Malika</a>
            <div class="navbar-nav ms-auto d-flex flex-row align-items-center gap-2">
                <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>Beranda</a>
                <a class="nav-link" href="form_data_penyewa.php"><i class="fas fa-id-card me-1"></i>Data Saya</a>
                <a class="nav-link" href="notifikasi.php"><i class="fas fa-bell me-1"></i>Notifikasi</a>
                <a class="nav-link" href="profil.php"><i class="fas fa-user-circle me-1"></i>Profil</a>
                <a class="btn btn-outline-danger ms-2 rounded-pill px-3" href="../logout.php"><i class="fas fa-sign-out-alt me-1"></i>Keluar</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="mb-4">Riwayat Pesanan</h2>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>



        <div class="row">
            <?php
            $stmt = $conn->prepare("SELECT r.*, k.nama_kamar, k.harga_per_bulan, k.foto_utama, p.status_transaksi, p.token_snap
                                    FROM reservasi r 
                                    JOIN kamar k ON r.id_kamar = k.id_kamar 
                                    LEFT JOIN pembayaran p ON r.id_reservasi = p.id_reservasi
                                    WHERE r.id_pengguna = ?
                                    ORDER BY r.dibuat_pada DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
            ?>
            <div class="col-md-12 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <img src="../public/<?php echo htmlspecialchars($row['foto_utama']); ?>" class="img-fluid rounded" onerror="this.src='https://via.placeholder.com/150'">
                            </div>
                            <div class="col-md-7">
                                <h5 class="card-title"><?php echo htmlspecialchars($row['nama_kamar']); ?></h5>
                                <p class="mb-1 text-muted"><i class="fas fa-calendar"></i> <?php echo $row['tanggal_masuk']; ?> sampai <?php echo $row['tanggal_keluar']; ?></p>
                                <p class="mb-1"><strong>Status Konfirmasi:</strong>
                                    <?php 
                                    $badge = 'bg-secondary';
                                    $status_text = $row['status_reservasi'] ?? '-';
                                    
                                    if ($row['status_reservasi'] == 'Dikonfirmasi') {
                                        $badge = 'bg-success';
                                        $status_text = 'Dikonfirmasi';
                                    } elseif ($row['status_reservasi'] == 'Dibatalkan') {
                                        $badge = 'bg-danger';
                                        $status_text = 'Dibatalkan';
                                    } elseif ($row['status_reservasi'] == 'Menunggu') {
                                        $badge = 'bg-warning text-dark';
                                        $status_text = 'Menunggu Konfirmasi Admin';
                                    } elseif ($row['status_reservasi'] == 'Menunggu Pembayaran') {
                                        $badge = 'bg-warning text-dark';
                                        $status_text = 'Menunggu Pembayaran';
                                    } elseif ($row['status_reservasi'] == 'Selesai') {
                                        $badge = 'bg-info text-dark';
                                        $status_text = 'Selesai';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge; ?>"><?php echo $status_text; ?></span>
                                </p>
                                <p class="mb-0"><strong>Status Pembayaran:</strong>
                                    <?php
                                    $pay_badge = 'bg-danger';
                                    $pay_status_text = 'Belum Lunas';
                                    $pt = strtolower($row['status_transaksi'] ?? '');
                                    $sr = $row['status_reservasi'] ?? '';

                                    if ($pt == 'settlement' || $pt == 'capture' || $pt == 'lunas') {
                                        $pay_badge = 'bg-success';
                                        $pay_status_text = 'Lunas';
                                    } elseif ($pt == 'pending' || $pt == 'menunggu') {
                                        $pay_badge = 'bg-warning text-dark';
                                        $pay_status_text = 'Menunggu Pembayaran';
                                    } elseif ($pt == 'cancel' || $pt == 'deny' || $pt == 'expire') {
                                        $pay_badge = 'bg-danger';
                                        $pay_status_text = 'Gagal / Expired';
                                    } elseif (empty($pt) && ($sr == 'Dikonfirmasi' || $sr == 'Selesai')) {
                                        // Fallback: jika tidak ada record pembayaran tapi reservasi sudah dikonfirmasi
                                        $pay_badge = 'bg-success';
                                        $pay_status_text = 'Lunas';
                                    } elseif (empty($pt) && $sr == 'Menunggu') {
                                        $pay_badge = 'bg-success';
                                        $pay_status_text = 'Lunas';
                                    }
                                    ?>
                                    <span class="badge <?php echo $pay_badge; ?>"><?php echo $pay_status_text; ?></span>
                                </p>
                                <div class="mt-3">
                                    <?php if ($row['status_reservasi'] == 'Menunggu Pembayaran' && !empty($row['token_snap'])): ?>
                                        <a href="bayar.php?id=<?php echo $row['id_reservasi']; ?>" class="btn btn-sm btn-danger rounded-pill px-3 me-1">
                                            <i class="fas fa-credit-card me-1"></i> Bayar Sekarang
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($row['status_reservasi'] == 'Dikonfirmasi'): ?>
                                        <a href="cetak_kwitansi.php?id=<?php echo $row['id_reservasi']; ?>" target="_blank" class="btn btn-sm btn-success rounded-pill px-3 me-1">
                                            <i class="fas fa-print me-1"></i> Cetak Kwitansi
                                        </a>
                                    <?php endif; ?>
                                    <a href="detail_kamar.php?id=<?php echo $row['id_kamar']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                        <i class="fas fa-info-circle me-1"></i> Detail Kamar
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-3 text-end">
                                <?php
                                $d1 = new DateTime($row['tanggal_masuk']);
                                $d2 = new DateTime($row['tanggal_keluar']);
                                $days = $d1->diff($d2)->days;
                                
                                // Use stored total_harga
                                $total = isset($row['total_harga']) && $row['total_harga'] > 0 ? $row['total_harga'] : ($days * ($row['harga_per_bulan'] / 30));
                                $durasi = isset($row['durasi_sewa']) ? $row['durasi_sewa'] : '1 Bulan';
                                ?>
                                <h4 class="text-primary mb-0">Rp <?php echo number_format($total); ?></h4>
                                <small class="text-muted d-block mb-2"><?php echo $durasi; ?></small>
                                <small class="text-muted" style="font-size: 0.7rem;">ID Transaksi: #BERKAH-<?php echo str_pad($row['id_reservasi'], 5, "0", STR_PAD_LEFT); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                }
            } else {
                echo "<div class='col-12'><div class='alert alert-info'>Anda belum memiliki pesanan.</div></div>";
            }
            ?>
        </div>
    </div>
</body>
</html>
