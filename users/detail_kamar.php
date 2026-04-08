<?php
session_start();
require_once '../config/database.php';

$id = $_GET['id'] ?? 0;
// Use prepared statement for security
$stmt = $conn->prepare("SELECT * FROM kamar WHERE id_kamar = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    header("Location: ../index.php");
    exit();
}

// Check if room is occupied (dynamic logic)
function isRoomOccupied($conn, $room_id) {
    // 1. Check if manually set to 'perbaikan'
    $stmt = $conn->prepare("SELECT status_kamar FROM kamar WHERE id_kamar = ? AND status_kamar = 'perbaikan'");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return true;

    // 2. Check for active confirmed reservation
    $stmt = $conn->prepare("SELECT id_reservasi FROM reservasi WHERE id_kamar = ? AND status_reservasi = 'Dikonfirmasi' AND CURDATE() BETWEEN tanggal_masuk AND tanggal_keluar");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

$is_occupied = isRoomOccupied($conn, $room['id_kamar']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($room['nama_kamar']); ?> - Detail Kamar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .hero-section { position: relative; height: 500px; background-color: #000; overflow: hidden; }
        .hero-img { width: 100%; height: 100%; object-fit: cover; opacity: 0.9; }
        .hero-overlay { position: absolute; bottom: 0; left: 0; width: 100%; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 50px 0 20px 0; color: white; }
        
        .gallery-card { border: none; overflow: hidden; border-radius: 12px; transition: transform 0.3s; position: relative; }
        .gallery-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .gallery-img { height: 200px; object-fit: cover; width: 100%; cursor: pointer; transition: all 0.3s; }
        .gallery-img:hover { transform: scale(1.05); }
        .gallery-label { position: absolute; bottom: 10px; left: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; }
        
        /* Advanced Lightbox Styles */
        #imageModal .modal-dialog { max-width: 90vw; }
        #imageModal .modal-content { background: rgba(0, 0, 0, 0.95); backdrop-filter: blur(10px); }
        .lightbox-image { max-height: 70vh; width: auto; max-width: 100%; object-fit: contain; transition: transform 0.3s ease; }
        
        .lightbox-nav { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.3); color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; z-index: 10; }
        .lightbox-nav:hover { background: rgba(255,255,255,0.2); transform: translateY(-50%) scale(1.1); }
        .lightbox-nav.prev { left: 20px; }
        .lightbox-nav.next { right: 20px; }
        
        .image-counter { position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.6); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; }
        
        .thumbnail-container { display: flex; gap: 10px; justify-content: center; padding: 15px; flex-wrap: wrap; max-width: 600px; margin: 0 auto; }
        .thumbnail { width: 80px; height: 60px; object-fit: cover; border-radius: 8px; cursor: pointer; opacity: 0.5; transition: all 0.3s; border: 2px solid transparent; }
        .thumbnail:hover { opacity: 0.8; transform: scale(1.05); }
        .thumbnail.active { opacity: 1; border-color: #0d6efd; }
        
        @media (max-width: 768px) {
            .lightbox-nav { width: 40px; height: 40px; }
            .lightbox-nav.prev { left: 10px; }
            .lightbox-nav.next { right: 10px; }
            .image-counter { top: 10px; right: 10px; font-size: 0.8rem; padding: 6px 12px; }
        }
        
        .price-card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); position: sticky; top: 20px; }
        .feature-icon { width: 40px; height: 40px; background: #e7f1ff; color: #0d6efd; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin-right: 15px; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand text-primary fw-bold" href="../index.php"><i class="fas fa-home me-2"></i>Kos Berkah Malika</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <!-- Room Status Link -->
                    <li class="nav-item me-2">
                        <a class="btn btn-outline-primary rounded-pill px-3" href="status_kamar.php">
                            <i class="fas fa-door-open me-2"></i>Status Kamar
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../index.php" class="btn btn-primary rounded-pill px-3">
                            <i class="fas fa-arrow-left me-2"></i>Kembali
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section">
        <img src="../public/<?php echo htmlspecialchars($room['foto_utama']); ?>" class="hero-img" alt="Main Room Image" onerror="this.src='https://via.placeholder.com/1200x600'">
        <div class="hero-overlay">
            <div class="container">
                <h1 class="display-4 fw-bold"><?php echo htmlspecialchars($room['nama_kamar']); ?></h1>
                <p class="lead mb-0"><i class="fas fa-map-marker-alt me-2 text-warning"></i><?php echo htmlspecialchars($room['lokasi']); ?> &bull; Lantai <?php echo $room['lantai']; ?></p>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row">
            <!-- Left Column: Details & Gallery -->
            <div class="col-lg-8">
                <!-- Description -->
                <div class="bg-white p-4 rounded-3 shadow-sm mb-4">
                    <h3 class="fw-bold mb-3">Deskripsi Kamar</h3>
                    <p class="text-secondary" style="line-height: 1.8;"><?php echo nl2br(htmlspecialchars($room['deskripsi'])); ?></p>
                    
                    <div class="row mt-4">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="feature-icon"><i class="fas fa-wifi"></i></div>
                                <div><h6 class="mb-0">Fasilitas</h6><small class="text-muted">Termasuk Listrik & Air</small></div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                                <div><h6 class="mb-0">Keamanan</h6><small class="text-muted">CCTV 24 Jam</small></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Grid -->
                <h4 class="fw-bold mb-3">Galeri Foto</h4>
                <div class="row g-3" id="galleryGrid">
                    <?php 
                    $gallery = [
                        ['field' => 'foto_lemari',      'label' => 'Lemari Pakaian', 'icon' => 'fa-tshirt'],
                        ['field' => 'foto_kasur',       'label' => 'Tempat Tidur',   'icon' => 'fa-bed'],
                        ['field' => 'foto_dapur',       'label' => 'Dapur',          'icon' => 'fa-utensils'],
                        ['field' => 'foto_kamar_mandi', 'label' => 'Kamar Mandi',    'icon' => 'fa-bath'],
                        ['field' => 'foto_lainnya',     'label' => 'Lainnya',        'icon' => 'fa-image']
                    ];
                    
                    $imageIndex = 0;
                    foreach($gallery as $item): 
                        if (!empty($room[$item['field']])):
                    ?>
                    <div class="col-md-6">
                        <div class="gallery-card">
                            <img src="../public/<?php echo htmlspecialchars($room[$item['field']]); ?>" 
                                 class="gallery-img gallery-trigger" 
                                 data-index="<?php echo $imageIndex; ?>"
                                 data-src="../public/<?php echo htmlspecialchars($room[$item['field']]); ?>" 
                                 data-label="<?php echo $item['label']; ?>"
                                 alt="<?php echo $item['label']; ?>">
                            <div class="gallery-label"><i class="fas <?php echo $item['icon']; ?> me-1"></i> <?php echo $item['label']; ?></div>
                        </div>
                    </div>
                    <?php 
                            $imageIndex++;
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Right Column: Booking Card -->
            <div class="col-lg-4">
                <div class="card price-card">
                    <div class="card-body p-4">
                        <h5 class="text-muted mb-3">Mulai dari</h5>
                        <h2 class="text-primary fw-bold mb-4">Rp <?php echo number_format($room['harga_per_bulan']); ?> <small class="fs-6 text-muted">/ bulan</small></h2>
                        
                        <div class="list-group list-group-flush mb-4 small">
                            <?php // Weekly removed ?>
                            
                            <?php if($room['harga_per_bulan'] > 0): ?>
                            <div class="list-group-item d-flex justify-content-between px-0">
                                <span><i class="fas fa-calendar-alt me-2 text-warning"></i>Bulanan</span>
                                <strong>Rp <?php echo number_format($room['harga_per_bulan']); ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($room['harga_per_tahun'] > 0): ?>
                            <div class="list-group-item d-flex justify-content-between px-0">
                                <span><i class="fas fa-calendar-check me-2 text-success"></i>Tahunan</span>
                                <strong>Rp <?php echo number_format($room['harga_per_tahun']); ?></strong>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($is_occupied): ?>
                        <div class="alert alert-danger mb-3" style="border-radius: 15px;">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                                <div>
                                    <h6 class="mb-1 fw-bold">Maaf, Kamar Sudah Terisi</h6>
                                    <small>Kamar ini saat ini sedang ditempati. Silakan hubungi admin untuk informasi lebih lanjut.</small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid">
                            <?php if ($is_occupied): ?>
                            <button class="btn btn-secondary btn-lg" style="border-radius: 50px;" disabled>
                                <i class="fas fa-lock me-2"></i> Tidak Tersedia
                            </button>
                            <?php else: ?>
                            <a href="pesan_kamar.php?id=<?php echo $room['id_kamar']; ?>" class="btn btn-primary btn-lg" style="border-radius: 50px;">
                                <i class="fas fa-paper-plane me-2"></i> Pesan Sekarang
                            </a>
                            <?php endif; ?>
                            <a href="https://wa.me/6282340371303?text=Halo%20Admin,%20saya%20tertarik%20dengan%20<?php echo urlencode($room['nama_kamar']); ?>" target="_blank" class="btn btn-outline-success mt-2" style="border-radius: 50px;">
                                <i class="fab fa-whatsapp me-2"></i> Chat Admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Image Lightbox -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 position-relative">
                    <h5 class="modal-title text-white" id="imageModalLabel">Foto Kamar</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    <div class="image-counter" id="imageCounter">1 / 5</div>
                </div>
                
                <div class="modal-body text-center position-relative p-4">
                    <!-- Navigation Arrows -->
                    <div class="lightbox-nav prev" id="prevBtn">
                        <i class="fas fa-chevron-left"></i>
                    </div>
                    <div class="lightbox-nav next" id="nextBtn">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                    
                    <!-- Main Image -->
                    <img id="modalImage" src="" class="lightbox-image" alt="Room Image">
                </div>
                
                <!-- Thumbnail Gallery -->
                <div class="modal-footer border-0 pt-0">
                    <div class="thumbnail-container" id="thumbnailContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Kos Berkah Malika. Hak Cipta Dilindungi.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Advanced Lightbox Gallery
        let currentImageIndex = 0;
        let galleryImages = [];
        
        // Initialize gallery
        document.addEventListener('DOMContentLoaded', function() {
            // Collect all gallery images
            const triggers = document.querySelectorAll('.gallery-trigger');
            triggers.forEach((trigger, index) => {
                galleryImages.push({
                    src: trigger.dataset.src,
                    label: trigger.dataset.label,
                    thumb: trigger.src
                });
                
                // Add click event
                trigger.addEventListener('click', function() {
                    currentImageIndex = index;
                    openLightbox();
                });
            });
            
            // Navigation buttons
            document.getElementById('prevBtn').addEventListener('click', () => navigateImage(-1));
            document.getElementById('nextBtn').addEventListener('click', () => navigateImage(1));
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (document.getElementById('imageModal').classList.contains('show')) {
                    if (e.key === 'ArrowLeft') navigateImage(-1);
                    if (e.key === 'ArrowRight') navigateImage(1);
                    if (e.key === 'Escape') bootstrap.Modal.getInstance(document.getElementById('imageModal')).hide();
                }
            });
            
            // Generate thumbnails
            generateThumbnails();
        });
        
        function openLightbox() {
            updateLightbox();
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }
        
        function navigateImage(direction) {
            currentImageIndex += direction;
            
            // Loop around
            if (currentImageIndex < 0) currentImageIndex = galleryImages.length - 1;
            if (currentImageIndex >= galleryImages.length) currentImageIndex = 0;
            
            updateLightbox();
        }
        
        function updateLightbox() {
            const image = galleryImages[currentImageIndex];
            document.getElementById('modalImage').src = image.src;
            document.getElementById('imageModalLabel').textContent = image.label;
            document.getElementById('imageCounter').textContent = `${currentImageIndex + 1} / ${galleryImages.length}`;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach((thumb, index) => {
                thumb.classList.toggle('active', index === currentImageIndex);
            });
        }
        
        function generateThumbnails() {
            const container = document.getElementById('thumbnailContainer');
            container.innerHTML = '';
            
            galleryImages.forEach((image, index) => {
                const thumb = document.createElement('img');
                thumb.src = image.thumb;
                thumb.className = 'thumbnail' + (index === 0 ? ' active' : '');
                thumb.alt = image.label;
                thumb.addEventListener('click', () => {
                    currentImageIndex = index;
                    updateLightbox();
                });
                container.appendChild(thumb);
            });
        }
        
        // Touch swipe support for mobile
        let touchStartX = 0;
        let touchEndX = 0;
        
        document.getElementById('modalImage').addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        document.getElementById('modalImage').addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
        
        function handleSwipe() {
            if (touchEndX < touchStartX - 50) navigateImage(1); // Swipe left
            if (touchEndX > touchStartX + 50) navigateImage(-1); // Swipe right
        }
    </script>
</body>
</html>
