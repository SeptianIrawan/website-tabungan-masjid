<?php
session_start();
header('Content-Type: application/json');

// $host = '127.0.0.1';
// $dbname = 'tabungan_masjid_rahmatullah';
// $username = 'root';
// $password = '';

$response = [
    'success' => false,
    'total_users' => 0,
    'total_savings' => 0,
    'today_deposits' => 0,
    'today_withdrawals' => 0,  // Initialize with 0 instead of undefined variable
    'new_users' => 0,
    'average_savings' => 0
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Total pengguna
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM pengguna");
    $response['total_users'] = (int)$stmt->fetchColumn();
    
    // Total tabungan
    $stmt = $pdo->query("SELECT SUM(jumlah_tabungan) as total_savings FROM tabungan");
    $response['total_savings'] = (int)$stmt->fetchColumn();
    
    // Setoran hari ini (from transaksi table where jenis = 'masuk')
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as today_deposits 
                          FROM transaksi 
                          WHERE jenis = 'masuk' AND DATE(tanggal) = ?");
    $stmt->execute([$today]);
    $response['today_deposits'] = (int)$stmt->fetchColumn();
    
    // Penarikan hari ini
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as today_withdrawals 
                          FROM transaksi 
                          WHERE jenis = 'keluar' AND DATE(tanggal) = ?");
    $stmt->execute([$today]);
    $response['today_withdrawals'] = (int)$stmt->fetchColumn();
    
    // Pengguna baru hari ini
    $stmt = $pdo->prepare("SELECT COUNT(*) as new_users FROM pengguna WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $response['new_users'] = (int)$stmt->fetchColumn();
    
    // Rata-rata tabungan
    $response['average_savings'] = $response['total_users'] > 0 
        ? round($response['total_savings'] / $response['total_users'])
        : 0;
    
    $response['success'] = true;

} catch (PDOException $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>