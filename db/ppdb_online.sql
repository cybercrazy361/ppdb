-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 10 Jan 2025 pada 15.03
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ppdb_online`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$3sZT7iKv4ieWNjpYsmz9kuzqATNARvQZZ4Y6iVR17BU29yZ.7c78e', '2024-12-12 11:52:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `calon_pendaftar`
--

CREATE TABLE `calon_pendaftar` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `asal_sekolah` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_hp` varchar(15) NOT NULL,
  `alamat` text NOT NULL,
  `pilihan` enum('SMA','SMK') NOT NULL,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Contacted','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `calon_pendaftar`
--

INSERT INTO `calon_pendaftar` (`id`, `nama`, `asal_sekolah`, `email`, `no_hp`, `alamat`, `pilihan`, `tanggal_daftar`, `status`, `notes`) VALUES
(1, 'Kartiko Setyoardi', 'SMP Mau Nanya', 'kartiko.ardhi.361@gmail.com', '082123979789', 'Jalan', 'SMA', '2024-12-13 13:45:19', 'Pending', NULL),
(2, 'samsul', 'SMP Al Azhar', 'kartiko.ardhi.361@gmail.com', '082123989878', 'Jalan K', 'SMK', '2024-12-17 14:32:35', 'Pending', NULL),
(3, 'Muhammad akbar', 'SMP Hidayah', 'kartiko.ardhi.361@gmail.com', '082123979789', 'jfj', 'SMA', '2024-12-19 06:47:06', 'Rejected', 'tidak jelas'),
(4, 'Kartiko bisa semua', 'SMP Hidayah', 'kartiko.ardhi.361@gmail.com', '082123979789', 'Jalan kabin', 'SMA', '2024-12-22 14:59:01', 'Pending', NULL),
(5, 'Jaka', 'SMP Al Azhar', 'kartiko.ardhi.361@gmail.com', '082123979789', 'H', 'SMA', '2024-12-22 15:03:52', 'Pending', NULL),
(6, 'Kartio', 'SMP Hidayah', 'kartiko.ardhi.361@gmail.com', '082123979789', 'Jak', 'SMA', '2024-12-22 16:23:31', 'Pending', NULL),
(7, 'surti', 'SMP Hidayah', 'kartiko.ardhi.361@gmail.com', '082123979789', 'K', 'SMA', '2024-12-22 16:45:41', 'Pending', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_pembayaran`
--

CREATE TABLE `jenis_pembayaran` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `unit` enum('Yayasan','SMA','SMK') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_pembayaran`
--

INSERT INTO `jenis_pembayaran` (`id`, `nama`, `unit`) VALUES
(4, 'Kegiatan', 'SMA'),
(10, 'Lain', 'SMA'),
(3, 'Seragam', 'SMA'),
(13, 'Seragam', 'SMK'),
(2, 'SPP', 'SMA'),
(12, 'SPP', 'SMK'),
(1, 'Uang Pangkal', 'SMA'),
(11, 'Uang Pangkal', 'SMK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `keuangan`
--

CREATE TABLE `keuangan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `unit` enum('Yayasan','SMA','SMK') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `keuangan`
--

INSERT INTO `keuangan` (`id`, `nama`, `username`, `password`, `unit`) VALUES
(1, 'Muhammad akbar Hara', 'Akbar', '$2y$10$lLCmeKxyBIgMugbgQnmRw.l5Aaxl8XqHRAp1Erg/bjPv4lGyRyVRG', 'SMA'),
(2, 'Supriyanto', 'supri', '$2y$10$hiKfZo6t56weUpGDwzll5O7pf5bHjR9Mj.TldCek2Lxmc8jgBepy2', 'SMK'),
(3, 'Yohanes Krisnawan', 'johan', '$2y$10$xIiAq.iIHa.Cat97t.Skp.nWd/MyIclNd8kz/jkUozkjccKvy.Gt2', 'SMA');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `no_formulir` varchar(20) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `metode_pembayaran` enum('Cash','Transfer') NOT NULL,
  `tahun_pelajaran` varchar(15) NOT NULL DEFAULT '2024/2025',
  `tanggal_pembayaran` date NOT NULL DEFAULT curdate(),
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `siswa_id`, `no_formulir`, `jumlah`, `metode_pembayaran`, `tahun_pelajaran`, `tanggal_pembayaran`, `keterangan`) VALUES
(2, 2, '12346', 2000000.00, 'Transfer', '2025/2026', '2024-12-18', ''),
(3, 7, '89890', 1500000.00, 'Transfer', '2025/2026', '2024-12-18', ''),
(5, 2, '12346', 100000.00, 'Cash', '2025/2026', '2024-12-19', '');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran_detail`
--

CREATE TABLE `pembayaran_detail` (
  `id` int(11) NOT NULL,
  `pembayaran_id` int(11) NOT NULL,
  `jenis_pembayaran_id` int(11) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `bulan` enum('Juli','Agustus','September','Oktober','November','Desember','Januari','Februari','Maret','April','Mei','Juni') DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `status_pembayaran` varchar(50) DEFAULT NULL,
  `angsuran_ke` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pembayaran_detail`
--

INSERT INTO `pembayaran_detail` (`id`, `pembayaran_id`, `jenis_pembayaran_id`, `jumlah`, `bulan`, `keterangan`, `status_pembayaran`, `angsuran_ke`) VALUES
(1, 2, 2, 1500000.00, 'Juli', NULL, 'Angsuran ke-1', 1),
(2, 2, 1, 500000.00, NULL, NULL, 'Angsuran ke-1', 1),
(5, 3, 12, 500000.00, 'Juli', NULL, 'Angsuran ke-1', 1),
(6, 3, 11, 1000000.00, NULL, NULL, 'Angsuran ke-1', 1),
(7, 5, 2, 100000.00, 'Juli', NULL, 'Angsuran ke-2', 2);

--
-- Trigger `pembayaran_detail`
--
DELIMITER $$
CREATE TRIGGER `before_pembayaran_detail_insert` BEFORE INSERT ON `pembayaran_detail` FOR EACH ROW BEGIN
    -- Validasi jumlah
    IF NEW.jumlah <= 0 THEN
        SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Jumlah harus lebih besar dari 0.';
    END IF;

    -- Validasi bulan hanya untuk jenis pembayaran SPP
    IF NEW.bulan IS NOT NULL THEN
        IF (SELECT nama FROM jenis_pembayaran WHERE id = NEW.jenis_pembayaran_id) != 'SPP' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Bulan hanya boleh diisi untuk pembayaran jenis SPP.';
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_pembayaran_detail_update` BEFORE UPDATE ON `pembayaran_detail` FOR EACH ROW BEGIN
    -- Validasi jumlah
    IF NEW.jumlah <= 0 THEN
        SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Jumlah harus lebih besar dari 0.';
    END IF;

    -- Validasi bulan hanya untuk jenis pembayaran SPP
    IF NEW.bulan IS NOT NULL THEN
        IF (SELECT nama FROM jenis_pembayaran WHERE id = NEW.jenis_pembayaran_id) != 'SPP' THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Bulan hanya boleh diisi untuk pembayaran jenis SPP.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_nominal`
--

CREATE TABLE `pengaturan_nominal` (
  `id` int(11) NOT NULL,
  `jenis_pembayaran_id` int(11) NOT NULL,
  `nominal_max` decimal(15,2) NOT NULL,
  `bulan` enum('Juli','Agustus','September','Oktober','November','Desember','Januari','Februari','Maret','April','Mei','Juni') DEFAULT NULL,
  `unit` enum('Yayasan','SMA','SMK') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan_nominal`
--

INSERT INTO `pengaturan_nominal` (`id`, `jenis_pembayaran_id`, `nominal_max`, `bulan`, `unit`, `created_at`, `updated_at`) VALUES
(1, 1, 4000000.00, NULL, 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:36:16'),
(3, 2, 2000000.00, 'Juli', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(4, 2, 2000000.00, 'Agustus', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(5, 2, 2000000.00, 'September', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(6, 2, 2000000.00, 'Oktober', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(7, 2, 2000000.00, 'November', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(8, 2, 2000000.00, 'Desember', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(9, 2, 2000000.00, 'Januari', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(10, 2, 2000000.00, 'Februari', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(11, 2, 2000000.00, 'Maret', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(12, 2, 2000000.00, 'April', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(13, 2, 2000000.00, 'Mei', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(14, 2, 2000000.00, 'Juni', 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(27, 3, 1500000.00, NULL, 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(29, 4, 1000000.00, NULL, 'SMA', '2024-12-17 16:02:14', '2024-12-17 16:02:14'),
(31, 11, 3000000.00, NULL, 'Yayasan', '2024-12-18 15:30:36', '2024-12-18 15:30:36'),
(32, 12, 1000000.00, 'Juli', 'Yayasan', '2024-12-18 15:30:52', '2024-12-18 15:30:52');

-- --------------------------------------------------------

--
-- Struktur dari tabel `periode`
--

CREATE TABLE `periode` (
  `id` int(11) NOT NULL,
  `nama_periode` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `periode`
--

INSERT INTO `periode` (`id`, `nama_periode`) VALUES
(1, 'Bulanan'),
(4, 'Sekali'),
(2, 'Semester'),
(3, 'Tahunan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `petugas`
--

CREATE TABLE `petugas` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `unit` enum('Yayasan','SMA','SMK') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `petugas`
--

INSERT INTO `petugas` (`id`, `nama`, `username`, `password`, `unit`) VALUES
(1, 'Masruri', 'ruri', '$2y$10$xJiJZoHh0KxOXLL8JzlR2uEE465i655Z/nR.0OKmW70VVNjN9gK0a', 'SMA'),
(2, 'Supri', 'supri', '$2y$10$Y/1oF/6PGlAvqf3/C746O.cRRhZp2GJK8yxlaLDWXX7uZ5q4UgsQ.', 'SMK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pimpinan`
--

CREATE TABLE `pimpinan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `unit` enum('Yayasan','SMA','SMK') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `receipt_layout`
--

CREATE TABLE `receipt_layout` (
  `id` int(11) NOT NULL,
  `element_name` varchar(50) NOT NULL,
  `x_position_mm` float NOT NULL DEFAULT 0,
  `y_position_mm` float NOT NULL DEFAULT 0,
  `paper_width_mm` float NOT NULL DEFAULT 80,
  `paper_height_mm` float NOT NULL DEFAULT 120,
  `font_size` float NOT NULL DEFAULT 12,
  `font_family` varchar(100) NOT NULL DEFAULT 'Nunito, sans-serif',
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `image_path` varchar(255) DEFAULT NULL,
  `watermark_text` varchar(255) DEFAULT NULL,
  `watermark_font` varchar(100) DEFAULT 'Arial, sans-serif',
  `watermark_size` float DEFAULT 24,
  `watermark_position_x` float DEFAULT 40,
  `watermark_position_y` float DEFAULT 60,
  `watermark_image_path` varchar(255) DEFAULT NULL,
  `unit` enum('Yayasan','SMA','SMK') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `receipt_layout`
--

INSERT INTO `receipt_layout` (`id`, `element_name`, `x_position_mm`, `y_position_mm`, `paper_width_mm`, `paper_height_mm`, `font_size`, `font_family`, `visible`, `image_path`, `watermark_text`, `watermark_font`, `watermark_size`, `watermark_position_x`, `watermark_position_y`, `watermark_image_path`, `unit`) VALUES
(1, 'logo', 8.6, 2.5, 229, 139, 0, '', 1, '../uploads/logos/ddf891bf07bd2d2146a3c75bad354f64.png', NULL, 'Arial, sans-serif', 24, 10, 10, NULL, 'SMA'),
(2, 'watermark', 42.2, 90.4, 229, 139, 24, 'Arial, sans-serif', 0, NULL, 'SMA DHARMA KARYA', 'Arial, sans-serif', 24, 40, 60, NULL, 'SMA'),
(3, 'header', 95, 3.8, 229, 139, 14, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 20, NULL, 'SMA'),
(4, 'no_formulir', 7.9, 22.9, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 35, NULL, 'SMA'),
(5, 'nama', 8.2, 30, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 50, NULL, 'SMA'),
(6, 'unit', 170, 4.4, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 65, NULL, 'SMA'),
(7, 'tahun_pelajaran', 170, 12.6, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 80, NULL, 'SMA'),
(8, 'jumlah', 9.8, 83.6, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 95, NULL, 'SMA'),
(9, 'metode_pembayaran', 170, 20.8, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 110, NULL, 'SMA'),
(10, 'tanggal_pembayaran', 7.7, 37.3, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 125, NULL, 'SMA'),
(11, 'keterangan', 9.3, 94.5, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 140, NULL, 'SMA'),
(12, 'details', 8.1, 41.6, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 155, NULL, 'SMA'),
(13, 'footer', 140.1, 81.6, 229, 139, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 170, NULL, 'SMA'),
(14, 'logo', 10, 10, 80, 120, 0, '', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 10, NULL, 'SMK'),
(15, 'watermark', 40, 60, 80, 120, 24, 'Arial, sans-serif', 0, NULL, 'CONFIDENTIAL', 'Arial, sans-serif', 24, 40, 60, NULL, 'SMK'),
(16, 'header', 10, 20, 80, 120, 14, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 20, NULL, 'SMK'),
(17, 'no_formulir', 10, 35, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 35, NULL, 'SMK'),
(18, 'nama', 10, 50, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 50, NULL, 'SMK'),
(19, 'unit', 10, 65, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 65, NULL, 'SMK'),
(20, 'tahun_pelajaran', 10, 80, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 80, NULL, 'SMK'),
(21, 'jumlah', 10, 95, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 95, NULL, 'SMK'),
(22, 'metode_pembayaran', 10, 110, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 110, NULL, 'SMK'),
(23, 'tanggal_pembayaran', 10, 125, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 125, NULL, 'SMK'),
(24, 'keterangan', 10, 140, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 140, NULL, 'SMK'),
(25, 'details', 10, 155, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 155, NULL, 'SMK'),
(26, 'footer', 10, 170, 80, 120, 12, 'Nunito, sans-serif', 1, NULL, NULL, 'Arial, sans-serif', 24, 10, 170, NULL, 'SMK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `no_formulir` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `unit` enum('Yayasan','SMA','SMK') NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') NOT NULL,
  `tempat_lahir` varchar(50) NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `asal_sekolah` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `no_hp` varchar(15) NOT NULL,
  `status_pembayaran` enum('Pending','Angsuran','Lunas') NOT NULL DEFAULT 'Pending',
  `metode_pembayaran` enum('Cash','Transfer') DEFAULT NULL,
  `tanggal_pendaftaran` date NOT NULL DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `siswa`
--

INSERT INTO `siswa` (`id`, `no_formulir`, `nama`, `unit`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `asal_sekolah`, `alamat`, `no_hp`, `status_pembayaran`, `metode_pembayaran`, `tanggal_pendaftaran`, `created_at`, `updated_at`) VALUES
(2, '12346', 'Supri', 'SMA', 'Laki-laki', 'Jakarta', '2021-09-09', 'SMP Al Azhar', 'Jalan Jian', '082123989878', 'Pending', '', '2024-12-12', '2024-12-12 12:55:39', '2024-12-12 13:18:29'),
(3, '12345', 'Muhammad akbar', 'SMA', 'Laki-laki', 'Jakarta', '2021-10-10', 'SMP Hidayah', 'Jalan Makmur', '082123989878', 'Pending', '', '2024-12-12', '2024-12-12 12:56:20', '2024-12-12 14:23:09'),
(4, '54321', 'Samsul bahar', 'SMA', 'Laki-laki', 'Jakarta', '2009-10-09', 'SMP Kaisar', 'Jalan Bojong', '082123989878', 'Pending', NULL, '2024-12-13', '2024-12-13 12:21:46', '2024-12-13 12:21:46'),
(5, '98123', 'Joko', 'SMA', 'Laki-laki', 'Jakarta', '2018-10-10', 'SMP Hidayah', 'Jail', '082123989878', 'Pending', NULL, '2024-12-13', '2024-12-13 13:44:23', '2024-12-15 14:36:13'),
(6, '98789', 'Joko', 'SMK', 'Laki-laki', 'Jakarta', '2017-10-16', 'SMP Al Azhar', 'Jalan B', '082123989878', 'Pending', NULL, '2024-12-18', '2024-12-18 14:51:01', '2024-12-18 14:51:01'),
(7, '89890', 'Marni', 'SMK', 'Laki-laki', 'Jakarta', '2014-10-15', 'SMP Hidayah', 'Jalan C', '082123979789', 'Pending', NULL, '2024-12-18', '2024-12-18 14:51:40', '2024-12-18 14:51:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tahun_pelajaran`
--

CREATE TABLE `tahun_pelajaran` (
  `id` int(11) NOT NULL,
  `tahun` varchar(9) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tahun_pelajaran`
--

INSERT INTO `tahun_pelajaran` (`id`, `tahun`) VALUES
(1, '2023/2024'),
(2, '2024/2025'),
(3, '2025/2026');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `calon_pendaftar`
--
ALTER TABLE `calon_pendaftar`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `jenis_pembayaran`
--
ALTER TABLE `jenis_pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_nama_unit` (`nama`,`unit`);

--
-- Indeks untuk tabel `keuangan`
--
ALTER TABLE `keuangan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `no_formulir` (`no_formulir`);

--
-- Indeks untuk tabel `pembayaran_detail`
--
ALTER TABLE `pembayaran_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pembayaran_id` (`pembayaran_id`),
  ADD KEY `jenis_pembayaran_id` (`jenis_pembayaran_id`);

--
-- Indeks untuk tabel `pengaturan_nominal`
--
ALTER TABLE `pengaturan_nominal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jp_bulan_unit` (`jenis_pembayaran_id`,`bulan`,`unit`);

--
-- Indeks untuk tabel `periode`
--
ALTER TABLE `periode`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_periode` (`nama_periode`);

--
-- Indeks untuk tabel `petugas`
--
ALTER TABLE `petugas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `pimpinan`
--
ALTER TABLE `pimpinan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `receipt_layout`
--
ALTER TABLE `receipt_layout`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_layout` (`element_name`,`unit`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_formulir` (`no_formulir`);

--
-- Indeks untuk tabel `tahun_pelajaran`
--
ALTER TABLE `tahun_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tahun` (`tahun`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `calon_pendaftar`
--
ALTER TABLE `calon_pendaftar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `jenis_pembayaran`
--
ALTER TABLE `jenis_pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `keuangan`
--
ALTER TABLE `keuangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `pembayaran_detail`
--
ALTER TABLE `pembayaran_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_nominal`
--
ALTER TABLE `pengaturan_nominal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT untuk tabel `periode`
--
ALTER TABLE `periode`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `petugas`
--
ALTER TABLE `petugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `pimpinan`
--
ALTER TABLE `pimpinan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `receipt_layout`
--
ALTER TABLE `receipt_layout`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `tahun_pelajaran`
--
ALTER TABLE `tahun_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`no_formulir`) REFERENCES `siswa` (`no_formulir`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembayaran_detail`
--
ALTER TABLE `pembayaran_detail`
  ADD CONSTRAINT `pembayaran_detail_ibfk_1` FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pembayaran_detail_ibfk_2` FOREIGN KEY (`jenis_pembayaran_id`) REFERENCES `jenis_pembayaran` (`id`);

--
-- Ketidakleluasaan untuk tabel `pengaturan_nominal`
--
ALTER TABLE `pengaturan_nominal`
  ADD CONSTRAINT `pengaturan_nominal_ibfk_1` FOREIGN KEY (`jenis_pembayaran_id`) REFERENCES `jenis_pembayaran` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
