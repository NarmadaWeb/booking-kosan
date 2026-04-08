<?php
session_start();
require_once '../config/database.php';

// Room status function reads directly from status_kamar column
function getRoomStatusFromDB($conn, $room) {
    if ($room['status_kamar'] === 'perbaikan') return 'Pending';
    
    // Check for active confirmed reservation
    $stmt = $conn->prepare("SELECT id_reservasi FROM reservasi WHERE id_kamar = ? AND status_reservasi = 'Dikonfirmasi' AND CURDATE() BETWEEN tanggal_masuk AND tanggal_keluar LIMIT 1");
    $stmt->bind_param("i", $room['id_kamar']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return 'Occupied';
    
    return 'Available';
}

// Get statistics
$total_rooms = $conn->query("SELECT COUNT(*) as count FROM kamar")->fetch_assoc()['count'];
$occupied_count  = 0;
$available_count = 0;
$pending_count   = 0;

$all_rooms = $conn->query("SELECT * FROM kamar");
while($r = $all_rooms->fetch_assoc()) {
    $status = getRoomStatusFromDB($conn, $r);
    if ($status == 'Occupied') $occupied_count++;
    elseif ($status == 'Pending') $pending_count++;
    else $available_count++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Kamar - Kos Berkah Malika</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .navbar { border-bottom: 1px solid #e9ecef; }
        
        .page-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border-radius: 15px; 
            padding: 2.5rem; 
            margin-bottom: 2.5rem;
            margin-top: 1.5rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .page-header h1 { color: white; font-size: 2rem; }
        .page-header p { color: rgba(255,255,255,0.9); font-size: 1rem; }
        .page-header .badge { font-size: 0.9rem; }
        
        .stat-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px; 
            padding: 1.5rem; 
            text-align: center; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.15); 
            transition: transform 0.3s;
            color: white;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .stat-card i { color: rgba(255,255,255,0.9); }
        .stat-number { font-size: 2.5rem; font-weight: bold; margin: 0.5rem 0; color: white; }
        .stat-label { color: rgba(255,255,255,0.9); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Specific colors for each stat type */
        .stat-card.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.available { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.occupied { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        
        .room-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 1.5rem; transition: all 0.3s; }
        .room-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.15); transform: translateY(-3px); }
        
        .room-header { padding: 1.5rem; border-bottom: 1px solid #f0f0f0; }
        .room-body { padding: 1.5rem; }
        
        .status-badge { font-size: 0.85rem; padding: 0.5rem 1rem; border-radius: 50px; font-weight: 600; }
        
        .booking-info-box { background: #f8f9fa; border-left: 4px solid #0d6efd; padding: 1rem; border-radius: 8px; margin-top: 1rem; }
        
        .progress-bar-custom { height: 8px; border-radius: 10px; }
        
        .filter-btn { border-radius: 50px; padding: 0.5rem 1.5rem; margin: 0.25rem; border: 2px solid; transition: all 0.3s; }
        .filter-btn.active { transform: scale(1.05); }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary d-flex align-items-center" href="../index.php">
                <i class="fas fa-home me-2"></i>
                <span>Kos Berkah Malika</span>
            </a>
            <a href="../index.php" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i>Kembali
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2 d-flex align-items-center">
                        <i class="fas fa-door-open me-3"></i>
                        <span>Status Ketersediaan Kamar</span>
                    </h1>
                    <p class="mb-0">Informasi real-time semua kamar di Kos Berkah Malika</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-white bg-opacity-25 px-3 py-2">
                        <i class="fas fa-clock me-2"></i><?php echo date('d/m/Y H:i'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card total">
                    <i class="fas fa-door-closed fa-3x"></i>
                    <div class="stat-number"><?php echo $total_rooms; ?></div>
                    <div class="stat-label">Total Kamar</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card available">
                    <i class="fas fa-check-circle fa-3x"></i>
                    <div class="stat-number"><?php echo $available_count; ?></div>
                    <div class="stat-label">Tersedia</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card occupied">
                    <i class="fas fa-lock fa-3x"></i>
                    <div class="stat-number"><?php echo $occupied_count; ?></div>
                    <div class="stat-label">Terisi</div>
                </div>
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="text-center mb-4">
            <button class="filter-btn btn btn-primary active" onclick="filterRooms('all')">
                <i class="fas fa-th me-2"></i>Semua Kamar
            </button>
            <button class="filter-btn btn btn-outline-success" onclick="filterRooms('available')">
                <i class="fas fa-check-circle me-2"></i>Tersedia
            </button>
            <button class="filter-btn btn btn-outline-danger" onclick="filterRooms('occupied')">
                <i class="fas fa-lock me-2"></i>Terisi
            </button>
        </div>

        <!-- Room List -->
        <div class="row g-4" id="roomList">
            <?php
            $all_rooms = $conn->query("SELECT * FROM kamar ORDER BY lantai ASC, id_kamar ASC");
            while($room = $all_rooms->fetch_assoc()):
                $status = getRoomStatusFromDB($conn, $room);
                $status_class = $status == 'Occupied' ? 'occupied' : ($status == 'Pending' ? 'pending' : 'available');
                
                // Get booking details for occupied rooms
                $booking_html = '';
                if ($status == 'Occupied') {
                    $booking_sql = "SELECT * FROM reservasi WHERE id_kamar = ? AND status_reservasi = 'Dikonfirmasi' ORDER BY dibuat_pada DESC LIMIT 1";
                    $stmt = $conn->prepare($booking_sql);
                    $stmt->bind_param("i", $room['id_kamar']);
                    $stmt->execute();
                    $booking = $stmt->get_result()->fetch_assoc();
                    
                    if ($booking) {
                        $check_in    = new DateTime($booking['tanggal_masuk']);
                        $check_out   = new DateTime($booking['tanggal_keluar']);
                        $today_date  = new DateTime(date('Y-m-d'));
                        
                        $days_total     = $check_in->diff($check_out)->days;
                        $days_remaining = $today_date->diff($check_out)->days;
                        $progress = $days_total > 0 ? (($days_total - $days_remaining) / $days_total) * 100 : 0;
                        
                        $booking_html = "
                        <div class='booking-info-box'>
                            <div class='row align-items-center mb-3'>
                                <div class='col-md-6'>
                                    <small class='text-muted d-block mb-1'><i class='fas fa-calendar-alt me-2'></i>Periode Booking</small>
                                    <strong>{$check_in->format('d M Y')} - {$check_out->format('d M Y')}</strong>
                                </div>
                                <div class='col-md-6 text-md-end'>
                                    <small class='text-muted d-block mb-1'><i class='fas fa-hourglass-half me-2'></i>Waktu Tersisa</small>
                                    <strong class='text-warning'>{$days_remaining} hari dari {$days_total} hari</strong>
                                </div>
                            </div>
                            <div class='progress progress-bar-custom'>
                                <div class='progress-bar bg-primary' style='width: {$progress}%'></div>
                            </div>
                            <small class='text-muted mt-2 d-block'><i class='fas fa-user me-2'></i>Penyewa: {$booking['nama_pemesan']}</small>
                        </div>";
                    }
                }
            ?>
            <div class="col-md-6 col-lg-4 room-item" data-status="<?php echo $status_class; ?>">
                <div class="room-card">
                    <div class="room-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($room['nama_kamar']); ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-layer-group me-1"></i>Lantai <?php echo $room['lantai']; ?> • 
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($room['lokasi']); ?>
                                </small>
                            </div>
                            <?php if ($status == 'Occupied'): ?>
                                <span class="status-badge bg-danger text-white"><i class="fas fa-lock me-1"></i>Terisi</span>
                            <?php elseif ($status == 'Pending'): ?>
                                <span class="status-badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                            <?php else: ?>
                                <span class="status-badge bg-success text-white"><i class="fas fa-check-circle me-1"></i>Tersedia</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="room-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <small class="text-muted">Harga Bulanan</small>
                                <strong class="text-primary">Rp <?php echo number_format($room['harga_per_bulan']); ?></strong>
                            </div>
                            <?php if ($room['harga_per_tahun'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <small class="text-muted">Harga Tahunan</small>
                                <strong class="text-primary">Rp <?php echo number_format($room['harga_per_tahun']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php echo $booking_html; ?>
                        
                        <div class="d-grid gap-2 mt-3">
                            <a href="detail_kamar.php?id=<?php echo $room['id_kamar']; ?>" class="btn btn-outline-primary rounded-pill">
                                <i class="fas fa-info-circle me-2"></i>Lihat Detail
                            </a>
                            <?php if ($status == 'Available'): ?>
                            <a href="pesan_kamar.php?id=<?php echo $room['id_kamar']; ?>" class="btn btn-primary rounded-pill">
                                <i class="fas fa-paper-plane me-2"></i>Pesan Sekarang
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterRooms(filter) {
            const rooms = document.querySelectorAll('.room-item');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update button states
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.closest('.filter-btn').classList.add('active');
            
            // Filter rooms
            rooms.forEach(room => {
                if (filter === 'all' || room.dataset.status === filter) {
                    room.style.display = 'block';
                } else {
                    room.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
