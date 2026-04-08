<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kos Berkah Malika - Temukan Kamar Impianmu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css">
    <style>
        .map-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            height: 0;
        }

        .map-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 12px;
        }

        .footer-link {
            color: #adb5bd;
            text-decoration: none;
            transition: 0.2s;
        }

        .footer-link:hover {
            color: white;
        }

        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #25d366;
            color: white;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            text-align: center;
            font-size: 30px;
            box-shadow: 2px 2px 3px #999;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .whatsapp-float:hover {
            background-color: #128c7e;
            transform: scale(1.1);
            color: white;
        }

        .section-title {
            font-weight: 700;
            margin-bottom: 2rem;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -10px;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background-color: #0d6efd;
            border-radius: 2px;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top shadow-sm mx-3 mt-3 rounded-3"
        style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#"><i class="fas fa-home me-2"></i>Kos Berkah Malika</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Room Status Link - Direct to Full Page -->
                    <li class="nav-item">
                        <a class="nav-link" href="users/status_kamar.php">
                            <i class="fas fa-door-open me-1"></i> Status Kamar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users/pesanan_saya.php">
                            <i class="fas fa-calendar-alt me-1 text-success "></i> Pesanan Saya
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users/form_data_penyewa.php">
                            <i class="fas fa-id-card me-1 text-info"></i> Data Penyewa
                        </a>
                    </li>
                    <?php if (isset($_SESSION['username'])): ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="admin/index.php"><i
                                class="fas fa-shield-alt me-1"></i>Admin Panel</a></li>
                    <?php
    else: ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle fs-5 me-2"></i>
                            <span>
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </span>
                            <?php
        $notif_unread = $conn->query("SELECT COUNT(*) as c FROM notifikasi WHERE id_pengguna = {$_SESSION['user_id']} AND status_baca = 'belum dibaca'")->fetch_assoc()['c'];
        if ($notif_unread > 0):
?>
                            <span class="ms-1 badge rounded-pill bg-danger" style="font-size:0.6rem">
                                <?php echo $notif_unread; ?>
                            </span>
                            <?php
        endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2"
                            aria-labelledby="userDropdown">
                            <li><a class="dropdown-item py-3" href="users/profil.php"><i
                                        class="fas fa-user-edit me-2 text-primary"></i>Profil Saya</a></li>
                            <li>
                                <a class="dropdown-item py-3 d-flex justify-content-between align-items-center"
                                    href="users/notifikasi.php">
                                    <span><i class="fas fa-bell me-2 text-warning"></i>Notifikasi</span>
                                    <?php if ($notif_unread > 0): ?>
                                    <span class="badge bg-danger rounded-pill">
                                        <?php echo $notif_unread; ?>
                                    </span>
                                    <?php
        endif; ?>
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item py-3 text-danger" href="logout.php"><i
                                        class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                    <?php
    endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="btn btn-outline-danger ms-2 rounded-pill px-4"
                            href="logout.php">Logout</a></li>
                    <?php
    endif; ?>
                    <?php
else: ?>
                    <li class="nav-item"><a class="nav-link" href="users/login.php">Masuk</a></li>
                    <li class="nav-item"><a class="btn btn-primary ms-2 rounded-pill px-4"
                            href="users/daftar.php">Daftar</a></li>
                    <?php
endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section text-center d-flex align-items-center justify-content-center text-white"
        style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('public/image/AR91_87_Molly_Grey_grande.jpg') no-repeat center center/cover; height: 80vh; padding-top: 80px;">
        <div class="container">
            <h1 class="display-3 fw-bold mb-3">Selamat Datang di Kos Berkah Malika</h1>
            <p class="lead mb-4 fs-4">Hunian Kos Nyaman, Strategis, dan Terjangkau di Pusat Kota</p>
            <a href="#rooms" class="btn btn-primary btn-lg rounded-pill px-5 py-3 shadow">Cari Kamar Sekarang</a>
        </div>
    </header>

    <?php if (isset($_SESSION['user_id'])):
    $uid = $_SESSION['user_id'];
    // Only show if they have a confirmed reservation but no tenant data yet
    $has_confirmed = $conn->query("SELECT id_reservasi FROM reservasi WHERE id_pengguna = $uid AND status_reservasi = 'Dikonfirmasi' LIMIT 1")->fetch_assoc();
    $has_profile = $conn->query("SELECT id_penyewa FROM data_penyewa WHERE id_pengguna = $uid LIMIT 1")->fetch_assoc();

    if ($has_confirmed && !$has_profile):
?>
    <div class="container mt-4">
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center p-4 rounded-4"
            style="background: linear-gradient(to right, #e0f2fe, #f0f9ff);">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-4"
                style="width: 60px; height: 60px; min-width: 60px;">
                <i class="fas fa-id-card fa-lg"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="fw-bold mb-1">Lengkapi Profil Anda</h5>
                <p class="mb-0 text-muted small">Halo <strong>
                        <?php echo $_SESSION['username']; ?>
                    </strong>, Anda belum melengkapi data diri sebagai penyewa. Silakan lengkapi data Anda untuk
                    mempermudah proses reservasi.</p>
            </div>
            <a href="users/form_data_penyewa.php" class="btn btn-primary rounded-pill px-4 ms-3">Lengkapi Sekarang</a>
        </div>
    </div>
    <?php
    endif;
endif; ?>

    <!-- Room Status Board -->
    <div class="container py-5">
        <div class="text-center">
            <h2 class="section-title">Status Ketersediaan Kamar</h2>
        </div>
        <div class="row mt-4 justify-content-center">
            <?php
function getRoomStatusFromDB($conn, $room)
{
    if ($room['status_kamar'] === 'perbaikan')
        return 'Pending';

    // Check for active confirmed reservation
    $stmt = $conn->prepare("SELECT id_reservasi FROM reservasi WHERE id_kamar = ? AND status_reservasi = 'Dikonfirmasi' AND CURDATE() BETWEEN tanggal_masuk AND tanggal_keluar LIMIT 1");
    $stmt->bind_param("i", $room['id_kamar']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0)
        return 'Occupied';

    return 'Available';
}

$rooms_res = $conn->query("SELECT * FROM kamar ORDER BY lantai ASC, id_kamar ASC");
$rooms_by_floor = [];
while ($r = $rooms_res->fetch_assoc()) {
    $rooms_by_floor[$r['lantai']][] = $r;
}

foreach ($rooms_by_floor as $floor => $floor_rooms):
?>
            <div class="col-md-5 mb-4">
                <div class="card shadow border-0 h-100">
                    <div class="card-header bg-white align-items-center d-flex border-bottom-0 pt-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                            style="width: 40px; height: 40px; margin-right: 15px;">
                            <span class="fw-bold">
                                <?php echo $floor; ?>
                            </span>
                        </div>
                        <h5 class="mb-0 fw-bold">Lantai
                            <?php echo $floor; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($floor_rooms as $room):
        $status = getRoomStatusFromDB($conn, $room);
?>
                            <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                <span class="fw-medium"><i class="fas fa-door-closed me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($room['nama_kamar']); ?>
                                </span>
                                <?php if ($status == 'Occupied'): ?>
                                <span class="badge bg-danger rounded-pill">Terisi</span>
                                <?php
        elseif ($status == 'Pending'): ?>
                                <span class="badge bg-warning text-dark rounded-pill">Menunggu</span>
                                <?php
        else: ?>
                                <span class="badge bg-success rounded-pill">Tersedia</span>
                                <?php
        endif; ?>
                            </li>
                            <?php
    endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <?php
endforeach; ?>
        </div>
    </div>

    <!-- Rooms Section -->
    <div class="container py-5" id="rooms">
        <div class="text-center">
            <h2 class="section-title">Pilihan Kamar Terbaik</h2>
            <p class="text-muted">Pilih kamar yang sesuai dengan kebutuhan dan budget Anda</p>
        </div>
        <div class="row mt-4 g-4">
            <?php
$result = $conn->query("SELECT * FROM kamar");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $room_status = getRoomStatusFromDB($conn, $row);
        $is_occupied = ($room_status == 'Occupied');
?>
            <div class="col-md-4">
                <div class="card room-card h-100 shadow border-0 overflow-hidden">
                    <div class="position-relative">
                        <img src="public/<?php echo htmlspecialchars($row['foto_utama']); ?>"
                            class="card-img-top room-img" alt="<?php echo htmlspecialchars($row['nama_kamar']); ?>"
                            style="height: 250px; object-fit: cover;"
                            onerror="this.src='https://via.placeholder.com/400x300?text=Kamar+Kos'">
                        <div class="position-absolute top-0 end-0 m-3">
                            <span class="badge bg-white text-primary shadow-sm py-2 px-3 fw-bold rounded-pill">
                                Lantai
                                <?php echo $row['lantai']; ?>
                            </span>
                        </div>
                        <?php if ($is_occupied): ?>
                        <div class="position-absolute top-0 start-0 m-3">
                            <span class="badge bg-danger shadow-sm py-2 px-3 fw-bold rounded-pill">
                                <i class="fas fa-lock me-1"></i> Terisi
                            </span>
                        </div>
                        <?php
        endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column p-4">
                        <h5 class="card-title fw-bold mb-1">
                            <?php echo htmlspecialchars($row['nama_kamar']); ?>
                        </h5>
                        <p class="text-muted small mb-3"><i class="fas fa-map-marker-alt me-1"></i>
                            <?php echo htmlspecialchars($row['lokasi']); ?>
                        </p>

                        <div class="mb-3">
                            <small class="text-muted">
                                <?php echo htmlspecialchars(substr($row['deskripsi'], 0, 80)) . '...'; ?>
                            </small>
                        </div>

                        <?php if ($is_occupied): ?>
                        <div class="alert alert-warning small mb-3 py-2">
                            <i class="fas fa-info-circle me-1"></i> Kamar ini sudah terisi
                        </div>
                        <?php
        endif; ?>

                        <div class="mt-auto">
                            <h4 class="text-primary fw-bold mb-3">Rp
                                <?php echo number_format($row['harga_per_bulan']); ?> <small
                                    class="fs-6 text-muted fw-normal">/ bulan</small>
                            </h4>
                            <div class="d-grid gap-2">
                                <a href="users/detail_kamar.php?id=<?php echo $row['id_kamar']; ?>"
                                    class="btn btn-outline-primary rounded-pill">Lihat Detail</a>
                                <?php if ($is_occupied): ?>
                                <button class="btn btn-secondary rounded-pill" disabled>
                                    <i class="fas fa-lock me-2"></i>Tidak Tersedia
                                </button>
                                <?php
        else: ?>
                                <a href="users/pesan_kamar.php?id=<?php echo $row['id_kamar']; ?>"
                                    class="btn btn-primary rounded-pill">Pesan Sekarang</a>
                                <?php
        endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
    }
}
else {
    echo "<div class='col-12 text-center'><p>Tidak ada kamar tersedia.</p></div>";
}
?>
        </div>
    </div>

    <!-- Location Section -->
    <div class="container py-5 bg-light rounded-3 my-5">
        <div class="row align-items-center">
            <div class="col-md-5 mb-4 mb-md-0">
                <h2 class="fw-bold mb-3">Lokasi Strategis</h2>
                <p class="lead text-muted">Kami berlokasi di pusat kota, dekat dengan berbagai fasilitas umum.</p>
                <ul class="list-unstyled mt-4">
                    <li class="mb-3"><i class="fas fa-university me-3 text-primary fa-lg"></i> 3 Menit ke Universitas
                        Mataram (Unram)</li>
                    <li class="mb-3"><i class="fas fa-graduation-cap me-3 text-primary fa-lg"></i> 5 Menit ke
                        Universitas Teknologi Mataram (UTM)</li>
                    <li class="mb-3"><i class="fas fa-shopping-bag me-3 text-primary fa-lg"></i> 7 Menit ke Lombok
                        Epicentrum Mall</li>
                    <li class="mb-3"><i class="fas fa-hospital me-3 text-primary fa-lg"></i> 5 Menit ke RS Universitas
                        Mataram</li>
                    <li><i class="fas fa-utensils me-3 text-primary fa-lg"></i> Pusat Kuliner Kekalik & Gomong</li>
                </ul>
                <a href="https://maps.google.com" target="_blank"
                    class="btn btn-outline-primary rounded-pill mt-3 px-4">Buka di Google Maps <i
                        class="fas fa-external-link-alt ms-2"></i></a>
            </div>
            <div class="col-md-7">
                <div class="map-container shadow rounded">
                    <!-- Specific Address Map -->
                    <iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"
                        src="https://maps.google.com/maps?q=Jl.+Swadaya+No.15A,+Kekalik+Jaya,+Kec.+Mataram,+Kota+Mataram,+Nusa+Tenggara+Bar.+83115&t=&z=15&ie=UTF8&iwloc=&output=embed"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating WhatsApp Button -->
    <a href="https://wa.me/6282340371303?text=Halo%20Kos%20Berkah%20Malika,%20saya%20ingin%20bertanya%20tentang%20kamar..."
        class="whatsapp-float" target="_blank" title="Chat via WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>

    <!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="fw-bold text-primary mb-3"><i class="fas fa-home me-2"></i>Kos Berkah Malika</h4>
                    <p class="text-secondary">Penyedia layanan hunian kost terbaik dengan fasilitas lengkap dan harga
                        terjangkau. Kenyamanan Anda adalah prioritas kami.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">Tautan Cepat</h5>
                    <ul class="list-unstyled">
                        <li><a href="#rooms" class="footer-link">Daftar Kamar</a></li>
                        <li><a href="users/login.php" class="footer-link">Masuk</a></li>
                        <li><a href="users/daftar.php" class="footer-link">Daftar Akun</a></li>
                        <li><a href="#" class="footer-link">Syarat & Ketentuan</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">Hubungi Kami</h5>
                    <ul class="list-unstyled text-secondary">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> Jl. Swadaya No.15A, Kekalik Jaya,
                            Mataram</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> +62 823-4037-1303</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i>kosberkahmalika.com</li>
                    </ul>
                </div>
            </div>
            <hr class="border-secondary my-4">
            <div class="text-center text-secondary">
                <p class="mb-0">&copy;
                    <?php echo date('Y'); ?> Kos Berkah Malika. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>