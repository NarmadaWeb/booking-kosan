<?php
session_start();
require_once '../config/database.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?message=Silakan login terlebih dahulu untuk memesan kamar.");
    exit();
}

$user_id = $_SESSION['user_id'];
$room_id = $_GET['id'] ?? 0;
$room = null;

// Get User Info for auto-fill
$u_stmt = $conn->prepare("SELECT * FROM pengguna WHERE id_pengguna = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$current_user = $u_stmt->get_result()->fetch_assoc();

if ($room_id) {
    $stmt = $conn->prepare("SELECT * FROM kamar WHERE id_kamar = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
}

if (!$room) {
    die("<div class='container py-5 text-center'><h3>Kamar tidak ditemukan</h3><a href='../index.php' class='btn btn-primary'>Kembali ke Beranda</a></div>");
}

// Check if room is already occupied
function isRoomOccupied($conn, $room_id) {
    // Check if room is manually marked for repair
    $chk = $conn->prepare("SELECT status_kamar FROM kamar WHERE id_kamar = ?");
    $chk->bind_param("i", $room_id);
    $chk->execute();
    $res = $chk->get_result()->fetch_assoc();
    if ($res && $res['status_kamar'] === 'perbaikan') return true;

    // Check for active confirmed reservation
    $sql = "SELECT id_reservasi FROM reservasi WHERE id_kamar = ? AND status_reservasi = 'Dikonfirmasi' AND CURDATE() BETWEEN tanggal_masuk AND tanggal_keluar";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

if (isRoomOccupied($conn, $room_id)) {
    die("<div class='container py-5 text-center'>
            <div class='alert alert-danger shadow-lg mx-auto' style='max-width: 500px; border-radius:20px;'>
                <i class='fas fa-exclamation-triangle fa-3x text-danger mb-3 d-block'></i>
                <h4>Maaf, Kamar Sudah Terisi</h4>
                <p class='mb-3'>Kamar <strong>" . htmlspecialchars($room['nama_kamar']) . "</strong> saat ini sudah ditempati. Silakan pilih kamar lain.</p>
                <a href='../index.php' class='btn btn-primary me-2 rounded-pill px-4'><i class='fas fa-arrow-left me-2'></i>Pilih Kamar Lain</a>
            </div>
         </div>");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name      = $_POST['full_name'];
    $email_pemesan  = $_POST['email_pemesan'];
    $no_hp_pemesan  = $_POST['no_hp_pemesan'];
    $check_in       = $_POST['check_in'];
    $check_out      = $_POST['check_out'];
    $guests         = $_POST['guests'] ?? 1;
    $duration_type  = $_POST['duration_type'];
    $catatan        = $_POST['catatan'] ?? '';
    $total_price    = $_POST['total_price'] ?? 0;
    $payment_method = 'Midtrans';

    // Recalculate Logic
    $date1 = new DateTime($check_in);
    $date2 = new DateTime($check_out);
    $diff  = $date1->diff($date2);
    $days  = $diff->days;
    if ($days <= 0) $days = 1;

    $price = 0;
    $durasi_label = '';
    
    if ($duration_type == 'Monthly') {
        $months = ($date2->format('Y') - $date1->format('Y')) * 12 + ($date2->format('m') - $date1->format('m'));
        if ($date2->format('d') > $date1->format('d')) {
            $months++;
        }
        if ($months <= 0) $months = 1;
        $price  = $months * $room['harga_per_bulan'];
        $durasi_label = $months . ' Bulan';
    } elseif ($duration_type == 'Yearly') {
        $years = $date2->format('Y') - $date1->format('Y');
        // Check if it's already past the anniversary date in the end year
        $date1MonthDay = $date1->format('md');
        $date2MonthDay = $date2->format('md');
        if ($date2MonthDay > $date1MonthDay) {
            $years++;
        }
        if ($years <= 0) $years = 1;
        $price = $years * $room['harga_per_tahun'];
        $durasi_label = $years . ' Tahun';
    }

    if ($check_in >= $check_out) {
        $message = "<div class='alert alert-danger'>Tanggal keluar harus setelah tanggal masuk.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO reservasi (id_kamar, id_pengguna, nama_pemesan, email_pemesan, no_hp_pemesan, tanggal_masuk, tanggal_keluar, durasi_sewa, jumlah_tamu, total_harga, status_reservasi, metode_pembayaran, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran', ?, ?)");
        $stmt->bind_param("iissssssisss", $room_id, $user_id, $full_name, $email_pemesan, $no_hp_pemesan, $check_in, $check_out, $durasi_label, $guests, $price, $payment_method, $catatan);

        if ($stmt->execute()) {
            $insert_id = $stmt->insert_id;

            // Generate Midtrans Snap Token
            $serverKey = MIDTRANS_SERVER_KEY;
            $isProduction = MIDTRANS_IS_PRODUCTION;

            $transaction_details = [
                'order_id' => 'BERKAH-' . $insert_id . '-' . time(),
                'gross_amount' => $price,
            ];

            $customer_details = [
                'first_name' => $full_name,
                'email' => $email_pemesan,
                'phone' => $no_hp_pemesan,
            ];

            $payload = [
                'transaction_details' => $transaction_details,
                'customer_details' => $customer_details,
            ];

            $url = $isProduction ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($serverKey . ':')
            ]);

            $response = curl_exec($ch);

            $snapToken = '';
            if ($response) {
                $responseObj = json_decode($response);
                if (isset($responseObj->token)) {
                    $snapToken = $responseObj->token;
                } else {
                    $message = "<div class='alert alert-danger'>Gagal mendapatkan token pembayaran dari Midtrans. " . htmlspecialchars($response) . "</div>";
                }
            } else {
                 $message = "<div class='alert alert-danger'>Gagal terhubung ke Midtrans.</div>";
            }

            if ($snapToken) {
                 // Insert record ke tabel pembayaran
                 $order_id_val = $transaction_details['order_id'];
                 $p_stmt = $conn->prepare("INSERT INTO pembayaran (id_reservasi, kode_pesanan, jumlah_bayar, status_transaksi, token_snap) VALUES (?, ?, ?, 'pending', ?)");
                 $p_stmt->bind_param("isds", $insert_id, $order_id_val, $price, $snapToken);
                 $p_stmt->execute();

                 $clientKey = MIDTRANS_CLIENT_KEY;
                 $snapJsUrl = $isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
                 $formatted_price = number_format($price, 0, ',', '.');
                 $room_name_safe = htmlspecialchars($room['nama_kamar']);
                 echo "<!DOCTYPE html><html lang='id'><head>
                 <meta charset='UTF-8'>
                 <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                 <title>Pembayaran - Kos Berkah Malika</title>
                 <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap' rel='stylesheet'>
                 <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
                 <style>
                     * { margin: 0; padding: 0; box-sizing: border-box; }
                     body { font-family: 'Poppins', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%); display: flex; align-items: center; justify-content: center; overflow: hidden; }
                     .bg-pattern { position: fixed; top: 0; left: 0; width: 100%; height: 100%; opacity: 0.05; background-image: radial-gradient(circle at 25% 25%, white 2px, transparent 2px), radial-gradient(circle at 75% 75%, white 1px, transparent 1px); background-size: 60px 60px, 40px 40px; pointer-events: none; }
                     .glow-1 { position: fixed; top: -150px; right: -150px; width: 400px; height: 400px; background: radial-gradient(circle, rgba(139,92,246,0.3), transparent 70%); border-radius: 50%; animation: float 6s ease-in-out infinite; }
                     .glow-2 { position: fixed; bottom: -100px; left: -100px; width: 350px; height: 350px; background: radial-gradient(circle, rgba(99,102,241,0.25), transparent 70%); border-radius: 50%; animation: float 8s ease-in-out infinite reverse; }
                     @keyframes float { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-30px) scale(1.05); } }
                     @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
                     @keyframes spin { to { transform: rotate(360deg); } }
                     @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
                     .payment-card { background: rgba(255,255,255,0.08); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.15); border-radius: 28px; padding: 3rem 2.5rem; max-width: 460px; width: 90%; text-align: center; color: white; animation: fadeInUp 0.6s ease-out; box-shadow: 0 25px 60px rgba(0,0,0,0.3); }
                     .brand-logo { font-size: 1.1rem; font-weight: 700; letter-spacing: 1px; margin-bottom: 2rem; opacity: 0.9; }
                     .brand-logo i { color: #a5b4fc; margin-right: 8px; }
                     .spinner-ring { width: 64px; height: 64px; border: 3px solid rgba(255,255,255,0.15); border-top-color: #a5b4fc; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1.5rem; }
                     .payment-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 0.4rem; }
                     .payment-subtitle { font-size: 0.85rem; opacity: 0.6; margin-bottom: 2rem; }
                     .info-divider { width: 50px; height: 3px; background: linear-gradient(90deg, #818cf8, #a5b4fc); border-radius: 10px; margin: 0 auto 1.5rem; }
                     .room-info { background: rgba(255,255,255,0.06); border-radius: 16px; padding: 1.2rem; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.08); }
                     .room-name { font-weight: 600; font-size: 1rem; margin-bottom: 0.3rem; }
                     .room-detail { font-size: 0.78rem; opacity: 0.5; }
                     .room-detail i { margin-right: 5px; color: #a5b4fc; }
                     .price-tag { font-size: 2rem; font-weight: 800; background: linear-gradient(135deg, #c7d2fe, #e0e7ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 0.3rem; }
                     .price-label { font-size: 0.75rem; opacity: 0.5; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 1.5rem; }
                     .secure-badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(34,197,94,0.15); color: #86efac; padding: 0.4rem 1rem; border-radius: 50px; font-size: 0.72rem; font-weight: 600; }
                     .secure-badge i { font-size: 0.7rem; }
                 </style></head><body>
                 <div class='bg-pattern'></div>
                 <div class='glow-1'></div>
                 <div class='glow-2'></div>
                 <div class='payment-card'>
                     <div class='brand-logo'><i class='fas fa-home'></i>KOS BERKAH MALIKA</div>
                     <div class='spinner-ring'></div>
                     <div class='payment-title'>Memproses Pembayaran</div>
                     <div class='payment-subtitle'>Popup pembayaran akan segera muncul...</div>
                     <div class='info-divider'></div>
                     <div class='room-info'>
                         <div class='room-name'><i class='fas fa-door-open me-2' style='color:#a5b4fc'></i>{$room_name_safe}</div>
                         <div class='room-detail'><i class='fas fa-calendar-check'></i>Durasi: {$durasi_label}</div>
                     </div>
                     <div class='price-tag'>Rp {$formatted_price}</div>
                     <div class='price-label'>Total Pembayaran</div>
                     <div class='secure-badge'><i class='fas fa-lock'></i> Transaksi Aman via Midtrans</div>
                 </div>";
                 echo "<script src='{$snapJsUrl}' data-client-key='{$clientKey}'></script>";
                 echo "<script>
                     function verifyAndRedirect(reservasiId) {
                         fetch('verify_payment.php?id=' + reservasiId)
                             .then(function(r) { return r.json(); })
                             .then(function() {
                                 window.location.href = 'pesanan_saya.php';
                             })
                             .catch(function() {
                                 window.location.href = 'pesanan_saya.php';
                             });
                     }
                     window.onload = function() {
                         window.snap.pay('{$snapToken}', {
                             onSuccess: function(result) {
                                 verifyAndRedirect({$insert_id});
                             },
                             onPending: function(result) {
                                 verifyAndRedirect({$insert_id});
                             },
                             onError: function(result) {
                                 alert('Pembayaran gagal!');
                                 window.location.href = 'pesanan_saya.php';
                             },
                             onClose: function() {
                                 alert('Anda menutup popup tanpa menyelesaikan pembayaran');
                                 window.location.href = 'pesanan_saya.php';
                             }
                         });
                     };
                 </script></body></html>";
                 exit();
            }
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Form Pemesanan - Kos Berkah Malika</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #4f46e5; --secondary-color: #f8f9fa; }
        body { background-color: #f8fafc; font-family: 'Poppins', sans-serif; }
        .page-header { background: linear-gradient(135deg, #4f46e5, #3730a3); color: white; padding: 3rem 0; border-radius: 0 0 40px 40px; }
        .card-custom { border: 0; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); background: white; }
        .form-label { font-weight: 600; color: #4b5563; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control, .form-select { border-radius: 12px; padding: 0.75rem 1rem; border: 2px solid #f1f5f9; background-color: #f8fafc; transition: 0.3s; }
        .form-control:focus { border-color: #4f46e5; box-shadow: 0 0 0 4px rgba(79,70,229,0.1); background-color: white; }
        .segment-control { display: flex; background: #f1f5f9; padding: 5px; border-radius: 14px; margin-bottom: 1.5rem; }
        .segment-item { flex: 1; }
        .segment-input { display: none; }
        .segment-label { display: block; text-align: center; padding: 10px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.85rem; color: #64748b; transition: 0.2s; }
        .segment-input:checked + .segment-label { background: white; color: #4f46e5; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

        .total-price-display { font-size: 2rem; font-weight: 800; color: #4f46e5; }
        .room-summary-img { width: 100%; height: 180px; object-fit: cover; border-radius: 16px; }
    </style>
</head>
<body>

    <div class="page-header text-center shadow-sm">
        <div class="container">
            <h2 class="fw-bold mb-1">Form Pemesanan Kamar</h2>
            <p class="opacity-75 small">Lengkapi detail untuk konfirmasi reservasi Anda</p>
        </div>
    </div>

    <div class="container py-5">
        <form method="POST">
        <div class="row g-4">
            
            <div class="col-lg-8">
                <div class="card-custom p-4 p-md-5">
                    <?php echo $message; ?>
                    
                    <h5 class="fw-bold mb-4 d-flex align-items-center"><i class="fas fa-user-circle me-3 text-indigo-600"></i>Informasi Pemesan</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($current_user['nama_lengkap'] ?: $_SESSION['username']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Aktif</label>
                            <input type="email" name="email_pemesan" class="form-control" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nomor WhatsApp</label>
                            <input type="text" name="no_hp_pemesan" class="form-control" value="<?php echo htmlspecialchars($current_user['no_hp']); ?>" required placeholder="08xxxxxxxx">
                        </div>
                    </div>
                    
                    <hr class="my-5 opacity-25">
                    
                    <h5 class="fw-bold mb-4 d-flex align-items-center"><i class="fas fa-calendar-alt me-3 text-indigo-600"></i>Detail Reservasi</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Masuk</label>
                            <input type="date" name="check_in" id="checkIn" class="form-control" required min="<?php echo date('Y-m-d'); ?>" onchange="calculateTotal()">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Keluar</label>
                            <input type="date" name="check_out" id="checkOut" class="form-control" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" onchange="calculateTotal()">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Jumlah Tamu</label>
                            <select name="guests" class="form-select">
                                <option value="1">1 Orang (Default)</option>
                                <option value="2">2 Orang</option>
                            </select>
                        </div>
                    </div>
                    
                    <label class="form-label">Tipe Sewa & Pembayaran</label>
                    <div class="segment-control">
                        <div class="segment-item">
                            <input type="radio" name="duration_type" id="dtMonthly" value="Monthly" checked class="segment-input" onchange="calculateTotal()">
                            <label for="dtMonthly" class="segment-label">Masa Sewa Bulanan</label>
                        </div>
                         <div class="segment-item">
                            <input type="radio" name="duration_type" id="dtYearly" value="Yearly" class="segment-input" onchange="calculateTotal()">
                            <label for="dtYearly" class="segment-label">Masa Sewa Tahunan</label>
                        </div>
                    </div>

                    <div class="mb-5">
                       <label class="form-label">Catatan Tambahan (Opsional)</label>
                       <textarea name="catatan" class="form-control" rows="3" placeholder="Contoh: Bawa kendaraan mobil, butuh parkir lebih, dll."></textarea>
                    </div>
                    
                    <div class="bg-indigo-50 p-4 rounded-4 mb-5 text-center border border-indigo-100" style="background: #f5f3ff;">
                        <span class="text-indigo-600 small fw-bold text-uppercase letter-spacing-1">Total Biaya Reservasi</span>
                        <div class="total-price-display my-2" id="totalPriceDisplay">Rp 0</div>
                        <input type="hidden" name="total_price" id="totalPriceInput" value="0">
                        <div class="badge bg-indigo-600 rounded-pill px-3 py-2" id="durationText">Pilih tanggal untuk cek biaya</div>
                    </div>

                    <div class="bg-light p-4 rounded-4 mb-4 text-center border">
                        <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                        <h6 class="fw-bold mb-1">Pembayaran via Midtrans</h6>
                        <p class="small text-muted mb-0">Setelah menekan tombol di bawah, Anda akan diarahkan ke halaman pembayaran Midtrans. Tersedia berbagai metode: Transfer Bank, E-Wallet (GoPay, OVO, Dana), QRIS, dan lainnya.</p>
                    </div>
                    
                    <div class="alert alert-warning small rounded-3">
                         <i class="fas fa-info-circle me-2"></i> Dengan menekan tombol di bawah, Anda menyetujui syarat & ketentuan sewa Kos Berkah Malika.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill py-3 mt-3 shadow-lg fw-bold">
                        <i class="fas fa-credit-card me-2"></i> LANJUT KE PEMBAYARAN
                    </button>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card-custom p-4 sticky-top" style="top: 2rem;">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">Unit Pilihan Anda</h6>
                    <img src="../public/<?php echo $room['foto_utama']; ?>" class="room-summary-img mb-3" onerror="this.src='https://via.placeholder.com/400x250'">
                    <h5 class="fw-bold text-indigo-600 mb-1"><?php echo htmlspecialchars($room['nama_kamar']); ?></h5>
                    <p class="text-muted small mb-3"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($room['lokasi']); ?></p>
                    
                    <div class="d-flex justify-content-between small mb-2 text-muted">
                        <span>Harga Kamar (Bln)</span>
                        <span class="fw-bold text-dark">Rp <?php echo number_format($room['harga_per_bulan']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between small border-top pt-2 mt-2">
                        <span>Lantai</span>
                        <span class="fw-bold"><?php echo $room['lantai']; ?></span>
                    </div>
                </div>
            </div>

        </div>
        </form>
    </div>
    
    <script>
        const prices = { Monthly: <?php echo $room['harga_per_bulan']; ?>, Yearly: <?php echo $room['harga_per_tahun']; ?> };



        function calculateTotal() {
            const checkIn  = document.getElementById('checkIn').value;
            const checkOut = document.getElementById('checkOut').value;
            const type     = document.querySelector('input[name="duration_type"]:checked').value;
            
            if (checkIn && checkOut) {
                const d1 = new Date(checkIn);
                const d2 = new Date(checkOut);
                const diffTime = d2 - d1;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 0) {
                    let total = 0;
                    let text = '';
                    if (type === 'Monthly') {
                        let months = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
                        if (d2.getDate() > d1.getDate()) {
                            months++;
                        }
                        if (months <= 0) months = 1;
                        total = months * prices.Monthly;
                        text = months + ' Bulan';
                    } else {
                        let years = d2.getFullYear() - d1.getFullYear();
                        // Check if day/month of d2 is past d1 to count as additional year
                        const d1md = (d1.getMonth() + 1) * 100 + d1.getDate();
                        const d2md = (d2.getMonth() + 1) * 100 + d2.getDate();
                        if (d2md > d1md) {
                            years++;
                        }
                        if (years <= 0) years = 1;
                        total = years * prices.Yearly;
                        text = years + ' Tahun';
                    }
                    document.getElementById('totalPriceDisplay').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
                    document.getElementById('totalPriceInput').value = total;
                    document.getElementById('durationText').innerText = 'Dihitung untuk ' + text;
                } else {
                    document.getElementById('totalPriceDisplay').innerText = 'Rp 0';
                    document.getElementById('durationText').innerText = 'Tanggal Tidak Valid';
                }
            }
        }
    </script>
</body>
</html>
