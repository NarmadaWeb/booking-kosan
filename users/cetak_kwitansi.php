<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get Booking Data
$sql = "SELECT r.*, k.nama_kamar, k.lokasi as lokasi_kamar, u.nama_lengkap as nama_user, u.email as email_user
        FROM reservasi r 
        JOIN kamar k ON r.id_kamar = k.id_kamar 
        JOIN pengguna u ON r.id_pengguna = u.id_pengguna
        WHERE r.id_reservasi = ? AND r.id_pengguna = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking || $booking['status_reservasi'] !== 'Dikonfirmasi') {
    die("Kwitansi hanya dapat dicetak untuk reservasi yang telah dikonfirmasi.");
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kwitansi #
        <?php echo $id; ?> - Berkah Malika
    </title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .receipt-container {
            max-width: 800px;
            margin: auto;
            border: 2px solid #333;
            padding: 40px;
        }

        .header {
            text-align: center;
            border-bottom: 2px dashed #999;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            text-transform: uppercase;
        }

        .header p {
            margin: 5px 0;
            font-size: 14px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
        }

        .total-section {
            margin-top: 40px;
            border-top: 2px solid #333;
            padding-top: 10px;
            text-align: right;
        }

        .total-amount {
            font-size: 20px;
            font-weight: bold;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            font-style: italic;
        }

        .signature-box {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }

        .signature {
            text-align: center;
            width: 200px;
        }

        .btn-print {
            margin-top: 20px;
            padding: 10px 20px;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        @media print {
            .btn-print {
                display: none;
            }

            body {
                padding: 0;
            }

            .receipt-container {
                border: none;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-container">
        <div class="header">
            <h1>Kwitansi Pembayaran</h1>
            <p><strong>Kos Berkah Malika</strong></p>
            <p>Jl. Swadaya No.15, Kekalik Jaya, Mataram</p>
            <p>Telp: +62 823-4037-1303</p>
        </div>

        <div class="info-row">
            <span class="info-label">No. Transaksi:</span>
            <span>#BERKAH-
                <?php echo str_pad($id, 5, "0", STR_PAD_LEFT); ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Tanggal Cetak:</span>
            <span>
                <?php echo date('d F Y, H:i'); ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Nama Penyewa:</span>
            <span>
                <?php echo htmlspecialchars($booking['nama_pemesan']); ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Unit Kamar:</span>
            <span>
                <?php echo htmlspecialchars($booking['nama_kamar']); ?> (
                <?php echo htmlspecialchars($booking['lokasi_kamar']); ?>)
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Periode Sewa:</span>
            <span>
                <?php echo date('d M Y', strtotime($booking['tanggal_masuk'])); ?> -
                <?php echo date('d M Y', strtotime($booking['tanggal_keluar'])); ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Durasi:</span>
            <span>
                <?php echo htmlspecialchars($booking['durasi_sewa']); ?>
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Metode Pembayaran:</span>
            <span>
                <?php echo htmlspecialchars($booking['metode_pembayaran']); ?>
            </span>
        </div>

        <div class="total-section">
            <p>TOTAL PEMBAYARAN</p>
            <p class="total-amount">Rp
                <?php echo number_format($booking['total_harga']); ?>
            </p>
        </div>

        <div class="signature-box">
            <div class="signature">
                <p>Penyewa,</p>
                <div style="height: 60px;"></div>
                <p>(
                    <?php echo htmlspecialchars($booking['nama_user']); ?> )
                </p>
            </div>
            <div class="signature">
                <p>Admin Berkah Malika,</p>
                <div style="height: 60px;"></div>
                <p>( ............. )</p>
            </div>
        </div>

        <div class="footer">
            <p>Terima kasih telah mempercayai Kos Berkah Malika sebagai hunian Anda.</p>
            <p>Simpan kwitansi ini sebagai bukti pembayaran yang sah.</p>
        </div>
    </div>

    <div style="text-align: center;">
        <button class="btn-print" onclick="window.print()">Cetak Sekarang</button>
        <button class="btn-print" style="background: #ccc; color: #333;"
            onclick="window.history.back()">Kembali</button>
    </div>
</body>

</html>