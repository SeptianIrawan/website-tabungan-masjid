<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Periksa role pengguna
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Jika bukan admin, alihkan ke dashboard atau tampilkan pesan error
    $_SESSION['error'] = 'Anda tidak memiliki izin untuk mengakses halaman ini.';
    header("Location: dashboard.php"); 
    exit();
}

require_once "db.php";

// Initialize variables with default values
$error = null;
$success = null;
$import_result = null;

try {
    
    // Handle form submission for add/edit
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
        $id = $_POST['id'] ?? null;
        $nama = $_POST['nama'];
        $role = $_POST['role'];
        $email = $_POST['email'];
        $password = $_POST['password']; // Ini adalah password plaintext dari form
        
        if ($id) {
            // Update existing user
            if (!empty($password)) {
                // HASH PASSWORD DENGAN password_hash() SAAT UPDATE
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE pengguna SET nama = ?, role = ?, email = ?, password = ? WHERE id_pengguna = ?");
                $stmt->execute([$nama, $role, $email, $hashed_password, $id]);
            } else {
                // Jika password kosong, jangan update kolom password
                $stmt = $pdo->prepare("UPDATE pengguna SET nama = ?, role = ?, email = ? WHERE id_pengguna = ?");
                $stmt->execute([$nama, $role, $email, $id]);
            }
            $_SESSION['success'] = 'Pengguna berhasil diperbarui!';
        } else {
            // Add new user
            // HASH PASSWORD DENGAN password_hash() SAAT TAMBAH PENGGUNA BARU
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO pengguna (nama, role, email, password, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$nama, $role, $email, $hashed_password]);
            $_SESSION['success'] = 'Pengguna baru berhasil ditambahkan!';
        }
        
        header("Location: pengguna.php");
        exit();
    }
    
    // Handle import from Excel
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file_import'])) {
        // Pastikan vendor/autoload.php sudah diatur dengan benar (dari Composer)
        require_once 'vendor/autoload.php'; 
        
        $file = $_FILES['file_import']['tmp_name'];
        $file_name = $_FILES['file_import']['name'];
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
        
        try {
            if ($file_ext == 'xlsx' || $file_ext == 'xls') {
                $reader = ($file_ext == 'xlsx') 
                    ? new \PhpOffice\PhpSpreadsheet\Reader\Xlsx() 
                    : new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                
                $spreadsheet = $reader->load($file);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
                
                $imported = 0;
                $skipped = 0;
                $errors = [];
                
                // Skip header row (row 1)
                for ($i = 1; $i < count($rows); $i++) {
                    $row = $rows[$i];
                    
                    // Skip empty rows
                    if (empty($row[0])) {
                        $skipped++; // Tambahkan ke skipped jika baris kosong
                        continue;
                    }
                    
                    $nama = trim($row[0]);
                    // Default role 'user' jika kosong atau bukan 'admin'
                    $role = !empty($row[1]) && strtolower(trim($row[1])) == 'admin' ? 'admin' : 'user';
                    $email = trim($row[2]);
                    // Gunakan password default jika kolom password kosong
                    $password_excel = !empty($row[3]) ? trim($row[3]) : 'user123'; 
                    
                    // Validasi data sebelum import
                    if (empty($nama)) {
                        $errors[] = "Baris ".($i+1).": Nama tidak boleh kosong.";
                        $skipped++;
                        continue;
                    }
                    
                    if (empty($email)) {
                        $errors[] = "Baris ".($i+1).": Email tidak boleh kosong.";
                        $skipped++;
                        continue;
                    }
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Baris ".($i+1).": Format email tidak valid.";
                        $skipped++;
                        continue;
                    }
                    
                    if (strlen($password_excel) < 6) {
                        $errors[] = "Baris ".($i+1).": Password minimal 6 karakter.";
                        $skipped++;
                        continue;
                    }
                    
                    // Check if email already exists to prevent duplicates
                    $check = $pdo->prepare("SELECT id_pengguna FROM pengguna WHERE email = ?");
                    $check->execute([$email]);
                    
                    if ($check->rowCount() > 0) {
                        $errors[] = "Baris ".($i+1).": Email '{$email}' sudah terdaftar, dilewati.";
                        $skipped++;
                        continue;
                    }
                    
                    // HASH PASSWORD DENGAN password_hash() SAAT IMPORT
                    $hashed_password_excel = password_hash($password_excel, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $pdo->prepare("INSERT INTO pengguna (nama, role, email, password, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$nama, $role, $email, $hashed_password_excel]);
                    $imported++;
                }
                
                $message = "Import selesai!<br>Berhasil: $imported data<br>Dilewati: $skipped data";
                if (!empty($errors)) {
                    $message .= "<br><br>Detail Error:<br>- " . implode("<br>- ", $errors);
                }
                
                $_SESSION['import_result'] = [
                    'success' => $imported > 0 && empty($errors), // Status sukses jika ada yang diimport dan tidak ada error fatal
                    'message' => $message,
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors
                ];
                
                header("Location: pengguna.php");
                exit();
            } else {
                throw new Exception("Format file tidak didukung. Harap upload file Excel (.xlsx atau .xls)");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error saat import: " . $e->getMessage();
            header("Location: pengguna.php");
            exit();
        }
    }
    
    // Handle delete action
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM pengguna WHERE id_pengguna = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = 'Pengguna berhasil dihapus!';
        header("Location: pengguna.php");
        exit();
    }
    
    // Get user data for editing
    $edit_user = null;
    if (isset($_GET['edit'])) {
        $id = $_GET['edit'];
        $stmt = $pdo->prepare("SELECT * FROM pengguna WHERE id_pengguna = ?");
        $stmt->execute([$id]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$edit_user) {
            $_SESSION['error'] = 'Pengguna tidak ditemukan!';
            header("Location: pengguna.php");
            exit();
        }
    }
    
    // Get all users
    $stmt = $pdo->query("SELECT * FROM pengguna ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Koneksi database gagal: ' . $e->getMessage();
    $users = []; // Pastikan $users tetap terdefinisi agar tidak error di HTML
}

// Get notification messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['import_result'])) {
    $import_result = $_SESSION['import_result'];
    unset($_SESSION['import_result']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Pengguna</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* =================================================================== */
        /* TEMA GELAP DENGAN GAYA CYAN/TEAL                                    */
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
        .action-buttons .btn {
            margin-right: 5px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        /* Table Styles */
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
        .badge-primary { background-color: var(--info-color) !important; }
        .badge-success { background-color: var(--success-color) !important; }

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
        
        /* Custom File Input */
        .custom-file-label {
            background-color: #2a2a2a;
            border: 1px solid #444;
            color: #adb5bd;
        }
        .custom-file-label::after {
            background-color: #004d4d !important;
            color: var(--primary-color) !important;
            border-left: 1px solid #444 !important;
            content: "Pilih file..." !important;
        }
        
        /* Import Instructions */
        .alert.alert-info {
            background-color: rgba(23, 162, 184, 0.1) !important;
            border-color: var(--info-color) !important;
            color: #bee5eb !important;
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
                        <h1 class="m-0">Data Pengguna</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Pengguna</li>
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
                                <h3 class="card-title">Daftar Pengguna Sistem</h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-success btn-sm mr-2" data-toggle="modal" data-target="#importModal">
                                        <i class="fas fa-file-import"></i> Import Excel
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#userModal" onclick="resetForm()">
                                        <i class="fas fa-plus"></i> Tambah Pengguna
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <table id="usersTable" class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama</th>
                                            <th>Role</th>
                                            <th>Email</th>
                                            <th>Tanggal Daftar</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($users)): ?>
                                            <?php foreach ($users as $index => $user): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($user['nama']) ?></td>
                                                <td>
                                                    <span class="badge <?= $user['role'] == 'admin' ? 'badge-primary' : 'badge-success' ?>">
                                                        <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-info btn-xs edit-btn" 
                                                            data-id="<?= $user['id_pengguna'] ?>"
                                                            data-nama="<?= htmlspecialchars($user['nama']) ?>"
                                                            data-role="<?= htmlspecialchars($user['role']) ?>"
                                                            data-email="<?= htmlspecialchars($user['email']) ?>"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-xs delete-btn" 
                                                            data-id="<?= $user['id_pengguna'] ?>"
                                                            data-nama="<?= htmlspecialchars($user['nama']) ?>"
                                                            title="Hapus">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">Tidak ada data pengguna.</td>
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

    <div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Tambah Pengguna Baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="pengguna.php" id="userForm">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id" value="">
                        
                        <div class="form-group">
                            <label for="nama">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small id="passwordHelp" class="text-muted d-none">Kosongkan jika tidak ingin mengubah password</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" name="save_user" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Data Pengguna</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="pengguna.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="file_import">Pilih File Excel</label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="file_import" name="file_import" accept=".xlsx,.xls" required>
                                <label class="custom-file-label" for="file_import">Pilih file...</label>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info"></i> Petunjuk Import:</h5>
                            <ol>
                                <li>Download template Excel terlebih dahulu</li>
                                <li>Isi data sesuai format</li>
                                <li>Kolom password boleh dikosongkan (akan diisi default)</li>
                                <li>Role harus diisi 'admin' atau 'user'</li>
                            </ol>
                            <a href="template_excel.php" class="btn btn-sm btn-success">
                                <i class="fas fa-file-download"></i> Download Template
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-success">Import Data</button>
                    </div>
                </form>
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#usersTable').DataTable({
      responsive: true,
      autoWidth: false,
      order: [[4, "desc"]],
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
    
    // Highlight active nav link (sesuai kebutuhan Anda)
    $('.nav-link').removeClass('active');
    $('.nav-link[href="pengguna.php"]').addClass('active');
    
    // Show notifications (dari session PHP)
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
    
    <?php if (isset($import_result)): ?>
    Swal.fire({
        icon: <?= json_encode($import_result['success'] ? 'success' : 'warning') ?>,
        title: 'Hasil Import',
        html: <?= json_encode($import_result['message']) ?>,
        showConfirmButton: true,
        width: '800px'
    });
    <?php endif; ?>
    
    // Edit button handler
    $('.edit-btn').click(function() {
        $('#userModalLabel').text('Edit Pengguna');
        $('#edit_id').val($(this).data('id'));
        $('#nama').val($(this).data('nama'));
        $('#role').val($(this).data('role'));
        $('#email').val($(this).data('email'));
        $('#password').val(''); // Kosongkan field password saat edit
        $('#password').removeAttr('required'); // Password tidak wajib diisi saat edit
        $('#passwordHelp').removeClass('d-none'); // Tampilkan pesan bantuan
        $('#userModal').modal('show');
    });
    
    // Delete button handler
    $('.delete-btn').click(function() {
        const userName = $(this).data('nama');
        Swal.fire({
            title: 'Apakah Anda yakin?',
            html: `Anda akan menghapus pengguna <b>${userName}</b>!<br>Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `pengguna.php?delete=${$(this).data('id')}`;
            }
        });
    });
    
    // Form validation for userForm (add/edit)
    $('#userForm').submit(function(e) {
        var password = $('#password').val();
        var isEdit = $('#edit_id').val() !== '';
        
        // Untuk mode tambah pengguna atau jika password diisi saat edit
        if ((!isEdit && password.length < 6) || (isEdit && password.length > 0 && password.length < 6)) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Password harus minimal 6 karakter!'
            });
            e.preventDefault(); // Mencegah form submit
        }
    });
    
    // Show file name on import modal
    $('#file_import').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
});

// Fungsi untuk mereset form modal tambah/edit pengguna
function resetForm() {
    $('#userModalLabel').text('Tambah Pengguna Baru');
    $('#edit_id').val('');
    $('#nama').val('');
    $('#role').val('user'); // Set default role ke 'user' saat tambah baru
    $('#email').val('');
    $('#password').val('').attr('required', true); // Password wajib diisi saat tambah baru
    $('#passwordHelp').addClass('d-none'); // Sembunyikan pesan bantuan
    $('#userForm')[0].reset(); // Reset semua field form
}
</script>
</body>
</html>
