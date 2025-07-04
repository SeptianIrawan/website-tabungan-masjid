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
$transactions = [];
$total_masuk = 0;
$total_keluar = 0;
$saldo = 0;
$error = '';
$success = '';

try {
    // Pastikan db.php mengembalikan objek PDO ($pdo)
    // Jika tidak, inisialisasi PDO di sini seperti di dashboard.php
    // $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Handle filter form submission
    $filter_start = $_GET['start_date'] ?? null;
    $filter_end = $_GET['end_date'] ?? null;
    
    $where_clause = "";
    $params = [];
    
    if ($filter_start && $filter_end) {
        $where_clause = " WHERE t.tanggal BETWEEN ? AND ?";
        $params = [$filter_start . ' 00:00:00', $filter_end . ' 23:59:59'];
    } elseif ($filter_start) {
        $where_clause = " WHERE t.tanggal >= ?";
        $params = [$filter_start . ' 00:00:00'];
    } elseif ($filter_end) {
        $where_clause = " WHERE t.tanggal <= ?";
        $params = [$filter_end . ' 23:59:59'];
    }
    
    // Get all transactions with user info
    $sql = "SELECT t.*, p.nama as nama_pengguna 
            FROM transaksi t
            LEFT JOIN pengguna p ON t.id_pengguna = p.id_pengguna
            $where_clause
            ORDER BY t.tanggal DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($transactions as $trx) {
        if ($trx['jenis'] == 'masuk') {
            $total_masuk += (int)$trx['jumlah'];
        } else {
            $total_keluar += (int)$trx['jumlah'];
        }
    }
    $saldo = $total_masuk - $total_keluar;
    
} catch (PDOException $e) {
    $error = 'Koneksi database gagal: ' . $e->getMessage();
    $transactions = []; // Ensure it's an array even on error
}

// Get notification messages from session
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Transaksi</title> <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* Variabel Warna (konsisten dengan tema login/sidebar/dashboard) */
        :root {
            --bg-color: #1a1a1a; /* Hampir hitam */
            --text-color: #f0f0f0; /* PUTIH */
            --placeholder-color: rgba(255, 255, 255, 0.6);
            --form-bg: rgba(255, 255, 255, 0.05); /* Semi-transparan untuk kartu/form */

            --neon-blue: #00FFFF;
            --neon-blue-light: #00E5E5;
            --border-color: rgba(0, 255, 255, 0.3);

            /* Warna kustom untuk box/info-box/summary-card agar lebih neon */
            --neon-info: rgba(0, 191, 255, 0.4);   /* Light blue neon */
            --neon-success: rgba(0, 255, 127, 0.4); /* Green neon */
            --neon-warning: rgba(255, 255, 0, 0.4);  /* Yellow neon */
            --neon-danger: rgba(255, 69, 0, 0.4);   /* Orange-red neon */
            --neon-primary: rgba(0, 100, 255, 0.4); /* Deeper blue neon */

            /* Variabel untuk DataTables Pagination/Search - Biru Gelap */
            --datatables-dark-blue-bg: rgba(0, 100, 100, 0.5);
            --datatables-dark-blue-border: rgba(0, 100, 100, 0.5);
            --datatables-dark-blue-hover-bg: rgba(0, 100, 100, 0.7);
            --datatables-dark-blue-hover-border: rgba(0, 100, 100, 0.7);
            --datatables-dark-blue-disabled-bg: rgba(0, 100, 100, 0.2);
            --datatables-dark-blue-disabled-border: rgba(0, 100, 100, 0.2);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        .content-wrapper {
            background-color: var(--bg-color);
            color: var(--text-color);
        }

        /* --- Global AdminLTE Overrides (dari dashboard.php, jika belum terpisah) --- */
        .card {
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.1); 
            border: 1px solid var(--border-color);
            background-color: var(--form-bg);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3); 
        }
        .card-header {
            border-bottom: 1px solid var(--border-color);
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .card-title {
            font-weight: 600;
            color: var(--neon-blue);
            text-shadow: 0 0 5px rgba(0, 255, 255, 0.5);
        }
        .content-header h1 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
            color: var(--neon-blue);
            text-shadow: 0 0 5px rgba(0, 255, 255, 0.5);
        }
        .breadcrumb {
            background-color: transparent !important;
            padding: 0;
            margin-bottom: 0;
        }
        .breadcrumb-item a {
            color: var(--neon-blue) !important;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .breadcrumb-item a:hover {
            color: var(--neon-blue-light) !important;
            text-shadow: 0 0 5px rgba(0, 255, 255, 0.5);
        }
        .breadcrumb-item.active {
            color: var(--text-color) !important;
        }
        /* Penyesuaian Warna Teks Umum dan Kontras */
        .content-wrapper p, 
        .content-wrapper span, 
        .content-wrapper div:not(.icon):not(.progress-bar) {
            color: var(--text-color);
        }
        .content-wrapper a:not(.btn):not(.nav-link) {
            color: var(--neon-blue);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .content-wrapper a:not(.btn):not(.nav-link):hover {
            color: var(--neon-blue-light);
            text-decoration: underline;
        }
        /* --- Akhir Global AdminLTE Overrides --- */

        /* Action Buttons */
        .action-buttons .btn {
            margin-right: 5px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            box-shadow: 0 0 8px rgba(0, 255, 255, 0.4); /* Glow untuk semua tombol aksi */
            transition: all 0.3s ease;
        }
        .action-buttons .btn:hover {
             box-shadow: 0 0 15px rgba(0, 255, 255, 0.6);
             transform: translateY(-1px);
        }
        .btn-info { /* Cetak individu */
            background-color: var(--neon-blue) !important;
            border-color: var(--neon-blue) !important;
            color: var(--bg-color) !important; /* Teks gelap di tombol neon */
        }
        .btn-info:hover {
            background-color: var(--neon-blue-light) !important;
            border-color: var(--neon-blue-light) !important;
        }
        .btn-danger { /* PDF individu */
            background-color: var(--neon-danger) !important; /* Merah-oranye neon */
            border-color: var(--neon-danger) !important;
            color: var(--text-color) !important;
        }
        .btn-danger:hover {
            background-color: rgba(255, 69, 0, 0.7) !important;
            border-color: rgba(255, 69, 0, 0.7) !important;
        }
        .btn-primary { /* Cetak Laporan Penuh */
            background-color: var(--neon-blue) !important;
            border-color: var(--neon-blue) !important;
            color: var(--bg-color) !important;
        }
        .btn-primary:hover {
            background-color: var(--neon-blue-light) !important;
            border-color: var(--neon-blue-light) !important;
        }
        .btn-secondary { /* Reset Filter */
            background-color: var(--form-bg) !important;
            border-color: var(--border-color) !important;
            color: var(--text-color) !important;
            box-shadow: none; /* Tanpa glow di sini */
        }
        .btn-secondary:hover {
            background-color: rgba(0,0,0,0.4) !important;
            border-color: var(--neon-blue) !important;
            color: var(--neon-blue) !important;
        }


        /* Summary Cards */
        .summary-card {
            border-left: 4px solid var(--neon-blue); /* Garis kiri neon utama */
            background-color: var(--form-bg); /* Latar belakang semi-transparan */
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.1); 
            border: 1px solid var(--border-color); /* Border keseluruhan */
            transition: all 0.3s ease;
        }
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.3); 
        }
        .summary-card .card-body {
            padding: 15px;
        }
        .summary-card .card-title {
            color: var(--neon-blue); /* Judul card neon */
            font-size: 1.1rem; /* Sesuaikan ukuran font jika perlu */
        }
        .summary-card .card-text.h4 {
            font-size: 1.8rem; /* Ukuran angka besar */
            font-weight: 700;
            color: var(--text-color); /* Angka utama putih */
            text-shadow: 0 0 5px rgba(255,255,255,0.3); /* Glow samar */
        }
        /* Hapus override warna spesifik ini jika semua angka ingin putih */
        /* .summary-card .text-success, .summary-card .text-danger, .summary-card .text-primary { ... } */

        /* Table Styles */
        .table {
            background-color: var(--form-bg); /* Latar belakang tabel semi-transparan */
            color: var(--text-color); /* Warna teks default tabel */
            border: 1px solid var(--border-color); /* Border tabel */
            border-radius: 10px; /* Sudut membulat */
            overflow: hidden; /* Penting untuk border-radius pada tabel */
        }
        .table th {
            background-color: rgba(0, 255, 255, 0.1) !important; /* Header tabel latar neon transparan */
            color: var(--neon-blue) !important; /* Teks header tabel neon */
            border-bottom: 1px solid var(--border-color) !important;
            border-top: none; /* Hapus border top bawaan AdminLTE jika ada */
        }
        .table tbody td {
            border-color: rgba(0, 255, 255, 0.05) !important; /* Border antar sel yang sangat samar */
            vertical-align: middle; /* Pusatkan teks vertikal di sel */
        }
        .table tbody tr:last-child td {
            border-bottom: none !important; /* Hapus border bawah di baris terakhir */
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 255, 255, 0.08) !important; /* Efek hover neon transparan */
            color: var(--text-color) !important; /* Pastikan teks tetap putih saat hover */
        }
        /* Warna Badge pada Kolom Jenis */
        .badge-success { 
            background-color: var(--neon-success) !important; 
            color: var(--bg-color) !important; /* Teks gelap pada badge hijau neon */
            font-weight: 600;
        }
        .badge-danger { 
            background-color: var(--neon-danger) !important; 
            color: var(--text-color) !important; /* Teks putih pada badge merah neon */
            font-weight: 600;
        }
        /* Teks "Tidak ada data transaksi" */
        .table .text-center {
            color: var(--placeholder-color);
        }

        /* Filter Form Styles */
        .filter-form label {
            color: var(--neon-blue-light); /* Label filter neon */
            font-size: 0.9em;
        }
        .filter-form .form-control {
            background-color: var(--neon-blue) !important; /* Latar belakang biru muda */
            border: 1px solid var(--neon-blue) !important; /* Border biru muda */
            color: var(--bg-color) !important; /* Teks hitam */
            border-radius: 5px;
            padding: 8px 12px;
        }
        .filter-form .form-control:focus {
            outline: none;
            border-color: var(--neon-blue-light) !important;
            box-shadow: 0 0 8px var(--neon-blue) !important;
            background-color: var(--neon-blue-light) !important;
            color: var(--bg-color) !important;
        }
        /* Icon kalender pada input type="date" */
        .filter-form .form-control::-webkit-calendar-picker-indicator {
            filter: invert(0); /* Kembali ke warna gelap jika background terang */
        }
        /* Progress bar dalam info box */
        .progress-bar {
            background-color: var(--neon-blue) !important; /* Warna progress bar neon */
        }

        /* DataTables Pagination */
        .dataTables_wrapper .pagination .page-item .page-link {
            background-color: var(--datatables-dark-blue-bg) !important; /* Biru gelap */
            border-color: var(--datatables-dark-blue-border) !important;
            color: var(--text-color) !important; /* Teks putih */
            transition: all 0.3s ease;
        }
        .dataTables_wrapper .pagination .page-item .page-link:hover {
            background-color: var(--datatables-dark-blue-hover-bg) !important;
            border-color: var(--datatables-dark-blue-hover-border) !important;
            color: var(--text-color) !important;
            box-shadow: 0 0 8px rgba(0,255,255,0.5);
        }
        .dataTables_wrapper .pagination .page-item.active .page-link {
            background-color: var(--datatables-dark-blue-hover-bg) !important; /* Aktif sedikit lebih terang */
            border-color: var(--datatables-dark-blue-hover-border) !important;
            color: var(--text-color) !important;
            box-shadow: 0 0 10px rgba(0,255,255,0.7); /* Glow untuk aktif */
        }
        .dataTables_wrapper .pagination .page-item.disabled .page-link {
            background-color: var(--datatables-dark-blue-disabled-bg) !important; /* Transparan lebih gelap untuk disabled */
            border-color: var(--datatables-dark-blue-disabled-border) !important;
            color: var(--placeholder-color) !important; /* Teks pudar untuk disabled */
        }

        /* DataTables Search Textfield */
        .dataTables_wrapper .dataTables_filter input[type="search"] {
            background-color: var(--datatables-dark-blue-bg) !important; /* Biru gelap */
            border: 1px solid var(--datatables-dark-blue-border) !important;
            color: var(--text-color) !important; /* Teks putih */
            border-radius: 5px;
            padding: 5px 10px;
            box-shadow: 0 0 5px rgba(0,255,255,0.3);
            transition: all 0.3s ease;
        }
        .dataTables_wrapper .dataTables_filter input[type="search"]:focus {
            background-color: var(--datatables-dark-blue-hover-bg) !important;
            border-color: var(--datatables-dark-blue-hover-border) !important;
            box-shadow: 0 0 10px rgba(0,255,255,0.5);
            color: var(--text-color) !important;
        }
        .dataTables_wrapper .dataTables_filter label {
            color: var(--text-color); /* Label "Cari:" */
        }
        .dataTables_wrapper .dataTables_length select { /* Select box "Show X entries" */
            background-color: var(--form-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: 5px;
            padding: 5px 10px;
            /* Untuk icon panah dropdown neon */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2300FFFF'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .dataTables_wrapper .dataTables_length select option {
            background-color: var(--bg-color); /* Background option item */
            color: var(--text-color); /* Warna teks option item */
        }
        .dataTables_info {
            color: var(--placeholder-color); /* Warna teks info DataTables */
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 255, 255, 0.5);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--neon-blue);
        }

        /* Ripple Effect */
        .ripple {
            position: absolute;
            background: rgba(0,255,255,0.4);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }
        @keyframes ripple {
            to {
                transform: scale(2.5);
                opacity: 0;
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .action-buttons .btn {
                margin-bottom: 5px;
            }
            .filter-form .col-md-3 {
                margin-bottom: 15px;
            }
            .filter-form .d-flex.align-items-end { /* This rule is problematic with new HTML structure */
                /* Consider removing or adjusting in global theme for consistency */
                /* align-items: flex-start !important; */
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
                        <h1 class="m-0">Laporan Transaksi</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Laporan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Daftar Transaksi</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="printReport()">
                                        <i class="fas fa-print"></i> Cetak
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm ml-1" onclick="downloadPDF()">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="mb-4 filter-form">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="start_date">Dari Tanggal</label>
                                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                                        value="<?php echo $filter_start ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="end_date">Sampai Tanggal</label>
                                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                                        value="<?php echo $filter_end ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group filter-buttons-aligner">
                                                <label for="dummyLabel" style="visibility: hidden;">_</label> <div> <button type="submit" class="btn btn-info">
                                                        <i class="fas fa-filter"></i> Filter
                                                    </button>
                                                    <?php if ($filter_start || $filter_end): ?>
                                                    <a href="laporan.php" class="btn btn-secondary ml-2">
                                                        <i class="fas fa-times"></i> Reset
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="card summary-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Total Pemasukan</h5>
                                                <p class="card-text h4">Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card summary-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Total Pengeluaran</h5>
                                                <p class="card-text h4">Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card summary-card">
                                            <div class="card-body">
                                                <h5 class="card-title">Saldo</h5>
                                                <p class="card-text h4">Rp <?php echo number_format($saldo, 0, ',', '.'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <table id="transactionsTable" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Tanggal</th>
                                            <th>Jenis</th>
                                            <th>Jumlah</th>
                                            <th>Nama</th>
                                            <th>Keterangan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $index => $trx): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($trx['tanggal'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $trx['jenis'] == 'masuk' ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo ucfirst($trx['jenis']); ?>
                                                </span>
                                            </td>
                                            <td>Rp <?php echo number_format($trx['jumlah'], 0, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($trx['nama_pengguna'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($trx['keterangan'] ?? '-'); ?></td>
                                            <td class="action-buttons">
                                                <button class="btn btn-info btn-xs print-btn" 
                                                        data-id="<?php echo $trx['id_transaksi']; ?>"
                                                        title="Cetak">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <button class="btn btn-danger btn-xs pdf-btn" 
                                                        data-id="<?php echo $trx['id_transaksi']; ?>"
                                                        title="Download PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($transactions)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Tidak ada data transaksi</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
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
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#transactionsTable').DataTable({
      "responsive": true,
      "autoWidth": false,
      "order": [[1, "desc"]], // Default sort by tanggal descending
      "language": {
        "lengthMenu": "Tampilkan _MENU_ data per halaman",
        "zeroRecords": "Tidak ada data yang ditemukan",
        "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
        "infoEmpty": "Tidak ada data tersedia",
        "infoFiltered": "(difilter dari _MAX_ total data)",
        "search": "Cari:",
        "paginate": {
          "first": "Pertama",
          "last": "Terakhir",
          "next": "Selanjutnya",
          "previous": "Sebelumnya"
        }
      }
    });
    
    // Highlight active nav link
    $('.nav-link').removeClass('active');
    $('.nav-link[href="laporan.php"]').addClass('active');
    
    // Show SweetAlert notifications
    <?php if (!empty($error)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo addslashes($error); ?>',
        timer: 3000,
        showConfirmButton: true
    });
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Sukses!',
        text: '<?php echo addslashes($success); ?>',
        timer: 3000,
        showConfirmButton: true
    });
    <?php endif; ?>
    
    // Print single transaction button
    $('.print-btn').click(function() {
        var id = $(this).data('id');
        window.open(`print_transaksi.php?id=${id}`, '_blank');
    });
    
    // Download PDF single transaction button
    $('.pdf-btn').click(function() {
        var id = $(this).data('id');
        window.open(`pdf_transaksi.php?id=${id}`, '_blank');
    });
});

// Print full report
function printReport() {
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    
    var url = 'print_laporan.php';
    if (startDate || endDate) {
        url += `?start_date=${startDate}&end_date=${endDate}`;
    }
    
    window.open(url, '_blank');
}

// Download PDF full report
function downloadPDF() {
    var startDate = $('#start_date').val();
    var endDate = $('#end_date').val();
    
    var url = 'pdf_laporan.php';
    if (startDate || endDate) {
        url += `?start_date=${startDate}&end_date=${endDate}`;
    }
    
    window.open(url, '_blank');
}
</script>

</body>
</html>