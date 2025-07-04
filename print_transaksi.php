<?php
require_once "db.php";

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Transaksi #<?php echo $id; ?></title>
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
        <?php echo $transaction['id_transaksi']; ?>
    </div>
    
    <div class="detail">
        <span class="detail-label">Tanggal:</span>
        <?php echo date('d/m/Y H:i', strtotime($transaction['tanggal'])); ?>
    </div>
    
    <div class="detail">
        <span class="detail-label">Jenis:</span>
        <?php echo ucfirst($transaction['jenis']); ?>
    </div>
    
    <div class="detail">
        <span class="detail-label">Jumlah:</span>
        Rp <?php echo number_format($transaction['jumlah'], 0, ',', '.'); ?>
    </div>
    
    <div class="detail">
        <span class="detail-label">Nama:</span>
        <?php echo htmlspecialchars($transaction['nama_pengguna'] ?? '-'); ?>
    </div>
    
    <div class="detail">
        <span class="detail-label">Keterangan:</span>
        <?php echo htmlspecialchars($transaction['keterangan'] ?? '-'); ?>
    </div>
    
    <hr>
    
    <div class="footer">
        <div>Dicetak pada: <?php echo date('d/m/Y '); ?></div>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>