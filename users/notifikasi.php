<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifikasi SET status_baca = 'sudah dibaca' WHERE id_pengguna = $user_id");
    header("Location: notifikasi.php");
    exit();
}

// Mark single notification as read
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $stmt = $conn->prepare("UPDATE notifikasi SET status_baca = 'sudah dibaca' WHERE id_notifikasi = ? AND id_pengguna = ?");
    $stmt->bind_param("ii", $nid, $user_id);
    $stmt->execute();
    header("Location: notifikasi.php");
    exit();
}

// Delete notification
if (isset($_GET['delete'])) {
    $nid = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM notifikasi WHERE id_notifikasi = ? AND id_pengguna = ?");
    $stmt->bind_param("ii", $nid, $user_id);
    $stmt->execute();
    header("Location: notifikasi.php");
    exit();
}

// Get all notifications
$stmt = $conn->prepare("SELECT * FROM notifikasi WHERE id_pengguna = ? ORDER BY dibuat_pada DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifikasi = $stmt->get_result();

$unread = $conn->query("SELECT COUNT(*) as cnt FROM notifikasi WHERE id_pengguna = $user_id AND status_baca = 'belum dibaca'")->fetch_assoc()['cnt'];
$total  = $conn->query("SELECT COUNT(*) as cnt FROM notifikasi WHERE id_pengguna = $user_id")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Kos Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background-color: #f4f6fb; }
        .navbar { background: white !important; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        
        .page-header {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
        }
        
        .notif-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            border: 1px solid #f0f0f0;
            transition: all 0.3s;
            overflow: hidden;
        }
        .notif-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .notif-card.unread { border-left: 4px solid #0d6efd; }
        .notif-card.unread { background: linear-gradient(to right, #f0f7ff, white); }
        
        .notif-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .icon-info        { background: #e7f1ff; color: #0d6efd; }
        .icon-pembayaran  { background: #d1fae5; color: #059669; }
        .icon-reservasi   { background: #fef3c7; color: #d97706; }
        .icon-sistem      { background: #f3e8ff; color: #7c3aed; }
        
        .notif-time { font-size: 0.78rem; color: #9ca3af; }
        
        .badge-unread {
            background: #0d6efd;
            color: white;
            border-radius: 50px;
            padding: 0.2rem 0.6rem;
            font-size: 0.7rem;
        }
        
        .empty-state { text-align: center; padding: 5rem 2rem; }
        .empty-state i { font-size: 4rem; color: #dee2e6; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="../index.php">
                <i class="fas fa-home me-2"></i>Kos Berkah Malika
            </a>
            <div class="navbar-nav ms-auto d-flex flex-row align-items-center gap-2">
                <a href="../index.php" class="btn btn-outline-dark btn-sm rounded-pill">
                    <i class="fas fa-home me-1"></i>Beranda
                </a>
                <a href="profil.php" class="btn btn-light btn-sm rounded-pill">
                    <i class="fas fa-user me-1"></i>Profil
                </a>
                <a href="pesanan_saya.php" class="btn btn-outline-primary btn-sm rounded-pill">
                    <i class="fas fa-calendar-alt me-1"></i>Pesanan
                </a>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill">
                    <i class="fas fa-sign-out-alt me-1"></i>Keluar
                </a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1"><i class="fas fa-bell me-2"></i>Notifikasi</h4>
                    <p class="opacity-75 mb-0 small">
                        <?php echo $total; ?> total &bull; <strong><?php echo $unread; ?> belum dibaca</strong>
                    </p>
                </div>
                <?php if ($unread > 0): ?>
                <a href="notifikasi.php?mark_all_read=1" class="btn btn-light btn-sm rounded-pill">
                    <i class="fas fa-check-double me-1"></i>Tandai Semua Dibaca
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container pb-5" style="max-width: 750px;">
        <?php if ($notifikasi->num_rows === 0): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h5 class="text-muted">Tidak ada notifikasi</h5>
                <p class="text-muted small">Notifikasi dari admin akan muncul di sini.</p>
                <a href="../index.php" class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-home me-2"></i>Kembali ke Beranda
                </a>
            </div>
        <?php else: ?>
            <?php while ($n = $notifikasi->fetch_assoc()): 
                $is_unread = $n['status_baca'] === 'belum dibaca';
                $icon_map  = ['info' => 'icon-info', 'pembayaran' => 'icon-pembayaran', 'reservasi' => 'icon-reservasi', 'sistem' => 'icon-sistem'];
                $icon_fa   = ['info' => 'fas fa-info-circle', 'pembayaran' => 'fas fa-credit-card', 'reservasi' => 'fas fa-calendar-check', 'sistem' => 'fas fa-cog'];
                $icon_cls  = $icon_map[$n['jenis']] ?? 'icon-info';
                $icon_fa_val = $icon_fa[$n['jenis']] ?? 'fas fa-bell';
            ?>
            <div class="notif-card <?php echo $is_unread ? 'unread' : ''; ?>">
                <div class="d-flex align-items-start p-3 gap-3">
                    <div class="notif-icon <?php echo $icon_cls; ?>">
                        <i class="<?php echo $icon_fa_val; ?>"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="fw-bold mb-0 <?php echo $is_unread ? '' : 'text-muted'; ?>">
                                <?php echo htmlspecialchars($n['judul']); ?>
                                <?php if ($is_unread): ?>
                                    <span class="badge-unread ms-2">Baru</span>
                                <?php endif; ?>
                            </h6>
                            <div class="d-flex gap-1 ms-2">
                                <?php if ($is_unread): ?>
                                <a href="notifikasi.php?read=<?php echo $n['id_notifikasi']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-2 py-0" title="Tandai dibaca" style="font-size:0.75rem">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                                <a href="notifikasi.php?delete=<?php echo $n['id_notifikasi']; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-2 py-0" 
                                   onclick="return confirm('Hapus notifikasi ini?')" title="Hapus" style="font-size:0.75rem">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                        <p class="mb-1 small <?php echo $is_unread ? '' : 'text-muted'; ?>" style="line-height:1.6">
                            <?php echo htmlspecialchars($n['pesan']); ?>
                        </p>
                        <div class="notif-time">
                            <i class="fas fa-clock me-1"></i>
                            <?php
                            $diff  = (new DateTime())->diff(new DateTime($n['dibuat_pada']));
                            if ($diff->days > 0) echo $diff->days . ' hari lalu';
                            elseif ($diff->h > 0) echo $diff->h . ' jam lalu';
                            elseif ($diff->i > 0) echo $diff->i . ' menit lalu';
                            else echo 'Baru saja';
                            ?>
                            &bull; <?php echo date('d M Y, H:i', strtotime($n['dibuat_pada'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
