<?php
require_once "db.php";
require_once 'vendor/autoload.php'; // Require Composer's autoloader

use Dompdf\Dompdf;

// Get transaction ID from URL
$id = $_GET['id'] ?? null;

if (!$id) {
    die("ID transaksi tidak valid");
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get transaction data
    $stmt = $pdo->prepare("SELECT t.*, p.nama as nama_pengguna 
                          FROM transaksi t
                          LEFT JOIN pengguna p ON t.id_pengguna = p.id_pengguna
                          WHERE t.id_transaksi = ?");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        die("Transaksi tidak ditemukan");
    }
    
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Generate HTML content
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi #'.$id.'</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; }
        .detail { margin-bottom: 15px; }
        .detail-label { font-weight: bold; width: 150px; display: inline-block; }
        .footer { margin-top: 30px; text-align: right; }
        hr { border: 0.5px dashed #ccc; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">LAPORAN TRANSAKSI</div>
        <div>Masjid Rahmatullah</div>
    </div>
    
    <hr>
    
    <div class="detail">
        <span class="detail-label">ID Transaksi:</span>
        '.$transaction['id_transaksi'].'
    </div>
    
    <div class="detail">
        <span class="detail-label">Tanggal:</span>
        '.date('d/m/Y H:i', strtotime($transaction['tanggal'])).'
    </div>
    
    <div class="detail">
        <span class="detail-label">Jenis:</span>
        '.ucfirst($transaction['jenis']).'
    </div>
    
    <div class="detail">
        <span class="detail-label">Jumlah:</span>
        Rp '.number_format($transaction['jumlah'], 0, ',', '.').'
    </div>
    
    <div class="detail">
        <span class="detail-label">Nama:</span>
        '.htmlspecialchars($transaction['nama_pengguna'] ?? '-').'
    </div>
    
    <div class="detail">
        <span class="detail-label">Keterangan:</span>
        '.htmlspecialchars($transaction['keterangan'] ?? '-').'
    </div>
    
    <hr>
    
    <div class="footer">
        <div>Dicetak pada: '.date('d/m/Y ').'</div>
    </div>
</body>
</html>';

// Create PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();

// Output PDF
$dompdf->stream("transaksi_$id.pdf", array("Attachment" => true));