<?php
// tabungan.php

// Pastikan session sudah dimulai. Jika file ini adalah halaman utama setelah login,
// session_start() sudah ada di sini dan db.php dimuat.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login. Jika tidak, arahkan ke halaman login/index.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php"); // Ganti ke login.php jika itu halaman login Anda
    exit();
}

require_once "db.php"; // Memuat koneksi database

// Set zona waktu PHP ke WIB (Pekanbaru) untuk konsistensi waktu di server
date_default_timezone_set('Asia/Jakarta');

// Inisialisasi variabel
$saldo_saat_ini = 0; // Saldo spesifik user yang login
$error = '';
$success = '';

// ID pengguna yang sedang login
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_name = $_SESSION['user_name'] ?? 'Pengguna'; // Ambil nama pengguna dari session

if (!$current_user_id) {
    // Jika ID pengguna tidak ada di session, mungkin session bermasalah atau belum login sempurna
    $_SESSION['error'] = 'Sesi pengguna tidak valid. Silakan login kembali.';
    header("Location: index.php");
    exit();
}

try {
    // Handle form submission (Add/Edit Tabungan - ini biasanya hanya untuk admin)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['save_tabungan'])) { // Ini biasanya hanya untuk admin/petugas
            if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'petugas')) {
                $_SESSION['error'] = 'Anda tidak memiliki izin untuk melakukan aksi ini.';
                header("Location: tabungan.php");
                exit();
            }

            $id = $_POST['id'] ?? null;
            $id_pengguna_form = $_POST['id_pengguna']; // ID pengguna dari form (bisa beda dengan user login)
            $jumlah = $_POST['jumlah'];
            $tanggal = $_POST['tanggal'];
            $keterangan = $_POST['keterangan'];
            
            if ($id) {
                // Update existing tabungan
                $stmt = $pdo->prepare("UPDATE tabungan SET id_pengguna=?, jumlah_tabungan=?, tanggal_setor=?, keterangan=? WHERE id_tabungan=?");
                $stmt->execute([$id_pengguna_form, $jumlah, $tanggal, $keterangan, $id]);
                $_SESSION['success'] = 'Data tabungan berhasil diperbarui!';
            } else {
                // Add new tabungan (initial deposit, not via Midtrans)
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO tabungan (id_pengguna, jumlah_tabungan, tanggal_setor, keterangan) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id_pengguna_form, $jumlah, $tanggal, $keterangan]);
                    $id_tabungan = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO transaksi (id_tabungan, id_pengguna, jenis, jumlah, tanggal, keterangan, status_pembayaran) VALUES (?, ?, 'masuk', ?, ?, ?, 'success')");
                    $stmt->execute([$id_tabungan, $id_pengguna_form, $jumlah, $tanggal, 'Setoran awal - ' . $keterangan]);
                    
                    $pdo->commit();
                    $_SESSION['success'] = 'Data tabungan baru berhasil ditambahkan!';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = 'Gagal menambahkan tabungan baru: ' . $e->getMessage();
                    error_log("Error adding new tabungan: " . $e->getMessage());
                }
            }
        } 
        // Handle withdrawal (kurangi saldo) - internal process
        elseif (isset($_POST['transaksi']) && $_POST['jenis'] == 'keluar') { 
            $id_tabungan = $_POST['id_tabungan'];
            $jenis = $_POST['jenis']; // 'keluar'
            $jumlah = $_POST['jumlah'];
            $keterangan = $_POST['keterangan'];
            
            // Verifikasi bahwa tabungan yang ditarik adalah milik user yang login
            $stmt_check = $pdo->prepare("SELECT jumlah_tabungan, id_pengguna FROM tabungan WHERE id_tabungan=? AND id_pengguna=?");
            $stmt_check->execute([$id_tabungan, $current_user_id]);
            $current = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                $_SESSION['error'] = 'Data tabungan tidak ditemukan atau bukan milik Anda.';
                header("Location: tabungan.php");
                exit();
            }

            if ($current['jumlah_tabungan'] < $jumlah) {
                $_SESSION['error'] = 'Saldo tidak cukup untuk penarikan ini.';
                header("Location: tabungan.php");
                exit();
            }

            $new_balance = $current['jumlah_tabungan'] - $jumlah;
            
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE tabungan SET jumlah_tabungan=? WHERE id_tabungan=?");
                $stmt->execute([$new_balance, $id_tabungan]);
                
                $stmt = $pdo->prepare("INSERT INTO transaksi (id_tabungan, id_pengguna, jenis, jumlah, keterangan, tanggal, status_pembayaran) VALUES (?, ?, ?, ?, ?, NOW(), 'success')");
                $stmt->execute([$id_tabungan, $current_user_id, $jenis, $jumlah, $keterangan]);
                
                $pdo->commit();
                $_SESSION['success'] = 'Transaksi penarikan berhasil dicatat!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = 'Gagal mencatat transaksi penarikan: ' . $e->getMessage();
                error_log("Error recording withdrawal: " . $e->getMessage());
            }
        }
        
        header("Location: tabungan.php"); // Redirect after any POST
        exit();
    }
    
    // Handle delete action (only for admin/petugas)
    if (isset($_GET['delete'])) {
        if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'petugas')) {
            $_SESSION['error'] = 'Anda tidak memiliki izin untuk melakukan aksi ini.';
            header("Location: tabungan.php");
            exit();
        }
        $id = $_GET['delete'];
        $pdo->beginTransaction();
        try {
            $stmt_trans = $pdo->prepare("DELETE FROM transaksi WHERE id_tabungan=?");
            $stmt_trans->execute([$id]);

            $stmt_tabungan = $pdo->prepare("DELETE FROM tabungan WHERE id_tabungan=?");
            $stmt_tabungan->execute([$id]);
            
            $pdo->commit();
            $_SESSION['success'] = 'Data tabungan dan transaksi terkait berhasil dihapus!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Gagal menghapus data tabungan: ' . $e->getMessage();
            error_log("Error deleting tabungan: " . $e->getMessage());
        }
        header("Location: tabungan.php");
        exit();
    }
    
    // Get tabungan data for the *logged-in user*
    $stmt = $pdo->prepare("SELECT t.*, p.nama 
                           FROM tabungan t 
                           JOIN pengguna p ON t.id_pengguna = p.id_pengguna 
                           WHERE t.id_pengguna = ? 
                           ORDER BY t.tanggal_setor DESC");
    $stmt->execute([$current_user_id]);
    $tabungan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total saldo for the logged-in user
    if (!empty($tabungan)) {
        // Asumsi satu pengguna punya satu rekening tabungan di sini
        // Jika satu pengguna bisa punya banyak rekening, ini perlu diubah
        $saldo_saat_ini = $tabungan[0]['jumlah_tabungan']; 
        $user_tabungan_id = $tabungan[0]['id_tabungan']; // Ambil ID tabungan user yang login
    } else {
        $user_tabungan_id = null; // User belum punya rekening tabungan
    }

    // Get all transactions for the logged-in user's savings account (for report/history)
    // Note: this data is now specifically for the modal, not directly displayed in table
    // It will be fetched via AJAX by get_transactions.php
    $transactions_history = []; // Initialize empty; actual data loaded by AJAX
    if ($user_tabungan_id) {
        // We still need this check to determine if 'Riwayat' button should be enabled
        $stmt_check_history = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE id_tabungan = ?");
        $stmt_check_history->execute([$user_tabungan_id]);
        $has_transactions = ($stmt_check_history->fetchColumn() > 0);
    } else {
        $has_transactions = false;
    }


    // Get pengguna data for dropdown (for admin/petugas adding new savings)
    $pengguna_query = "SELECT p.id_pengguna, p.nama FROM pengguna p ORDER BY p.nama";
    $stmt_pengguna = $pdo->prepare($pengguna_query);
    $stmt_pengguna->execute();
    $pengguna = $stmt_pengguna->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Koneksi database gagal: ' . $e->getMessage();
    error_log("PDO Error in tabungan.php: " . $e->getMessage());
    $tabungan = [];
    $pengguna = [];
    $transactions_history = []; // Make sure this is initialized
    $has_transactions = false;
}

// Get notification messages from session
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Tabungan - <?= htmlspecialchars($current_user_name) ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* =================================================================== */
        /* TEMA GELAP DENGAN GAYA CYAN/TEAL                                   */
        /* =================================================================== */

        :root {
            --bg-color: #121212;
            --overlay-bg: #1e1e1e;
            --text-color: #e0e0e0;
            --text-strong-color: #ffffff;
            --border-color: #008080; /* Teal gelap untuk border */
            --border-light-color: rgba(0, 255, 255, 0.3); /* Cyan terang untuk glow/border tipis */

            --primary-color: #00FFFF; /* Cyan terang */
            --primary-hover-color: #00E5E5;
            --primary-text-color: #121212; /* Teks gelap untuk kontras di atas tombol cyan */
            
            --success-color: #28a745;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --warning-color: #ffc107; /* Kuning untuk peringatan */
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

        /* --- Global AdminLTE Overrides --- */
        .card {
            border-radius: .5rem;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.1);
            border: 1px solid var(--border-color);
            background-color: var(--overlay-bg);
            margin-bottom: 20px;
        }
        .card-header {
            border-bottom: 1px solid var(--border-color);
            background-color: var(--overlay-bg);
            padding: 1rem 1.25rem;
        }
        .card-title {
            font-weight: 600;
            color: var(--primary-color);
        }
        .content-header h1 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
            color: var(--text-strong-color);
        }
        .breadcrumb-item a {
            color: var(--primary-color) !important;
        }

        /* Tombol Utama */
        .btn-primary { 
            background-color: var(--primary-color) !important; 
            border-color: var(--primary-color) !important;
            color: var(--primary-text-color) !important;
            font-weight: 600;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        .btn-primary:hover { 
            background-color: var(--primary-hover-color) !important; 
            border-color: var(--primary-hover-color) !important;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.8);
        }
        
        /* Tombol lain */
        .btn-success { background-color: var(--success-color); border-color: var(--success-color); }
        .btn-danger { background-color: var(--danger-color); border-color: var(--danger-color); }
        .btn-info { background-color: var(--info-color); border-color: var(--info-color); }
        
        /* Tombol Aksi di Tabel */
        .action-buttons .transaction-btn {
            cursor: pointer;
            margin: 0 5px;
            font-size: 1.1rem;
            transition: all 0.2s ease-in-out;
        }
        .action-buttons .transaction-btn:hover {
            transform: scale(1.2);
        }
        .deposit-btn { color: var(--success-color); }
        .withdraw-btn { color: var(--danger-color); }
        .print-btn { color: var(--info-color); }

        /* Table Styles (for admin view / modal) */
        .table {
            background-color: var(--overlay-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            border-radius: .5rem;
            overflow: hidden;
        }
        .table th {
            background-color: #1A2A2A; /* Teal sangat gelap */
            color: var(--primary-color);
            border-bottom: 2px solid var(--border-color) !important;
            font-weight: 600;
        }
        .table td {
            border-color: #2a2a2a !important;
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: #2c2c2c !important;
            color: var(--text-strong-color) !important;
        }
        .amount {
            font-weight: 600;
            color: var(--text-strong-color);
        }

        /* Modal Styles */
        .modal-content {
            background-color: var(--overlay-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            border-radius: .5rem;
            box-shadow: 0 0 25px rgba(0, 255, 255, 0.2);
        }
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            color: var(--primary-color);
        }
        .modal-header .close {
            color: var(--text-color) !important;
            text-shadow: none;
        }
        .modal-footer {
            border-top: 1px solid var(--border-color);
        }

        /* Form Controls in Modals */
        .modal-body label {
            color: var(--text-color);
            font-weight: 600;
        }
        .modal-body .form-control,
        .modal-body select.form-control {
            background-color: #2a2a2a;
            border: 1px solid #444;
            color: var(--text-color);
        }
        .modal-body .form-control:focus,
        .modal-body select.form-control:focus {
            border-color: var(--primary-color);
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 255, 255, .25);
        }
        .input-group-text {
            background-color: #343a40;
            border: 1px solid #444;
            color: var(--text-color);
        }
        
        /* DataTables Styles */
        .dataTables_wrapper .dataTables_filter input[type="search"] {
            background-color: rgba(0, 100, 100, 0.3) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-strong-color) !important;
            border-radius: .25rem;
            padding: .375rem .75rem;
        }
        .dataTables_wrapper .pagination .page-item .page-link {
            background-color: #2a2a2a;
            border-color: var(--border-color);
            color: var(--text-color) !important;
        }
        .dataTables_wrapper .pagination .page-item.active .page-link {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color) !important;
            color: var(--primary-text-color) !important;
            font-weight: 600;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
        }
        .dataTables_wrapper .pagination .page-item.disabled .page-link {
            color: #6c757d !important;
        }
        .dataTables_info, .dataTables_length label, .dataTables_filter label {
            color: #adb5bd;
        }
        .dataTables_wrapper .dataTables_length select {
            background-color: #2a2a2a;
            border: 1px solid #444;
            color: var(--text-color);
        }

        /* Custom Styles for Saldo Info (DANA-like) */
        .saldo-info-card {
            background: linear-gradient(135deg, #00BFFF, #00FFFF); /* Biru muda ke cyan */
            color: #121212; /* Teks gelap di atas latar terang */
            border-radius: 15px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 255, 255, 0.3);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 180px; /* Tinggi kartu untuk tampilan padat */
            position: relative;
            overflow: hidden;
            width: 100%; /* Membuat kartu ini full lebar di dalam parent col-12 */
            max-width: none; /* Override max-width default jika ada */
        }
        .saldo-info-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        .saldo-info-card .card-title {
            color: #121212;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .saldo-info-card .saldo-amount {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1;
            color: #121212;
            margin-bottom: 15px;
            letter-spacing: -1px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .saldo-info-card .saldo-actions {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        .saldo-info-card .saldo-actions .btn {
            background-color: rgba(0, 0, 0, 0.2); /* Tombol transparan */
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #121212;
            font-weight: 600;
            padding: 10px 15px;
            border-radius: 8px;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .saldo-info-card .saldo-actions .btn:hover {
            background-color: rgba(0, 0, 0, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .saldo-info-card .saldo-actions .btn i {
            font-size: 1.1rem;
        }
        .saldo-info-card .card-body-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Card-tool adjustments for better visual */
        .card-tools {
            position: absolute;
            right: 1.25rem;
            top: 1rem;
        }

        /* Adjust modal sizes for history table */
        #reportModal .modal-dialog {
            max-width: 90%; /* Lebar lebih besar untuk tabel */
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
                        <h1 class="m-0">Data Tabungan Saya</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Tabungan</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card saldo-info-card">
                            <div class="card-body-content">
                                <h3 class="card-title">Saldo Anda</h3>
                                <div class="saldo-amount">
                                    Rp <?= number_format($saldo_saat_ini, 0, ',', '.') ?>
                                </div>
                            </div>
                            <div class="saldo-actions">
                                <button type="button" class="btn btn-sm" 
                                    <?php if (!$user_tabungan_id): ?> disabled title="Anda belum memiliki rekening tabungan. Hubungi admin." <?php endif; ?>
                                    onclick="showTransactionModal(<?= $user_tabungan_id ?? 'null' ?>, 'masuk', '<?= htmlspecialchars($current_user_name) ?>')">
                                    <i class="fas fa-plus"></i> Top Up
                                </button>
                                <button type="button" class="btn btn-sm" 
                                    <?php if (!$user_tabungan_id || $saldo_saat_ini <= 0): ?> disabled title="Saldo tidak cukup atau Anda belum memiliki rekening tabungan." <?php endif; ?>
                                    onclick="showTransactionModal(<?= $user_tabungan_id ?? 'null' ?>, 'keluar')">
                                    <i class="fas fa-minus"></i> Tarik
                                </button>
                                <button type="button" class="btn btn-sm" 
                                    <?php if (!$has_transactions): ?> disabled title="Tidak ada riwayat transaksi." <?php endif; ?>
                                    onclick="loadAndShowReportModal(<?= $user_tabungan_id ?? 'null' ?>)">
                                    <i class="fas fa-history"></i> Riwayat
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'petugas')): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Semua Data Tabungan (Admin View)</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#tabunganModal" onclick="resetForm()">
                                        <i class="fas fa-plus"></i> Tambah Tabungan Baru
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <table id="allTabunganTable" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Pengguna</th>
                                            <th>Jumlah Saldo</th>
                                            <th>Tanggal Setor Awal</th>
                                            <th>Keterangan Awal</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        // Fetch all tabungan data again for admin view
                                        $stmt_all = $pdo->query("SELECT t.*, p.nama FROM tabungan t JOIN pengguna p ON t.id_pengguna = p.id_pengguna ORDER BY t.tanggal_setor DESC");
                                        $all_tabungan = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
                                        if (!empty($all_tabungan)): ?>
                                            <?php foreach ($all_tabungan as $index => $row): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                                <td class="amount">Rp <?= number_format($row['jumlah_tabungan'], 0, ',', '.') ?></td>
                                                <td><?= date('d/m/Y', strtotime($row['tanggal_setor'])) ?></td>
                                                <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                                <td class="action-buttons">
                                                    <span class="transaction-btn deposit-btn" title="Tambah Saldo" onclick="showTransactionModal(<?= $row['id_tabungan'] ?>, 'masuk', '<?= htmlspecialchars($row['nama']) ?>')">
                                                        <i class="fas fa-plus-circle"></i>
                                                    </span>
                                                    <span class="transaction-btn withdraw-btn" title="Kurangi Saldo" onclick="showTransactionModal(<?= $row['id_tabungan'] ?>, 'keluar')">
                                                        <i class="fas fa-minus-circle"></i>
                                                    </span>
                                                    <span class="transaction-btn print-btn" title="Cetak Laporan" onclick="loadAndShowReportModal(<?= $row['id_tabungan'] ?>)">
                                                        <i class="fas fa-print"></i>
                                                    </span>
                                                    <a href="#" class="btn btn-sm btn-info edit-btn" 
                                                       data-id="<?= $row['id_tabungan'] ?>" 
                                                       data-id_pengguna="<?= $row['id_pengguna'] ?>"
                                                       data-jumlah="<?= $row['jumlah_tabungan'] ?>"
                                                       data-tanggal="<?= $row['tanggal_setor'] ?>"
                                                       data-keterangan="<?= htmlspecialchars($row['keterangan']) ?>"
                                                       title="Edit Data">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-sm btn-danger delete-btn" 
                                                       data-id="<?= $row['id_tabungan'] ?>" 
                                                       data-nama="<?= htmlspecialchars($row['nama']) ?>"
                                                       data-jumlah="<?= number_format($row['jumlah_tabungan'], 0, ',', '.') ?>"
                                                       title="Hapus Data">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Tidak ada data tabungan.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </section>
    </div>

    <div class="modal fade" id="tabunganModal" tabindex="-1" role="dialog" aria-labelledby="tabunganModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tabunganModalLabel">Tambah Data Tabungan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="tabungan.php" id="tabunganForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id" value="">
                        
                        <div class="form-group">
                            <label for="id_pengguna">Nama Pengguna</label>
                            <select class="form-control" id="id_pengguna" name="id_pengguna" required>
                                <option value="">-- Pilih Pengguna --</option>
                                <?php foreach ($pengguna as $p): ?>
                                <option value="<?= $p['id_pengguna'] ?>"><?= htmlspecialchars($p['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="jumlah">Jumlah Tabungan</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Rp</span>
                                </div>
                                <input type="number" class="form-control" id="jumlah" name="jumlah" required min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="tanggal">Tanggal Setor</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="keterangan">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" name="save_tabungan" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="transactionModal" tabindex="-1" role="dialog" aria-labelledby="transactionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="transactionModalLabel">Transaksi Tabungan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="tabungan.php" id="transactionForm">
                    <div class="modal-body">
                        <input type="hidden" name="id_tabungan" id="trans_id_tabungan" value="<?= $user_tabungan_id ?? '' ?>">
                        <input type="hidden" name="jenis" id="trans_jenis">
                        <input type="hidden" id="trans_nama_pengguna" value="<?= htmlspecialchars($current_user_name) ?>"> 
                        
                        <div class="form-group">
                            <label id="trans_label">Jumlah</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">Rp</span>
                                </div>
                                <input type="number" class="form-control" id="trans_jumlah" name="jumlah" required min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="trans_keterangan">Keterangan</label>
                            <textarea class="form-control" id="trans_keterangan" name="keterangan" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" name="transaksi" id="saveTransactionButton" class="btn btn-primary">Simpan Transaksi</button>
                        <button type="button" id="midtransPaymentButton" class="btn btn-success" style="display:none;">Bayar dengan Midtrans</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reportModal" tabindex="-1" role="dialog" aria-labelledby="reportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document"> <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalLabel">Riwayat Transaksi Tabungan Anda</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="reportContent">
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="printReportContent()">
                        <i class="fas fa-print"></i> Cetak Laporan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="text/javascript" src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-3VdtTZertP794s93"></script> 

<script>
// Fungsi yang akan dipanggil saat DOM siap
$(document).ready(function() {
    // Inisialisasi DataTable untuk Tabel Admin View (jika ditampilkan)
    <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'petugas')): ?>
    $('#allTabunganTable').DataTable({
      responsive: true,
      autoWidth: false,
      order: [[0, "asc"]],
      language: {
        lengthMenu: "Tampilkan _MENU_ data per halaman",
        zeroRecords: "Tidak ada data yang ditemukan",
        info: "Menampilkan halaman _PAGE_ dari _PAGES_",
        infoEmpty: "Tidak ada data tersedia",
        infoFiltered: "(difilter dari _MAX_ total data)",
        search: "Cari:",
        paginate: {
          first: "Pertama",
          last: "Terakhir",
          next: "Selanjutnya",
          previous: "Sebelumnya"
        }
      }
    });
    <?php endif; ?>

    // Highlight active nav link
    $('.nav-link').removeClass('active');
    $('.nav-link[href="tabungan.php"]').addClass('active');
    
    // Show SweetAlert notifications
    <?php if (isset($error)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: <?= json_encode($error) ?>,
        timer: 3000,
        showConfirmButton: true
    });
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Sukses!',
        text: <?= json_encode($success) ?>,
        timer: 3000,
        showConfirmButton: true
    });
    <?php endif; ?>
    
    // Edit button click handler (for admin/petugas)
    $('.edit-btn').click(function() {
        var id = $(this).data('id');
        var id_pengguna = $(this).data('id_pengguna');
        var jumlah = $(this).data('jumlah');
        var tanggal = $(this).data('tanggal');
        var keterangan = $(this).data('keterangan');
        
        $('#tabunganModalLabel').text('Edit Data Tabungan');
        $('#edit_id').val(id);
        $('#id_pengguna').val(id_pengguna);
        $('#jumlah').val(jumlah);
        $('#tanggal').val(tanggal);
        $('#keterangan').val(keterangan);
        
        $('#tabunganModal').modal('show');
    });
    
    // Delete button click handler (for admin/petugas)
    $('.delete-btn').click(function() {
        var id = $(this).data('id');
        var nama = $(this).data('nama');
        var jumlah = $(this).data('jumlah');
        
        Swal.fire({
            title: 'Apakah Anda yakin?',
            html: `Anda akan menghapus data tabungan dari <b>${nama}</b> sebesar <b>Rp ${jumlah}</b>!<br>Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `tabungan.php?delete=${id}`;
            }
        });
    });
});

function resetForm() {
    $('#tabunganModalLabel').text('Tambah Data Tabungan');
    $('#edit_id').val('');
    $('#id_pengguna').val('');
    $('#jumlah').val('');
    $('#tanggal').val('<?= date('Y-m-d') ?>');
    $('#keterangan').val('');
    $('#tabunganForm')[0].reset();
}

function showTransactionModal(id_tabungan, jenis, nama_pengguna = '') { 
    if (!id_tabungan) {
        Swal.fire('Informasi', 'Anda belum memiliki rekening tabungan. Silakan hubungi admin untuk membuatnya.', 'info');
        return;
    }

    $('#trans_id_tabungan').val(id_tabungan);
    $('#trans_jenis').val(jenis);
    
    if (jenis == 'masuk') {
        $('#transactionModalLabel').text('Tambah Saldo (Top Up)');
        $('#trans_label').text('Jumlah Setoran');
        $('#trans_nama_pengguna').val(nama_pengguna); 
        $('#midtransPaymentButton').show(); 
        $('#saveTransactionButton').hide(); 
    } else { // jenis == 'keluar'
        $('#transactionModalLabel').text('Kurangi Saldo (Penarikan)');
        $('#trans_label').text('Jumlah Penarikan');
        $('#midtransPaymentButton').hide(); 
        $('#saveTransactionButton').show(); 
    }
    
    $('#trans_jumlah').val('');
    $('#trans_keterangan').val('');
    $('#transactionModal').modal('show');
}

// New function to handle Midtrans payment initiation
$('#midtransPaymentButton').click(function(event) {
    event.preventDefault(); 

    var id_tabungan = $('#trans_id_tabungan').val();
    var amount = $('#trans_jumlah').val();
    var keterangan = $('#trans_keterangan').val();
    var nama_pengguna = $('#trans_nama_pengguna').val(); 
    
    if (!amount || parseFloat(amount) <= 0) {
        Swal.fire('Peringatan!', 'Jumlah setoran tidak boleh kosong atau nol.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Memproses Pembayaran...',
        text: 'Harap tunggu, kami sedang menyiapkan transaksi Anda.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'process_deposit.php', 
        type: 'POST',
        data: { 
            id_tabungan: id_tabungan, 
            jumlah: amount, 
            keterangan: keterangan,
            nama_pengguna: nama_pengguna
        },
        dataType: 'json',
        success: function(response) {
            Swal.close(); 
            if (response.token) {
                snap.pay(response.token, {
                    onSuccess: function(result) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Pembayaran Berhasil!',
                            text: 'Saldo Anda akan segera diperbarui.',
                            showConfirmButton: true
                        }).then(() => {
                            window.location.reload(); 
                        });
                    },
                    onPending: function(result) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Pembayaran Tertunda',
                            text: 'Harap selesaikan pembayaran Anda.',
                            showConfirmButton: true
                        }).then(() => {
                            window.location.reload(); 
                        });
                    },
                    onError: function(result) {
                        Swal.fire('Error!', 'Pembayaran gagal. Silakan coba lagi.', 'error');
                        console.error("Midtrans Snap Error:", result);
                    },
                    onClose: function() {
                        Swal.fire('Informasi', 'Anda menutup jendela pembayaran.', 'info');
                    }
                });
            } else {
                Swal.fire('Error!', response.error || 'Gagal membuat transaksi Midtrans.', 'error');
                console.error("Backend response error:", response);
            }
        },
        error: function(xhr, status, error) {
            Swal.close(); 
            console.error("AJAX Error:", status, error, xhr.responseText);
            Swal.fire('Error!', 'Terjadi kesalahan saat menghubungi server. Mohon periksa konsol browser atau log server Anda.', 'error');
        }
    });
});

// Fungsi baru untuk memuat dan menampilkan modal riwayat
function loadAndShowReportModal(id_tabungan) {
    if (!id_tabungan) {
        Swal.fire('Informasi', 'Tidak ada rekening tabungan untuk menampilkan riwayat.', 'info');
        return;
    }

    $('#reportContent').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-3x"></i><p>Memuat riwayat transaksi...</p></div>');
    $('#reportModal').modal('show');
    
    $.ajax({
        url: 'get_transactions.php', // File ini akan mengembalikan HTML tabel riwayat
        type: 'GET',
        data: { id_tabungan: id_tabungan },
        success: function(response) {
            // Setelah konten dimuat, inisialisasi DataTable di dalamnya
            $('#reportContent').html(response);
            $('#historyTable').DataTable({ // ID tabel di dalam get_transactions.php
                responsive: true,
                autoWidth: false,
                order: [[0, "desc"]], // Urutkan berdasarkan tanggal terbaru
                language: {
                    lengthMenu: "Tampilkan _MENU_ data per halaman",
                    zeroRecords: "Tidak ada data yang ditemukan",
                    info: "Menampilkan halaman _PAGE_ dari _PAGES_",
                    infoEmpty: "Tidak ada data tersedia",
                    infoFiltered: "(difilter dari _MAX_ total data)",
                    search: "Cari:",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "Selanjutnya",
                        previous: "Sebelumnya"
                    }
                }
            });
        },
        error: function() {
            $('#reportContent').html('<div class="alert alert-danger">Gagal memuat riwayat transaksi.</div>');
        }
    });
}

// Fungsi cetak laporan (akan mencetak konten dari modal riwayat)
function printReportContent() {
    var printContents = document.getElementById('reportContent').innerHTML;
    var originalContents = document.body.innerHTML;
    
    var printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Laporan Tabungan</title>');
    // Copy necessary styles for printing (minimal)
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: \'Poppins\', sans-serif; margin: 20px; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
    printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
    printWindow.document.write('th { background-color: #f2f2f2; }');
    printWindow.document.write('.text-center { text-align: center; }');
    printWindow.document.write('.text-right { text-align: right; }');
    printWindow.document.write('@media print { body { -webkit-print-color-adjust: exact; } }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(printContents);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>

</body>
</html>