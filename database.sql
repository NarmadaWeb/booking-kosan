-- =============================================
-- DATABASE: db_berkahmalika
-- Sistem Informasi Reservasi dan Pembayaran Kos
-- Kos Berkah Malika
-- Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram, NTB
-- =============================================

CREATE DATABASE IF NOT EXISTS db_berkahmalika;
USE db_berkahmalika;

-- =============================================
-- TABEL 1: pengguna
-- Menyimpan data admin dan penyewa
-- =============================================
CREATE TABLE IF NOT EXISTS pengguna (
    id_pengguna INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL UNIQUE,
    nama_lengkap VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    no_hp VARCHAR(20) DEFAULT NULL,
    kata_sandi VARCHAR(255) NOT NULL,
    peran ENUM('admin','penyewa') NOT NULL DEFAULT 'penyewa',
    foto_profil VARCHAR(255) DEFAULT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pengguna)
);

-- =============================================
-- TABEL 2: kamar
-- Menyimpan data kamar kos yang tersedia
-- =============================================
CREATE TABLE IF NOT EXISTS kamar (
    id_kamar INT(11) NOT NULL AUTO_INCREMENT,
    nama_kamar VARCHAR(255) NOT NULL,
    tipe_kamar VARCHAR(100) DEFAULT NULL,
    luas_kamar VARCHAR(50) DEFAULT NULL,
    lokasi VARCHAR(255) DEFAULT NULL,
    deskripsi TEXT DEFAULT NULL,
    foto_utama VARCHAR(255) DEFAULT NULL,
    foto_lemari VARCHAR(255) DEFAULT NULL,
    foto_kasur VARCHAR(255) DEFAULT NULL,
    foto_dapur VARCHAR(255) DEFAULT NULL,
    foto_kamar_mandi VARCHAR(255) DEFAULT NULL,
    foto_lainnya VARCHAR(255) DEFAULT NULL,
    harga_per_bulan DECIMAL(15,2) DEFAULT 0.00,
    harga_per_tahun DECIMAL(15,2) DEFAULT 0.00,
    lantai INT(11) DEFAULT 1,
    status_kamar ENUM('tersedia','terisi','perbaikan') DEFAULT 'tersedia',
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_kamar)
);

-- =============================================
-- TABEL 3: reservasi
-- Menyimpan data pemesanan kamar oleh penyewa
-- =============================================
CREATE TABLE IF NOT EXISTS reservasi (
    id_reservasi INT(11) NOT NULL AUTO_INCREMENT,
    id_kamar INT(11) NOT NULL,
    id_pengguna INT(11) NOT NULL,
    nama_pemesan VARCHAR(255) NOT NULL,
    email_pemesan VARCHAR(255) DEFAULT NULL,
    no_hp_pemesan VARCHAR(20) DEFAULT NULL,
    tanggal_masuk DATE NOT NULL,
    tanggal_keluar DATE NOT NULL,
    durasi_sewa VARCHAR(50) DEFAULT NULL,
    jumlah_tamu INT(11) NOT NULL DEFAULT 1,
    total_harga DECIMAL(15,2) DEFAULT 0.00,
    status_reservasi ENUM('Menunggu Pembayaran','Menunggu','Dikonfirmasi','Dibatalkan','Selesai')
        DEFAULT 'Menunggu',
    metode_pembayaran VARCHAR(50) DEFAULT 'Tunai',
    detail_pembayaran TEXT DEFAULT NULL,
    bukti_pembayaran VARCHAR(255) DEFAULT NULL,
    catatan TEXT DEFAULT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_reservasi),
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar) ON DELETE CASCADE,
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE CASCADE
);

-- =============================================
-- TABEL 4: pembayaran
-- Menyimpan data transaksi pembayaran via Midtrans
-- =============================================
CREATE TABLE IF NOT EXISTS pembayaran (
    id_pembayaran INT(11) NOT NULL AUTO_INCREMENT,
    id_reservasi INT(11) NOT NULL,
    kode_pesanan VARCHAR(255) NOT NULL UNIQUE,
    id_transaksi VARCHAR(255) DEFAULT NULL,
    jumlah_bayar DECIMAL(15,2) NOT NULL,
    jenis_pembayaran VARCHAR(100) DEFAULT NULL,
    status_transaksi VARCHAR(100) DEFAULT 'menunggu',
    status_penipuan VARCHAR(100) DEFAULT NULL,
    pesan_status TEXT DEFAULT NULL,
    token_snap VARCHAR(255) DEFAULT NULL,
    url_pembayaran TEXT DEFAULT NULL,
    dibayar_pada TIMESTAMP NULL DEFAULT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_pembayaran),
    FOREIGN KEY (id_reservasi) REFERENCES reservasi(id_reservasi)
        ON DELETE CASCADE
);

-- =============================================
-- TABEL 5: data_penyewa
-- Menyimpan data lengkap penyewa yang aktif menghuni
-- =============================================
CREATE TABLE IF NOT EXISTS data_penyewa (
    id_penyewa INT(11) NOT NULL AUTO_INCREMENT,
    id_reservasi INT(11) NOT NULL,
    id_pengguna INT(11) NOT NULL,
    id_kamar INT(11) NOT NULL,
    no_ktp VARCHAR(50) DEFAULT NULL,
    alamat_asal TEXT DEFAULT NULL,
    pekerjaan VARCHAR(255) DEFAULT NULL,
    tanggal_masuk DATE NOT NULL,
    tanggal_keluar DATE DEFAULT NULL,
    status_huni ENUM('aktif','sudah keluar') DEFAULT 'aktif',
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    diperbarui_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_penyewa),
    FOREIGN KEY (id_reservasi) REFERENCES reservasi(id_reservasi)
        ON DELETE CASCADE,
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna)
        ON DELETE CASCADE,
    FOREIGN KEY (id_kamar) REFERENCES kamar(id_kamar)
        ON DELETE CASCADE
);

-- =============================================
-- TABEL 6: notifikasi
-- Menyimpan notifikasi sistem kepada pengguna
-- =============================================
CREATE TABLE IF NOT EXISTS notifikasi (
    id_notifikasi INT(11) NOT NULL AUTO_INCREMENT,
    id_pengguna INT(11) NOT NULL,
    judul VARCHAR(255) NOT NULL,
    pesan TEXT NOT NULL,
    jenis ENUM('info','pembayaran','reservasi','sistem') DEFAULT 'info',
    status_baca ENUM('belum dibaca','sudah dibaca') DEFAULT 'belum dibaca',
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_notifikasi),
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna)
        ON DELETE CASCADE
);

-- =============================================
-- TABEL 7: laporan_keuangan
-- Menyimpan laporan keuangan bulanan kos
-- =============================================
CREATE TABLE IF NOT EXISTS laporan_keuangan (
    id_laporan INT(11) NOT NULL AUTO_INCREMENT,
    bulan INT(2) NOT NULL,
    tahun INT(4) NOT NULL,
    total_pendapatan DECIMAL(15,2) DEFAULT 0.00,
    total_reservasi INT(11) DEFAULT 0,
    total_kamar_terisi INT(11) DEFAULT 0,
    total_kamar_kosong INT(11) DEFAULT 0,
    keterangan TEXT DEFAULT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_laporan)
);

-- =============================================
-- DATA AWAL: pengguna
-- =============================================
INSERT INTO pengguna (username, nama_lengkap, email, no_hp, kata_sandi, peran) VALUES
('admin', 'Administrator', 'admin@berkahmalika.com', '081234567890',
 '$2y$12$aZnG4Na2gcqVMD51iSBD.OyR7XlnUBx5JftfikfYnk0QeUvjSnVoy', 'admin'),
('udin', 'Udin Saputra', 'udin@gmail.com', '082345678901',
 '$2y$12$Fcp2/zjOM53niHbUBgy4vOzhlnz/B2n9HhjnD1WD/3dh8GlqjvzQO', 'penyewa'),
('deo123', 'Deo Pratama', 'deo@gmail.com', '083456789012',
 '$2y$12$examplehashfordeo123456789abcdef', 'penyewa'),
('bagas123', 'Bagas Ramadhan', 'bagas@gmail.com', '084567890123',
 '$2y$12$/vYJKFKD1OMhFLDozTvivuic6Fr3PItEtYOc1tdyZq0NU.vtSPxZG', 'penyewa');

-- =============================================
-- DATA AWAL: kamar (15 kamar)
-- =============================================
INSERT INTO kamar (nama_kamar, tipe_kamar, luas_kamar, lokasi, deskripsi,
foto_utama, foto_lemari, foto_kasur, foto_dapur, foto_kamar_mandi, foto_lainnya,
harga_per_bulan, harga_per_tahun, lantai, status_kamar) VALUES
('Kamar Kos 1', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap. Berlokasi strategis dekat transportasi umum.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 1, 'tersedia'),

('Kamar Kos 2', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 1, 'terisi'),

('Kamar Kos 3', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 1, 'tersedia'),

('Kamar Kos 4', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 1, 'tersedia'),

('Kamar Kos 5', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 1, 'tersedia'),

('Kamar Kos 6', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 1, 'tersedia'),

('Kamar Kos 7', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 1, 'terisi'),

('Kamar Kos 8', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'tersedia'),

('Kamar Kos 9', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'tersedia'),

('Kamar Kos 10', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'tersedia'),

('Kamar Kos 11', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'tersedia'),

('Kamar Kos 12', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'tersedia'),

('Kamar Kos 13', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'tersedia'),

('Kamar Kos 14', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'terisi'),

('Kamar Kos 15', 'Standar', '3x4 m',
 'Jl. Swadaya No.15 Kekalik Jaya, Sekarbela, Mataram',
 'Kamar kos yang nyaman dengan fasilitas lengkap.',
 'image/4.jpg','image/lemari.jpg','image/5.jpg','image/dapur.jpg','image/mandi.jpg','image/3.jpg',
 700000.00, 7000000.00, 2, 'tersedia');

-- =============================================
-- DATA AWAL: reservasi
-- =============================================
INSERT INTO reservasi (id_kamar, id_pengguna, nama_pemesan, email_pemesan,
no_hp_pemesan, tanggal_masuk, tanggal_keluar, durasi_sewa, jumlah_tamu,
total_harga, status_reservasi, metode_pembayaran) VALUES
(2,  2, 'Udin Saputra',  'udin@gmail.com',  '082345678901',
 '2025-09-24', '2025-10-24', '1 Bulan', 1, 700000.00,  'Dikonfirmasi', 'Midtrans'),
(14, 3, 'Deo Pratama',   'deo@gmail.com',   '083456789012',
 '2025-09-27', '2026-09-27', '1 Tahun', 1, 7000000.00, 'Dikonfirmasi', 'Midtrans'),
(7,  2, 'Udin Saputra',  'udin@gmail.com',  '082345678901',
 '2025-09-27', '2025-10-27', '1 Bulan', 1, 700000.00,  'Dikonfirmasi', 'Midtrans');

-- =============================================
-- DATA AWAL: pembayaran
-- =============================================
INSERT INTO pembayaran (id_reservasi, kode_pesanan, jumlah_bayar,
jenis_pembayaran, status_transaksi, dibayar_pada) VALUES
(1, 'BM-2025-001', 700000.00,  'transfer_bank', 'lunas', '2025-09-24 09:00:00'),
(2, 'BM-2025-002', 7000000.00, 'transfer_bank', 'lunas', '2025-09-27 13:00:00'),
(3, 'BM-2025-003', 700000.00,  'transfer_bank', 'lunas', '2025-09-27 13:10:00');

-- =============================================
-- DATA AWAL: data_penyewa
-- =============================================
INSERT INTO data_penyewa (id_reservasi, id_pengguna, id_kamar,
tanggal_masuk, tanggal_keluar, status_huni) VALUES
(1, 2, 2,  '2025-09-24', '2025-10-24', 'aktif'),
(2, 3, 14, '2025-09-27', '2026-09-27', 'aktif'),
(3, 2, 7,  '2025-09-27', '2025-10-27', 'aktif');

-- =============================================
-- DATA AWAL: notifikasi
-- =============================================
INSERT INTO notifikasi (id_pengguna, judul, pesan, jenis, status_baca) VALUES
(2, 'Reservasi Dikonfirmasi',
 'Reservasi Kamar Kos 2 Anda telah dikonfirmasi oleh admin. Selamat datang di Kos Berkah Malika!',
 'reservasi', 'sudah dibaca'),
(3, 'Reservasi Dikonfirmasi',
 'Reservasi Kamar Kos 14 Anda telah dikonfirmasi oleh admin. Selamat datang di Kos Berkah Malika!',
 'reservasi', 'sudah dibaca'),
(2, 'Pembayaran Berhasil',
 'Pembayaran sebesar Rp 2.000.000 untuk Kamar Kos 7 telah berhasil diterima. Terima kasih!',
 'pembayaran', 'belum dibaca'),
(1, 'Reservasi Baru Masuk',
 'Terdapat reservasi baru dari Udin Saputra untuk Kamar Kos 7. Silakan konfirmasi.',
 'sistem', 'sudah dibaca');

-- =============================================
-- DATA AWAL: laporan_keuangan
-- =============================================
INSERT INTO laporan_keuangan (bulan, tahun, total_pendapatan,
total_reservasi, total_kamar_terisi, total_kamar_kosong, keterangan) VALUES
(9, 2025, 7700000.00, 3, 3, 12, 'Laporan keuangan bulan September 2025');