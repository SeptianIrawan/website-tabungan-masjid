<?php
require_once "db.php";
require_once 'vendor/autoload.php'; // Require Composer's autoloader

use Dompdf\Dompdf;


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

// Generate HTML content
$html = '
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
';

if ($start_date || $end_date) {
    $html .= 'Periode: ';
    $html .= $start_date ? date('d/m/Y', strtotime($start_date)) : 'Awal';
    $html .= ' s/d ';
    $html .= $end_date ? date('d/m/Y', strtotime($end_date)) : 'Sekarang';
}

$html .= '
    </div>
    
    <div class="summary">
        <div class="summary-item">
            <div>Total Pemasukan</div>
            <div class="text-success">Rp '.number_format($total_masuk, 0, ',', '.').'</div>
        </div>
        <div class="summary-item">
            <div>Total Pengeluaran</div>
            <div class="text-danger">Rp '.number_format($total_keluar, 0, ',', '.').'</div>
        </div>
        <div class="summary-item">
            <div>Saldo</div>
            <div class="text-primary">Rp '.number_format($saldo, 0, ',', '.').'</div>
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
';

foreach ($transactions as $index => $trx) {
    $html .= '
            <tr>
                <td>'.($index + 1).'</td>
                <td>'.date('d/m/Y H:i', strtotime($trx['tanggal'])).'</td>
                <td>'.ucfirst($trx['jenis']).'</td>
                <td>Rp '.number_format($trx['jumlah'], 0, ',', '.').'</td>
                <td>'.htmlspecialchars($trx['nama_pengguna'] ?? '-').'</td>
                <td>'.htmlspecialchars($trx['keterangan'] ?? '-').'</td>
            </tr>
    ';
}

if (empty($transactions)) {
    $html .= '
            <tr>
                <td colspan="6" class="text-center">Tidak ada data transaksi</td>
            </tr>
    ';
}

$html .= '
        </tbody>
    </table>
    
    <div class="footer">
        <div>Dicetak pada: '.date('d/m/Y ').'</div>
    </div>
</body>
</html>';

// Create PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Generate filename
$filename = "laporan_transaksi";
if ($start_date || $end_date) {
    $filename .= "_" . ($start_date ? date('Ymd', strtotime($start_date)) : 'awal');
    $filename .= "_" . ($end_date ? date('Ymd', strtotime($end_date)) : 'sekarang');
}
$filename .= ".pdf";

// Output PDF
$dompdf->stream($filename, array("Attachment" => true));