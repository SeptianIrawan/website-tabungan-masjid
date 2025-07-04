<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}
require_once "db.php";

// Inisialisasi variabel
$total_users = 0;
$total_savings = 0;
$today_deposits = 0;
$today_withdrawals = 0;
$new_users = 0;
$average_savings = 0;
$last_update = date('Y-m-d H:i:s');

try {
    
    // Query untuk total pengguna
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM pengguna");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = $result['total_users'];
    
    // Query untuk total tabungan
    $stmt = $pdo->query("SELECT SUM(jumlah_tabungan) as total_savings FROM tabungan");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_savings = $result['total_savings'] ? $result['total_savings'] : 0;
    
    // Query untuk setoran hari ini (dari tabel transaksi jenis 'masuk')
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as today_deposits, MAX(tanggal) as last_deposit_time 
                                FROM transaksi 
                                WHERE jenis = 'masuk' AND DATE(tanggal) = ?");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_deposits = $result['today_deposits'];
    $last_deposit_time = $result['last_deposit_time'];

    // Query untuk penarikan hari ini
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah), 0) as today_withdrawals, MAX(tanggal) as last_withdrawal_time 
                                FROM transaksi 
                                WHERE jenis = 'keluar' AND DATE(tanggal) = ?");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_withdrawals = $result['today_withdrawals'];
    $last_withdrawal_time = $result['last_withdrawal_time'];
    
    // Query untuk pengguna baru hari ini
    $stmt = $pdo->prepare("SELECT COUNT(*) as new_users, MAX(created_at) as last_new_user_time 
                                FROM pengguna 
                                WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_users = $result['new_users'];
    $last_new_user_time = $result['last_new_user_time'];
    
    // Hitung rata-rata tabungan
    $average_savings = $total_users > 0 ? $total_savings / $total_users : 0;
    
    // Tentukan waktu update terakhir dari semua operasi
    $update_timestamps = [];

    if ($last_deposit_time !== null) {
        $update_timestamps[] = strtotime($last_deposit_time);
    }
    if ($last_withdrawal_time !== null) {
        $update_timestamps[] = strtotime($last_withdrawal_time);
    }
    if ($last_new_user_time !== null) {
        $update_timestamps[] = strtotime($last_new_user_time);
    }
    
    if (!empty($update_timestamps)) {
        $last_update = date('Y-m-d H:i:s', max($update_timestamps));
    } else {
        $last_update = date('Y-m-d H:i:s'); // Tetap gunakan waktu saat ini jika tidak ada data update
    }

} catch (PDOException $e) {
    error_log("Database error on dashboard: " . $e->getMessage());
    // Anda bisa tambahkan pesan error ke session jika ingin ditampilkan
    // $_SESSION['error_dashboard'] = 'Gagal memuat data dashboard: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Tabungan</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .h3-small {
            font-size: 1.5rem; /* Adjust as needed */
        }
        /* Existing styles */
        :root {
            --neon-blue: #00ffff;
            --neon-magenta: #ff00ff;
            --neon-green: #00ff00;
            --neon-yellow: #ffff00;
            --neon-purple: #8000ff;
            --neon-orange: #ff8000;
            --bg-color: #1a1a2e; /* Dark background */
            --card-bg: #2a2a4a; /* Slightly lighter dark for cards */
            --text-color: #f0f0f0; /* Light text for contrast */
            --border-color: rgba(0, 255, 255, 0.3); /* Neon border subtle */
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        .wrapper {
            background-color: var(--bg-color);
        }
        .main-header, .main-sidebar, .main-footer {
            background-color: var(--card-bg) !important;
            color: var(--text-color);
            border-bottom: 1px solid var(--border-color);
        }
        .main-sidebar .nav-link, .main-header .nav-link {
            color: var(--text-color) !important;
        }
        .main-sidebar .nav-link.active {
            background-color: rgba(0, 255, 255, 0.2) !important;
            color: var(--neon-blue) !important;
        }
        .content-wrapper {
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        .content-header h1, .card-title {
            color: var(--neon-blue);
            text-shadow: 0 0 5px var(--neon-blue);
        }
        .last-update {
            color: var(--text-color);
            font-size: 0.9em;
        }
        .breadcrumb-item a {
            color: var(--text-color) !important;
        }
        .breadcrumb-item.active {
            color: var(--neon-blue) !important;
        }
        .small-box, .info-box, .card {
            background-color: var(--card-bg) !important;
            color: var(--text-color) !important;
            border: 1px solid var(--border-color);
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.2);
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            overflow: hidden; /* For ripple effect */
            position: relative;
        }
        .small-box:hover, .info-box:hover, .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.4);
        }
        .small-box .icon, .info-box .info-box-icon {
            color: rgba(0, 255, 255, 0.4);
        }
        .small-box-footer, .info-box .progress-description, .alert {
            color: var(--text-color);
            border-top: 1px solid var(--border-color);
        }
        .alert-info, .alert-warning, .alert-success {
            background-color: rgba(0, 255, 255, 0.1);
            border-color: var(--neon-blue);
            color: var(--text-color);
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
        }
        .alert-warning {
            background-color: rgba(255, 128, 0, 0.1);
            border-color: var(--neon-orange);
        }
        .alert-success {
            background-color: rgba(0, 255, 0, 0.1);
            border-color: var(--neon-green);
        }
        .form-control {
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }
        .form-control option {
            background-color: var(--card-bg);
            color: var(--text-color);
        }
        .chart-container {
            height: 300px;
            width: 100%;
        }
        .ripple {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.7);
            animation: ripple-effect 1s linear forwards;
            transform: scale(0);
            opacity: 0;
        }
        @keyframes ripple-effect {
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <?php include 'navbar.php'; ?>

    <?php include 'sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard Tabungan</h1>
                        <div class="last-update">
                            <i class="fas fa-clock"></i>Data terakhir diupdate: <?php echo date('H:i:s', strtotime($last_update)); ?>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#"><i class="fas fa-home"></i></a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3 id="totalUsers"><?php echo number_format($total_users); ?></h3>
                                <p>Total Pengguna</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <a href="pengguna.php" class="small-box-footer">Info lebih lanjut <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <?php if (strlen(number_format($total_savings)) >= 10): ?>
                                    <h3 id="totalSavings" style="font-size: 1.7vw;">Rp <?php echo number_format($total_savings, 0, ',', '.'); ?></h3>
                                <?php else: ?>
                                    <h3 id="totalSavings" class="h3-small">Rp <?php echo number_format($total_savings, 0, ',', '.'); ?></h3>
                                <?php endif; ?>
                                <p>Total Tabungan</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-piggy-bank"></i>
                            </div>
                            <a href="tabungan.php" class="small-box-footer">Info lebih lanjut <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h3 id="todayDeposits">Rp <?php echo number_format($today_deposits, 0, ',', '.'); ?></h3>
                                <p>Setoran Hari Ini</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <a href="laporan.php" class="small-box-footer">Info lebih lanjut <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3 id="todayWithdrawals">Rp <?php echo number_format($today_withdrawals, 0, ',', '.'); ?></h3>
                                <p>Penarikan Hari Ini</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <a href="laporan.php" class="small-box-footer">Info lebih lanjut <i class="fas fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-line mr-1"></i>
                                    Statistik Bulanan
                                </h3>
                                <div class="card-tools">
                                    <select class="form-control form-control-sm month-selector" id="monthSelector">
                                        <?php
                                        $months = [
                                            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', 
                                            '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                                            '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                                            '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                                        ];
                                        $currentMonth = date('m');
                                        foreach ($months as $key => $month) {
                                            $selected = ($key == $currentMonth) ? 'selected' : '';
                                            echo "<option value='$key' $selected>$month</option>";
                                        }
                                        ?>
                                    </select>
                                    <select class="form-control form-control-sm month-selector" id="yearSelector">
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                            $selected = ($year == $currentYear) ? 'selected' : '';
                                            echo "<option value='$year' $selected>$year</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="info-box bg-info">
                            <span class="info-box-icon"><i class="fas fa-user-plus"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Pengguna Baru Hari ini</span>
                                <span class="info-box-number" id="newUsers">
                                    <?php echo $new_users; ?>
                                </span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo min(100, $new_users * 10); ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    <?php echo $new_users; ?> pengguna baru hari ini
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-box bg-success">
                            <span class="info-box-icon"><i class="fas fa-percentage"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Rata-rata Tabungan</span>
                                <span class="info-box-number" id="averageSavings">
                                    Rp <?php echo number_format($average_savings, 0, ',', '.'); ?>
                                </span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo min(100, $average_savings / max(1, $total_savings) * 100); ?>%"></div>
                                </div>
                                <span class="progress-description">
                                    Per pengguna
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-box bg-primary">
                            <span class="info-box-icon"><i class="fas fa-calendar-day"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Hari Tertinggi Bulan Ini</span>
                                <span class="info-box-number" id="highestDay">-</span>
                                <div class="progress">
                                    <div class="progress-bar" style="width: 0%"></div>
                                </div>
                                <span class="progress-description" id="highestDayAmount">
                                    Menunggu data...
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle mr-1"></i>
                                Informasi Singkat
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h5><i class="icon fas fa-info"></i> Total Setoran Bulan Ini</h5>
                                <span class="info-box-number" id="monthlyDeposit">Rp 0</span>
                            </div>
                            <div class="alert alert-warning">
                                <h5><i class="icon fas fa-hand-holding-usd"></i> Total Penarikan Bulan Ini</h5>
                                <span class="info-box-number" id="monthlyWithdrawal">Rp 0</span>
                            </div>
                            <div class="alert alert-success">
                                <h5><i class="icon fas fa-users"></i> Pengguna Baru Bulan Ini</h5>
                                <span class="info-box-number" id="monthlyNewUsers">0</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie mr-1"></i>
                                Distribusi Setoran
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="distributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </section>
    </div>

    <?php include 'footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>

<script>
$(document).ready(function() {
    // Format angka
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Fungsi untuk update data dashboard
    function updateDashboardData() {
        $.ajax({
            url: 'get_dashboard_data.php', // Pastikan file ini ada
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                // Update semua nilai
                $('#totalUsers').text(formatNumber(data.total_users));
                $('#totalSavings').text('Rp ' + formatNumber(data.total_savings));
                $('#newUsers').html(formatNumber(data.new_users));
                $('#todayDeposits').text('Rp ' + formatNumber(data.today_deposits));
                $('#todayWithdrawals').text('Rp ' + formatNumber(data.today_withdrawals));
                $('#averageSavings').text('Rp ' + formatNumber(data.average_savings));
                
                // Update waktu terakhir dari server
                if (data.last_update) {
                    // Konversi waktu UTC ke WIB
                    const lastUpdateUTC = new Date(data.last_update + 'Z'); // Asumsi data dari server adalah UTC
                    // Menggunakan Intl.DateTimeFormat untuk format waktu yang lebih fleksibel
                    const options = { 
                        hour: '2-digit', 
                        minute: '2-digit', 
                        second: '2-digit', 
                        timeZone: 'Asia/Jakarta' // Zona waktu WIB (Pekanbaru)
                    };
                    const lastUpdateWIB = new Intl.DateTimeFormat('id-ID', options).format(lastUpdateUTC);
                    $('.last-update').html('<i class="fas fa-clock"></i>Data terakhir diupdate: ' + lastUpdateWIB);
                }
                
                // Animasi (opsional, bisa disesuaikan/dihapus)
                $('.small-box, .info-box').css('transform', 'scale(1.02)'); // Sedikit lebih kecil agar tidak terlalu besar
                setTimeout(function() {
                    $('.small-box, .info-box').css('transform', 'scale(1)');
                }, 200); // Durasi lebih cepat
            },
            error: function(xhr, status, error) {
                console.error("Error fetching dashboard data:", error);
            }
        });
    }

    // Chart.js initialization
    var monthlyChart;
    var distributionChart;

    function initMonthlyChart(labels, deposits, withdrawals) { // Tambahkan parameter withdrawals
        if (monthlyChart) monthlyChart.destroy(); // Hancurkan instance chart yang lama jika ada

        var ctx = document.getElementById('monthlyChart').getContext('2d');
        monthlyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Setoran Harian',
                    backgroundColor: 'rgba(0, 255, 255, 0.5)', // Neon blue transparan
                    borderColor: 'var(--neon-blue)', // Neon blue
                    borderWidth: 1,
                    borderRadius: 5,
                    data: deposits
                }, {
                    label: 'Penarikan Harian', // New dataset for withdrawals
                    backgroundColor: 'rgba(255, 128, 0, 0.5)', // Neon orange transparan
                    borderColor: 'var(--neon-orange)', // Neon orange
                    borderWidth: 1,
                    borderRadius: 5,
                    data: withdrawals // Use the withdrawals data
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + formatNumber(value);
                            },
                            color: '#F0F0F0' 
                        },
                        grid: {
                            color: 'rgba(0, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 45,
                            color: '#F0F0F0' 
                        },
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rp ' + formatNumber(context.raw);
                            }
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'var(--neon-blue)',
                        bodyColor: 'var(--text-color)',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        }
                    },
                    legend: {
                        display: true, // Set to true to show legend for multiple datasets
                        labels: {
                            color: '#F0F0F0'
                        }
                    }
                }
            }
        });
    }

    function initDistributionChart(labels, data) {
        if (distributionChart) distributionChart.destroy(); // Hancurkan instance chart yang lama jika ada

        var ctx = document.getElementById('distributionChart').getContext('2d');
        distributionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: [
                        'rgba(0, 255, 255, 0.7)', // Neon Blue
                        'rgba(255, 0, 255, 0.7)', // Neon Magenta
                        'rgba(0, 255, 0, 0.7)',    // Neon Green
                        'rgba(255, 255, 0, 0.7)', // Neon Yellow
                        'rgba(128, 0, 255, 0.7)', // Neon Purple
                        'rgba(255, 128, 0, 0.7)'  // Neon Orange
                    ],
                    borderColor: 'var(--bg-color)', // Border sama dengan background card
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#F0F0F0',
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': Rp ' + formatNumber(context.raw);
                            }
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'var(--neon-blue)',
                        bodyColor: 'var(--text-color)',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        }
                    }
                },
                cutout: '70%'
            }
        });
    }

    function loadMonthlyData(month, year) {
        $.ajax({
            url: 'get_monthly_data.php', // Pastikan file ini ada
            type: 'GET',
            dataType: 'json',
            data: { 
                month: month,
                year: year 
            },
            success: function(data) {
                // Update summary boxes
                $('#monthlyDeposit').text('Rp ' + formatNumber(data.total_deposit));
                $('#monthlyWithdrawal').text('Rp ' + formatNumber(data.total_withdrawal));
                $('#monthlyNewUsers').text(formatNumber(data.new_users) + ' Orang');
                
                // Update highest day info
                if (data.highest_day && data.highest_day.day) {
                    $('#highestDay').text('Hari ' + data.highest_day.day);
                    $('#highestDayAmount').text('Rp ' + formatNumber(data.highest_day.amount));
                    // Calculate progress bar width based on highest_day amount (relative to monthly deposit total for example)
                    const progressBarWidth = (data.highest_day.amount / (data.total_deposit || 1)) * 100;
                    $('.info-box.bg-primary .progress-bar').css('width', `${Math.min(100, progressBarWidth)}%`);
                } else {
                    $('#highestDay').text('-');
                    $('#highestDayAmount').text('Menunggu data...');
                    $('.info-box.bg-primary .progress-bar').css('width', '0%');
                }
                
                // Update monthly chart with deposits AND withdrawals
                if (monthlyChart) {
                    monthlyChart.data.labels = data.days;
                    monthlyChart.data.datasets[0].data = data.deposits;
                    monthlyChart.data.datasets[1].data = data.withdrawals; // Update withdrawals
                    monthlyChart.update();
                } else {
                    initMonthlyChart(data.days, data.deposits, data.withdrawals); // Pass withdrawals
                }
                
                // Update distribution chart (example data - based on weeks for simplicity)
                var distributionLabels = ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'];
                var distributionData = [0, 0, 0, 0];
                
                data.deposits.forEach((amount, index) => {
                    var week = Math.floor(index / 7);
                    if (week < 4) { // Asumsi 4 minggu dalam sebulan
                        distributionData[week] += amount;
                    }
                });
                
                if (distributionChart) {
                    distributionChart.data.labels = distributionLabels;
                    distributionChart.data.datasets[0].data = distributionData;
                    distributionChart.data.datasets[0].backgroundColor = [
                        'rgba(0, 255, 255, 0.7)', // Neon Blue
                        'rgba(255, 0, 255, 0.7)', // Neon Magenta
                        'rgba(0, 255, 0, 0.7)',    // Neon Green
                        'rgba(255, 255, 0, 0.7)' // Neon Yellow (only need 4 colors for 4 weeks)
                    ];
                    distributionChart.update();
                } else {
                    initDistributionChart(distributionLabels, distributionData);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error fetching monthly data:", error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Gagal memuat data bulanan'
                });
            }
        });
    }

    // Load initial data
    var currentMonth = $('#monthSelector').val();
    var currentYear = $('#yearSelector').val();
    loadMonthlyData(currentMonth, currentYear);
    
    // Month/year selector change handler
    $('#monthSelector, #yearSelector').change(function() {
        loadMonthlyData($('#monthSelector').val(), $('#yearSelector').val());
    });

    // Auto refresh dashboard every 30 seconds
    setInterval(updateDashboardData, 30000);

    // Also refresh when window gets focus
    $(window).focus(function() {
        updateDashboardData();
    });

    // Animation on hover (fa-bounce)
    $('.small-box').hover(
        function() {
            $(this).find('.icon i').addClass('fa-bounce');
        },
        function() {
            $(this).find('.icon i').removeClass('fa-bounce');
        }
    );
    
    // Add ripple effect to cards
    $('.card, .info-box, .small-box').on('click', function(e) {
        var x = e.pageX - $(this).offset().left;
        var y = e.pageY - $(this).offset().top;
        
        var ripple = $('<span class="ripple"></span>');
        ripple.css({
            left: x,
            top: y
        });
        
        $(this).append(ripple);
        
        setTimeout(function() {
            ripple.remove();
        }, 1000);
    });
});
</script>

</body>
</html>