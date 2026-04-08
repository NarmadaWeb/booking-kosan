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
    $payment_method = $_POST['payment_method'];
    $duration_type  = $_POST['duration_type'];
    $catatan        = $_POST['catatan'] ?? '';
    $payment_proof  = null;
    $total_price    = $_POST['total_price'] ?? 0;

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

    // Handle File Upload
    $upload_error = '';
    if ($payment_method != 'Tunai') {
        $file_input_name = ($payment_method == 'Transfer') ? 'payment_proof_transfer' : 'payment_proof_ewallet';

        if (!empty($_FILES[$file_input_name]['name'])) {
            $target_dir = "../public/uploads/proofs/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

            $file_extension = strtolower(pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION));
            $new_filename   = uniqid('proof_') . '.' . $file_extension;
            $target_file    = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $target_file)) {
                $payment_proof = "uploads/proofs/" . $new_filename;
            } else {
                $upload_error = "Gagal mengupload bukti pembayaran.";
            }
        }
    }

    if ($upload_error) {
        $message = "<div class='alert alert-danger'>$upload_error</div>";
    } elseif ($check_in >= $check_out) {
        $message = "<div class='alert alert-danger'>Tanggal keluar harus setelah tanggal masuk.</div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO reservasi (id_kamar, id_pengguna, nama_pemesan, email_pemesan, no_hp_pemesan, tanggal_masuk, tanggal_keluar, durasi_sewa, jumlah_tamu, total_harga, status_reservasi, metode_pembayaran, bukti_pembayaran, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Pembayaran', ?, ?, ?)");
        $stmt->bind_param("iissssssidsss", $room_id, $user_id, $full_name, $email_pemesan, $no_hp_pemesan, $check_in, $check_out, $durasi_label, $guests, $price, $payment_method, $payment_proof, $catatan);

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
                'custom_expiry' => [
                    'order_time' => date('Y-m-d H:i:s O'),
                    'expiry_duration' => 24,
                    'unit' => 'hour'
                ]
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
            $redirectUrl = '';
            if ($response) {
                $responseObj = json_decode($response);
                if (isset($responseObj->token)) {
                    $snapToken = $responseObj->token;
                    $redirectUrl = $responseObj->redirect_url ?? '';

                    // Masukkan ke tabel pembayaran
                    $kode_pesanan = $transaction_details['order_id'];
                    $p_stmt = $conn->prepare("INSERT INTO pembayaran (id_reservasi, kode_pesanan, jumlah_bayar, status_transaksi, token_snap, url_pembayaran) VALUES (?, ?, ?, 'menunggu', ?, ?)");
                    $p_stmt->bind_param("isdss", $insert_id, $kode_pesanan, $price, $snapToken, $redirectUrl);
                    $p_stmt->execute();

                } else {
                    $message = "<div class='alert alert-danger'>Gagal mendapatkan token pembayaran dari Midtrans. " . htmlspecialchars($response) . "</div>";
                }
            } else {
                 $message = "<div class='alert alert-danger'>Gagal terhubung ke Midtrans.</div>";
            }

            if ($snapToken) {
                 $clientKey = MIDTRANS_CLIENT_KEY;
                 $snapJsUrl = $isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';
                 echo "<!DOCTYPE html><html lang='id'><head><title>Memproses Pembayaran</title></head><body>";
                 echo "<script src='{$snapJsUrl}' data-client-key='{$clientKey}'></script>";
                 echo "<script>
                     window.onload = function() {
                         window.snap.pay('{$snapToken}', {
                             onSuccess: function(result) {
                                 window.location.href = 'pesanan_saya.php?processing=1';
                             },
                             onPending: function(result) {
                                 window.location.href = 'pesanan_saya.php?processing=1';
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
        .payment-option { border: 2px solid #f1f5f9; border-radius: 16px; padding: 1.2rem; cursor: pointer; transition: 0.3s; text-align: center; height: 100%; background: white; }
        .payment-input { display: none; }
        .payment-input:checked + .payment-option { border-color: #4f46e5; background-color: #f5f3ff; }
        .payment-input:checked + .payment-option i { color: #4f46e5 !important; }
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
        <form method="POST" enctype="multipart/form-data">
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

                    <h5 class="fw-bold mb-4 d-flex align-items-center"><i class="fas fa-wallet me-3 text-indigo-600"></i>Metode Pembayaran</h5>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <input type="radio" name="payment_method" id="pmCash" value="Tunai" checked class="payment-input" onchange="togglePayment()">
                            <label for="pmCash" class="payment-option">
                                <i class="fas fa-money-bill-wave fa-2x mb-2 text-muted"></i>
                                <div class="fw-bold small">Tunai / COD</div>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input type="radio" name="payment_method" id="pmTransfer" value="Transfer" class="payment-input" onchange="togglePayment()">
                            <label for="pmTransfer" class="payment-option">
                                <i class="fas fa-university fa-2x mb-2 text-muted"></i>
                                <div class="fw-bold small">Transfer Bank</div>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <input type="radio" name="payment_method" id="pmEwallet" value="E-Wallet" class="payment-input" onchange="togglePayment()">
                            <label for="pmEwallet" class="payment-option">
                                <i class="fas fa-qrcode fa-2x mb-2 text-muted"></i>
                                <div class="fw-bold small">QRIS / E-Money</div>
                            </label>
                        </div>
                    </div>

                    <div id="transferInfo" class="alert alert-light border rounded-4" style="display:none;">
                        <h6 class="fw-bold mb-2 small uppercase">Rekening Pembayaran</h6>
                        <div class="d-flex justify-content-between mb-1"><span>BCA</span> <strong>123-456-789</strong></div>
                        <div class="d-flex justify-content-between mb-3"><span>A.N</span> <strong>Berkah Malika</strong></div>
                        <label class="form-label small">Upload Bukti Transfer</label>
                        <input type="file" name="payment_proof_transfer" class="form-control form-control-sm">
                    </div>

                    <div id="ewalletInfo" class="alert alert-light border rounded-4 text-center" style="display:none;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=MAJU-BERSAMA" class="mb-3 rounded shadow-sm border p-2 bg-white">
                        <p class="small text-muted mb-3">Scan QRIS di atas dan sertakan bukti screenshot.</p>
                        <input type="file" name="payment_proof_ewallet" class="form-control form-control-sm">
                    </div>
                    
                    <div class="alert alert-warning small rounded-3 mt-4">
                         <i class="fas fa-info-circle me-2"></i> Dengan menekan tombol di bawah, Anda menyetujui syarat & ketentuan sewa Kos Berkah Malika.
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill py-3 mt-3 shadow-lg fw-bold">
                        <i class="fas fa-check-circle me-2"></i> PEMBAYARAN
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

        function togglePayment() {
            const method = document.querySelector('input[name="payment_method"]:checked').value;
            document.getElementById('transferInfo').style.display = (method === 'Transfer') ? 'block' : 'none';
            document.getElementById('ewalletInfo').style.display = (method === 'E-Wallet') ? 'block' : 'none';
        }

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
