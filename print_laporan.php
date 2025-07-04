<?php
require_once "db.php";

// Get filter parameters
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $where_clause = "";
    $params = [];
    
    if ($start_date && $end_date) {
        $where_clause = " WHERE t.tanggal BETWEEN ? AND ?";
        $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } elseif ($start_date) {
        $where_clause = " WHERE t.tanggal >= ?";
        $params = [$start_date . ' 00:00:00'];
    } elseif ($end_date) {
        $where_clause = " WHERE t.tanggal <= ?";
        $params = [$end_date . ' 23:59:59'];
    }
    
    // Get transactions
    $sql = "SELECT t.*, p.nama as nama_pengguna 
            FROM transaksi t
            LEFT JOIN pengguna p ON t.id_pengguna = p.id_pengguna
            $where_clause
            ORDER BY t.tanggal DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $total_masuk = 0;
    $total_keluar = 0;
    foreach ($transactions as $trx) {
        if ($trx['jenis'] == 'masuk') {
            $total_masuk += (int)$trx['jumlah'];
        } else {
            $total_keluar += (int)$trx['jumlah'];
        }
    }
    $saldo = $total_masuk - $total_keluar;
    
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; }
        .period { margin-bottom: 15px; text-align: center; }
        .summary { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .summary-item { width: 30%; text-align: center; padding: 10px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .footer { margin-top: 30px; text-align: right; }
        .text-success { color: #28a745; }
        .text-danger { color: #dc3545; }
        .text-primary { color: #007bff; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">LAPORAN TRANSAKSI MASJID</div>
        <div>Masjid Rahmatullah</div>
    </div>
    
    <div class="period">
        <?php if ($start_date || $end_date): ?>
        Periode: 
        <?php echo $start_date ? date('d/m/Y', strtotime($start_date)) : 'Awal'; ?>
        s/d
        <?php echo $end_date ? date('d/m/Y', strtotime($end_date)) : 'Sekarang'; ?>
        <?php endif; ?>
    </div>
    
    <div class="summary">
        <div class="summary-item">
            <div>Total Pemasukan</div>
            <div class="text-success">Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-item">
            <div>Total Pengeluaran</div>
            <div class="text-danger">Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-item">
            <div>Saldo</div>
            <div class="text-primary">Rp <?php echo number_format($saldo, 0, ',', '.'); ?></div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Jumlah</th>
                <th>Nama</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $index => $trx): ?>
            <tr>
                <td><?php echo $index + 1; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($trx['tanggal'])); ?></td>
                <td><?php echo ucfirst($trx['jenis']); ?></td>
                <td>Rp <?php echo number_format($trx['jumlah'], 0, ',', '.'); ?></td>
                <td><?php echo htmlspecialchars($trx['nama_pengguna'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($trx['keterangan'] ?? '-'); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
            <tr>
                <td colspan="6" class="text-center">Tidak ada data transaksi</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <div>Dicetak pada: <?php echo date('d/m/Y '); ?></div>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>