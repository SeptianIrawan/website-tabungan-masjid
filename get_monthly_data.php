<?php
require_once "db.php"; // Pastikan file koneksi database Anda
header('Content-Type: application/json');

$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$labels = [];
$deposits = array_fill(0, $days_in_month, 0);
$withdrawals = array_fill(0, $days_in_month, 0); // New: initialize withdrawals array

// Prepare labels for the chart (days of the month)
for ($i = 1; $i <= $days_in_month; $i++) {
    $labels[] = str_pad($i, 2, '0', STR_PAD_LEFT);
}

$total_deposit = 0;
$total_withdrawal = 0;
$new_users = 0;
$highest_day_amount = 0;
$highest_day_number = 0;

try {
    // Fetch monthly deposits
    $stmt_deposits = $pdo->prepare("
        SELECT DAY(tanggal) as day, SUM(jumlah) as total_amount
        FROM transaksi
        WHERE jenis = 'masuk' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
        GROUP BY DAY(tanggal)
        ORDER BY DAY(tanggal) ASC
    ");
    $stmt_deposits->execute([$month, $year]);
    while ($row = $stmt_deposits->fetch(PDO::FETCH_ASSOC)) {
        $deposits[$row['day'] - 1] = $row['total_amount'];
        $total_deposit += $row['total_amount'];
        if ($row['total_amount'] > $highest_day_amount) {
            $highest_day_amount = $row['total_amount'];
            $highest_day_number = $row['day'];
        }
    }

    // Fetch monthly withdrawals (NEW)
    $stmt_withdrawals = $pdo->prepare("
        SELECT DAY(tanggal) as day, SUM(jumlah) as total_amount
        FROM transaksi
        WHERE jenis = 'keluar' AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
        GROUP BY DAY(tanggal)
        ORDER BY DAY(tanggal) ASC
    ");
    $stmt_withdrawals->execute([$month, $year]);
    while ($row = $stmt_withdrawals->fetch(PDO::FETCH_ASSOC)) {
        $withdrawals[$row['day'] - 1] = $row['total_amount'];
        $total_withdrawal += $row['total_amount'];
    }

    // Fetch new users for the month
    $stmt_new_users = $pdo->prepare("
        SELECT COUNT(*) as new_users_count
        FROM pengguna
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
    ");
    $stmt_new_users->execute([$month, $year]);
    $new_users_result = $stmt_new_users->fetch(PDO::FETCH_ASSOC);
    $new_users = $new_users_result['new_users_count'];

    echo json_encode([
        'days' => $labels,
        'deposits' => $deposits,
        'withdrawals' => $withdrawals, // New: send withdrawals data
        'total_deposit' => $total_deposit,
        'total_withdrawal' => $total_withdrawal,
        'new_users' => $new_users,
        'highest_day' => [
            'day' => $highest_day_number,
            'amount' => $highest_day_amount
        ]
    ]);

} catch (PDOException $e) {
    error_log("Database error on get_monthly_data: " . $e->getMessage());
    echo json_encode(['error' => 'Gagal memuat data bulanan', 'message' => $e->getMessage()]);
}
?>