<?php
// db.php
$host = '127.0.0.1';
$dbname = 'tabungan_masjid_rahmatullah'; // <<< GANTI DENGAN NAMA DATABASE ANDA
$username = 'root'; // <<< SESUAIKAN DENGAN USERNAME DATABASE ANDA
$password = ''; // <<< SESUAIKAN DENGAN PASSWORD DATABASE ANDA
$title = 'Website Tabungan Masjid Rahmatullah'; // Judul website Anda

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error secara aman, jangan tampilkan detail sensitif di produksi
    error_log("Koneksi database gagal: " . $e->getMessage());
    die("Koneksi database gagal. Silakan coba lagi nanti.");
}
?>