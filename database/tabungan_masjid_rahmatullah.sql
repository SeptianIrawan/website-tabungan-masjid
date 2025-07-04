-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for tabungan_masjid_rahmatullah
CREATE DATABASE IF NOT EXISTS `tabungan_masjid_rahmatullah` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `tabungan_masjid_rahmatullah`;

-- Dumping structure for table tabungan_masjid_rahmatullah.pengguna
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id_pengguna` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(50) DEFAULT NULL,
  `role` varchar(10) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_pengguna`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table tabungan_masjid_rahmatullah.pengguna: ~9 rows (approximately)
REPLACE INTO `pengguna` (`id_pengguna`, `nama`, `role`, `email`, `password`, `created_at`) VALUES
	(16, 'septian irawan', 'admin', 'septianirawan95@gmail.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-24 11:34:07'),
	(17, 'Budi Santoso', 'user', 'budi.s@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:00:58'),
	(18, 'Citra Lestari', 'user', 'citra.l@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:00:58'),
	(19, 'Doni Firmansyah', 'user', 'doni.f@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:00:58'),
	(20, 'Eka Putri', 'user', 'eka.p@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:00:58'),
	(21, 'Budi Santoso', 'user', 'budi.s@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:04:04'),
	(22, 'Citra Lestari', 'user', 'citra.l@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:04:04'),
	(23, 'Doni Firmansyah', 'user', 'doni.f@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:04:04'),
	(24, 'Eka Putri', 'user', 'eka.p@example.com', '$2y$10$4JZNe9kzZvb4Cs.TIX/Z3e1rJElAE/by64.3jIB1yQ2eS4UH3kqjq', '2025-06-26 04:04:04');

-- Dumping structure for table tabungan_masjid_rahmatullah.tabungan
CREATE TABLE IF NOT EXISTS `tabungan` (
  `id_tabungan` int NOT NULL AUTO_INCREMENT,
  `id_pengguna` int DEFAULT NULL,
  `jumlah_tabungan` int DEFAULT NULL,
  `tanggal_setor` date DEFAULT NULL,
  `keterangan` text,
  PRIMARY KEY (`id_tabungan`),
  KEY `id_pengguna` (`id_pengguna`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table tabungan_masjid_rahmatullah.tabungan: ~5 rows (approximately)
REPLACE INTO `tabungan` (`id_tabungan`, `id_pengguna`, `jumlah_tabungan`, `tanggal_setor`, `keterangan`) VALUES
	(11, 16, 1150000, '2025-06-24', 'Tabungan Septian'),
	(12, 17, 1665000, '2025-06-26', 'Tabungan Budi'),
	(13, 18, 2845000, '2025-06-26', 'Tabungan Citra'),
	(14, 19, 2560000, '2025-06-26', 'Tabungan Doni'),
	(15, 20, 2270000, '2025-06-26', 'Tabungan Eka');

-- Dumping structure for table tabungan_masjid_rahmatullah.transaksi
CREATE TABLE IF NOT EXISTS `transaksi` (
  `id_transaksi` int NOT NULL AUTO_INCREMENT,
  `id_tabungan` int DEFAULT NULL,
  `id_pengguna` int DEFAULT NULL,
  `jenis` enum('masuk','keluar') NOT NULL,
  `jumlah` int NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `keterangan` text,
  PRIMARY KEY (`id_transaksi`),
  KEY `id_tabungan` (`id_tabungan`),
  KEY `id_pengguna` (`id_pengguna`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table tabungan_masjid_rahmatullah.transaksi: ~92 rows (approximately)
REPLACE INTO `transaksi` (`id_transaksi`, `id_tabungan`, `id_pengguna`, `jenis`, `jumlah`, `tanggal`, `keterangan`) VALUES
	(17, 11, 16, 'masuk', 150000, '2025-06-24 00:00:00', 'Setoran awal - cash'),
	(18, 12, 17, 'masuk', 75000, '2025-05-27 11:00:58', 'Infaq Jumat'),
	(19, 13, 18, 'masuk', 125000, '2025-05-27 11:00:58', 'Sedekah Subuh'),
	(20, 14, 19, 'masuk', 50000, '2025-05-27 11:00:58', 'Donasi Anak Yatim'),
	(21, 15, 20, 'masuk', 50000, '2025-05-27 11:00:58', 'Sumbangan Pembangunan'),
	(22, 12, 17, 'masuk', 100000, '2025-05-28 11:00:58', 'Donasi'),
	(23, 13, 18, 'masuk', 150000, '2025-05-28 11:00:58', 'Infaq'),
	(24, 14, 19, 'keluar', 25000, '2025-05-28 11:00:58', 'Biaya Operasional'),
	(25, 15, 20, 'masuk', 80000, '2025-05-28 11:00:58', 'Sedekah'),
	(26, 12, 17, 'masuk', 60000, '2025-05-29 11:00:58', 'Sumbangan'),
	(27, 13, 18, 'masuk', 110000, '2025-05-29 11:00:58', 'Infaq'),
	(28, 14, 19, 'masuk', 130000, '2025-05-29 11:00:58', 'Donasi'),
	(29, 12, 17, 'masuk', 90000, '2025-06-21 11:00:58', 'Infaq'),
	(30, 13, 18, 'masuk', 160000, '2025-06-21 11:00:58', 'Sedekah'),
	(31, 15, 20, 'masuk', 70000, '2025-06-21 11:00:58', 'Donasi'),
	(32, 12, 17, 'keluar', 50000, '2025-06-22 11:00:58', 'Pembelian Karpet'),
	(33, 14, 19, 'masuk', 200000, '2025-06-22 11:00:58', 'Donasi Pembangunan'),
	(34, 15, 20, 'masuk', 150000, '2025-06-22 11:00:58', 'Infaq'),
	(35, 13, 18, 'masuk', 100000, '2025-06-23 11:00:58', 'Sedekah'),
	(36, 14, 19, 'masuk', 100000, '2025-06-23 11:00:58', 'Infaq'),
	(37, 15, 20, 'masuk', 100000, '2025-06-23 11:00:58', 'Sumbangan'),
	(38, 12, 17, 'masuk', 120000, '2025-06-24 11:00:58', 'Donasi'),
	(39, 13, 18, 'masuk', 80000, '2025-06-24 11:00:58', 'Infaq'),
	(40, 15, 20, 'keluar', 40000, '2025-06-24 11:00:58', 'Biaya Listrik'),
	(41, 12, 17, 'masuk', 50000, '2025-06-25 11:00:58', 'Sedekah'),
	(42, 14, 19, 'masuk', 250000, '2025-06-25 11:00:58', 'Infaq Jumat'),
	(43, 13, 18, 'masuk', 150000, '2025-06-26 11:00:58', 'Donasi'),
	(44, 15, 20, 'masuk', 150000, '2025-06-26 11:00:58', 'Sumbangan'),
	(45, 12, 17, 'masuk', 75000, '2025-05-27 11:06:12', 'Infaq Jumat'),
	(46, 13, 18, 'masuk', 125000, '2025-05-27 11:06:12', 'Sedekah Subuh'),
	(47, 14, 19, 'masuk', 50000, '2025-05-27 11:06:12', 'Donasi Anak Yatim'),
	(48, 15, 20, 'masuk', 50000, '2025-05-27 11:06:12', 'Sumbangan Pembangunan'),
	(49, 12, 17, 'masuk', 100000, '2025-05-28 11:06:12', 'Donasi'),
	(50, 13, 18, 'masuk', 150000, '2025-05-28 11:06:12', 'Infaq'),
	(51, 14, 19, 'keluar', 25000, '2025-05-28 11:06:12', 'Biaya Operasional'),
	(52, 15, 20, 'masuk', 80000, '2025-05-28 11:06:12', 'Sedekah'),
	(53, 12, 17, 'masuk', 60000, '2025-05-29 11:06:12', 'Sumbangan'),
	(54, 13, 18, 'masuk', 110000, '2025-05-29 11:06:12', 'Infaq'),
	(55, 14, 19, 'masuk', 130000, '2025-05-29 11:06:12', 'Donasi'),
	(56, 12, 17, 'masuk', 90000, '2025-06-21 11:06:12', 'Infaq'),
	(57, 13, 18, 'masuk', 160000, '2025-06-21 11:06:12', 'Sedekah'),
	(58, 15, 20, 'masuk', 70000, '2025-06-21 11:06:12', 'Donasi'),
	(59, 12, 17, 'keluar', 50000, '2025-06-22 11:06:12', 'Pembelian Karpet'),
	(60, 14, 19, 'masuk', 200000, '2025-06-22 11:06:12', 'Donasi Pembangunan'),
	(61, 15, 20, 'masuk', 150000, '2025-06-22 11:06:12', 'Infaq'),
	(62, 13, 18, 'masuk', 100000, '2025-06-23 11:06:12', 'Sedekah'),
	(63, 14, 19, 'masuk', 100000, '2025-06-23 11:06:12', 'Infaq'),
	(64, 15, 20, 'masuk', 100000, '2025-06-23 11:06:12', 'Sumbangan'),
	(65, 12, 17, 'masuk', 120000, '2025-06-24 11:06:12', 'Donasi'),
	(66, 13, 18, 'masuk', 80000, '2025-06-24 11:06:12', 'Infaq'),
	(67, 15, 20, 'keluar', 40000, '2025-06-24 11:06:12', 'Biaya Listrik'),
	(68, 12, 17, 'masuk', 50000, '2025-06-25 11:06:12', 'Sedekah'),
	(69, 14, 19, 'masuk', 250000, '2025-06-25 11:06:12', 'Infaq Jumat'),
	(70, 13, 18, 'masuk', 150000, '2025-06-26 11:06:12', 'Donasi'),
	(71, 15, 20, 'masuk', 150000, '2025-06-26 11:06:12', 'Sumbangan'),
	(72, 12, 17, 'masuk', 110000, '2025-06-01 10:00:00', 'Donasi Awal Bulan'),
	(73, 15, 20, 'masuk', 100000, '2025-06-01 11:00:00', 'Infaq'),
	(74, 13, 18, 'masuk', 150000, '2025-06-02 09:30:00', 'Sedekah'),
	(75, 14, 19, 'masuk', 60000, '2025-06-02 14:00:00', 'Sumbangan'),
	(76, 12, 17, 'masuk', 50000, '2025-06-03 16:00:00', 'Infaq'),
	(77, 15, 20, 'masuk', 160000, '2025-06-03 18:00:00', 'Donasi'),
	(78, 13, 18, 'keluar', 30000, '2025-06-04 11:00:00', 'Biaya Kebersihan'),
	(79, 14, 19, 'masuk', 240000, '2025-06-04 12:00:00', 'Infaq Pembangunan'),
	(80, 12, 17, 'masuk', 80000, '2025-06-05 08:00:00', 'Sedekah'),
	(81, 15, 20, 'masuk', 130000, '2025-06-05 15:00:00', 'Donasi'),
	(82, 13, 18, 'masuk', 210000, '2025-06-06 13:00:00', 'Infaq Jumat Berkah'),
	(83, 14, 19, 'masuk', 100000, '2025-06-07 19:00:00', 'Sumbangan'),
	(84, 15, 20, 'masuk', 110000, '2025-06-07 20:00:00', 'Infaq'),
	(85, 12, 17, 'masuk', 150000, '2025-06-08 09:00:00', 'Donasi'),
	(86, 13, 18, 'masuk', 60000, '2025-06-08 10:00:00', 'Sedekah'),
	(87, 14, 19, 'keluar', 40000, '2025-06-09 14:00:00', 'Biaya Air'),
	(88, 15, 20, 'masuk', 250000, '2025-06-09 16:00:00', 'Donasi Renovasi'),
	(89, 12, 17, 'masuk', 70000, '2025-06-10 11:00:00', 'Sumbangan'),
	(90, 13, 18, 'masuk', 140000, '2025-06-10 12:00:00', 'Infaq'),
	(91, 14, 19, 'masuk', 210000, '2025-06-11 17:00:00', 'Donasi'),
	(92, 15, 20, 'masuk', 90000, '2025-06-12 08:30:00', 'Sedekah Pagi'),
	(93, 12, 17, 'masuk', 120000, '2025-06-12 09:00:00', 'Infaq'),
	(94, 13, 18, 'masuk', 210000, '2025-06-13 13:00:00', 'Infaq Jumat'),
	(95, 14, 19, 'masuk', 130000, '2025-06-14 15:00:00', 'Donasi'),
	(96, 15, 20, 'masuk', 80000, '2025-06-14 16:00:00', 'Sumbangan'),
	(97, 12, 17, 'keluar', 75000, '2025-06-15 10:00:00', 'Santunan Anak Yatim'),
	(98, 13, 18, 'masuk', 285000, '2025-06-15 11:00:00', 'Donasi Acara'),
	(99, 14, 19, 'masuk', 100000, '2025-06-16 14:00:00', 'Infaq'),
	(100, 15, 20, 'masuk', 110000, '2025-06-16 15:00:00', 'Sedekah'),
	(101, 12, 17, 'masuk', 210000, '2025-06-17 19:00:00', 'Donasi'),
	(102, 13, 18, 'masuk', 120000, '2025-06-18 09:00:00', 'Infaq'),
	(103, 14, 19, 'masuk', 90000, '2025-06-18 10:00:00', 'Sumbangan'),
	(104, 15, 20, 'masuk', 150000, '2025-06-19 11:00:00', 'Sedekah'),
	(105, 12, 17, 'masuk', 60000, '2025-06-19 12:00:00', 'Donasi'),
	(106, 13, 18, 'keluar', 50000, '2025-06-20 13:00:00', 'Biaya Konsumsi Rapat'),
	(107, 14, 19, 'masuk', 260000, '2025-06-20 13:30:00', 'Infaq Jumat'),
	(108, 11, 16, 'masuk', 170000, '2025-06-27 10:44:26', 'tabungan cash'),
	(109, 15, 20, 'keluar', 30000, '2025-06-27 10:46:39', 'narik cash');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
