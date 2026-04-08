<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id_reservasi = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_reservasi) {
    header("Location: pesanan_saya.php");
    exit();
}

// Ambil data reservasi & pembayaran (pastikan milik user yang login)
$stmt = $conn->prepare("SELECT r.*, k.nama_kamar, p.token_snap, p.status_transaksi 
    FROM reservasi r 
    JOIN kamar k ON r.id_kamar = k.id_kamar 
    JOIN pembayaran p ON r.id_reservasi = p.id_reservasi 
    WHERE r.id_reservasi = ? AND r.id_pengguna = ? AND r.status_reservasi = 'Menunggu Pembayaran'
    ORDER BY p.id_pembayaran DESC LIMIT 1");
$stmt->bind_param("ii", $id_reservasi, $user_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data || empty($data['token_snap'])) {
    header("Location: pesanan_saya.php");
    exit();
}

$snapToken = $data['token_snap'];
$clientKey = MIDTRANS_CLIENT_KEY;
$isProduction = MIDTRANS_IS_PRODUCTION;
$snapJsUrl = $isProduction ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js';

$room_name = htmlspecialchars($data['nama_kamar']);
$formatted_price = number_format($data['total_harga'], 0, ',', '.');
$durasi = htmlspecialchars($data['durasi_sewa'] ?? '-');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - Kos Berkah Malika</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .bg-pattern { position: fixed; top: 0; left: 0; width: 100%; height: 100%; opacity: 0.05; background-image: radial-gradient(circle at 25% 25%, white 2px, transparent 2px), radial-gradient(circle at 75% 75%, white 1px, transparent 1px); background-size: 60px 60px, 40px 40px; pointer-events: none; }
        .glow-1 { position: fixed; top: -150px; right: -150px; width: 400px; height: 400px; background: radial-gradient(circle, rgba(139,92,246,0.3), transparent 70%); border-radius: 50%; animation: float 6s ease-in-out infinite; }
        .glow-2 { position: fixed; bottom: -100px; left: -100px; width: 350px; height: 350px; background: radial-gradient(circle, rgba(99,102,241,0.25), transparent 70%); border-radius: 50%; animation: float 8s ease-in-out infinite reverse; }
        @keyframes float { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-30px) scale(1.05); } }
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
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    <div class="glow-1"></div>
    <div class="glow-2"></div>
    <div class="payment-card">
        <div class="brand-logo"><i class="fas fa-home"></i>KOS BERKAH MALIKA</div>
        <div class="spinner-ring"></div>
        <div class="payment-title">Lanjutkan Pembayaran</div>
        <div class="payment-subtitle">Popup pembayaran akan segera muncul...</div>
        <div class="info-divider"></div>
        <div class="room-info">
            <div class="room-name"><i class="fas fa-door-open" style="color:#a5b4fc;margin-right:8px"></i><?php echo $room_name; ?></div>
            <div class="room-detail"><i class="fas fa-calendar-check"></i>Durasi: <?php echo $durasi; ?></div>
        </div>
        <div class="price-tag">Rp <?php echo $formatted_price; ?></div>
        <div class="price-label">Total Pembayaran</div>
        <div class="secure-badge"><i class="fas fa-lock"></i> Transaksi Aman via Midtrans</div>
    </div>

    <script src="<?php echo $snapJsUrl; ?>" data-client-key="<?php echo $clientKey; ?>"></script>
    <script>
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
            window.snap.pay('<?php echo $snapToken; ?>', {
                onSuccess: function(result) {
                    verifyAndRedirect(<?php echo $id_reservasi; ?>);
                },
                onPending: function(result) {
                    verifyAndRedirect(<?php echo $id_reservasi; ?>);
                },
                onError: function(result) {
                    alert('Pembayaran gagal!');
                    window.location.href = 'pesanan_saya.php';
                },
                onClose: function() {
                    window.location.href = 'pesanan_saya.php';
                }
            });
        };
    </script>
</body>
</html>
