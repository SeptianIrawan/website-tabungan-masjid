<?php
session_start();
require_once "db.php";
// Set timezone di awal file
date_default_timezone_set('Asia/Jakarta');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+07:00'"); // Set timezone untuk MySQL
    
    if (!isset($_GET['id_tabungan'])) {
        throw new Exception('ID Tabungan tidak valid');
    }
    
    $id_tabungan = $_GET['id_tabungan'];
    
    // Get savings account information
    $stmt = $pdo->prepare("SELECT t.*, p.nama FROM tabungan t JOIN pengguna p ON t.id_pengguna = p.id_pengguna WHERE t.id_tabungan = ?");
    $stmt->execute([$id_tabungan]);
    $tabungan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tabungan) {
        throw new Exception('Data tabungan tidak ditemukan');
    }
    
    // Get all transactions for this savings account
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE id_tabungan = ? ORDER BY tanggal DESC");
    $stmt->execute([$id_tabungan]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML report
    $html = '
    <style>
        .report-header { margin-bottom: 20px; }
        .report-title { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }
        .report-subtitle { text-align: center; margin-bottom: 20px; }
        .report-info { margin-bottom: 15px; }
        .transaction-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .transaction-table th, .transaction-table td { border: 1px solid #ddd; padding: 8px; }
        .transaction-table th { background-color:rgb(36, 33, 33); text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .deposit { color: #28a745; }
        .withdrawal { color: #dc3545; }
        .print-only { display: none; }
        .transaction-date { white-space: nowrap; }
        @media print {
            .no-print { display: none; }
            .print-only { display: block; }
            body { font-size: 12pt; }
            .report-header { margin-bottom: 10mm; }
            .transaction-table { page-break-inside: avoid; }
        }
    </style>
    
    <div class="report-header">
        <div class="report-title">LAPORAN TRANSAKSI TABUNGAN</div>
        <div class="report-subtitle">Mesjid Rahmatullah</div>
        
        <div class="report-info">
            <table>
                <tr>
                    <td width="120">Nama Anggota</td>
                    <td width="10">:</td>
                    <td><strong>'.htmlspecialchars($tabungan['nama']).'</strong></td>
                </tr>
                <tr>
                    <td>Saldo Akhir</td>
                    <td>:</td>
                    <td><strong>Rp '.number_format($tabungan['jumlah_tabungan'], 0, ',', '.').'</strong></td>
                </tr>
                <tr>
                    <td>Tanggal Cetak</td>
                    <td>:</td>
                    <td>'.date('d M Y H:i:s').'</td>
                </tr>
            </table>
        </div>
    </div>';
    
    if (count($transactions) > 0) {
        $html .= '
        <table class="transaction-table">
            <thead>
                <tr>
                    <th width="50">No</th>
                    <th width="150">Tanggal</th>
                    <th>Jenis</th>
                    <th class="text-right">Jumlah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($transactions as $index => $transaction) {
            $transactionDate = $transaction['tanggal'] ? date('d M Y H:i:s', strtotime($transaction['tanggal'])) : '-';
            
            $html .= '
            <tr>
                <td>'.($index + 1).'</td>
                <td class="transaction-date">'.$transactionDate.'</td>
                <td>'.($transaction['jenis'] == 'masuk' ? 'Setoran' : 'Penarikan').'</td>
                <td class="text-right '.($transaction['jenis'] == 'masuk' ? 'deposit' : 'withdrawal').'">
                    '.($transaction['jenis'] == 'masuk' ? '+' : '-').' Rp '.number_format($transaction['jumlah'], 0, ',', '.').'
                </td>
                <td>'.htmlspecialchars($transaction['keterangan']).'</td>
            </tr>';
        }
        
        $html .= '
            </tbody>
        </table>';
    } else {
        $html .= '<div class="alert alert-info">Tidak ada transaksi yang tercatat.</div>';
    }
    
    $html .= '
    <div class="print-only">
        <table width="100%">
            <tr>
                <td width="50%"></td>
                <td class="text-center">
                    <p>Mengetahui,</p>
                    <br><br><br>
                    <p>_________________________</p>
                    <p>Petugas</p>
                </td>
            </tr>
        </table>
    </div>';
    
    echo $html;
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: '.htmlspecialchars($e->getMessage()).'</div>';
}
?>