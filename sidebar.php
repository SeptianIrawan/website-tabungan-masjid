<?php
// sidebar.php

// Pastikan session sudah dimulai di file utama (misal: dashboard.php, tabungan.php)
// Ini adalah safety measure, idealnya session_start() hanya di halaman induk.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Mendapatkan nama file saat ini untuk penandaan active link
$current_page = basename($_SERVER['PHP_SELF']);

// Ambil role pengguna dari session
$user_role = $_SESSION['user_role'] ?? ''; // Jika belum login, role akan kosong
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="dashboard.php" class="brand-link">
        <i class="fas fa-mosque brand-image" style="font-size: 2rem; margin-right: 10px; opacity: .8"></i>
        <span class="brand-text font-weight-light" style="font-size: 1rem;">Masjid Rahmatullah</span>
    </a>

    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <?php if ($user_role == 'admin'): // Tampilkan link Pengguna hanya jika role adalah 'admin' ?>
                <li class="nav-item">
                    <a href="pengguna.php" class="nav-link <?php echo ($current_page == 'pengguna.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Pengguna</p>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a href="tabungan.php" class="nav-link <?php echo ($current_page == 'tabungan.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-money-bill-wave"></i>
                        <p>Tabungan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="laporan.php" class="nav-link <?php echo ($current_page == 'laporan.php') ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Laporan</p>
                    </a>
                </li>
                
                <li class="nav-item mt-3"> 
                    <a href="logout.php" class="nav-link" id="logoutButton">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Keluar</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Pastikan DOM sudah dimuat sebelum menambahkan event listener
document.addEventListener('DOMContentLoaded', function() {
    const logoutButton = document.getElementById('logoutButton');
    if (logoutButton) { // Pastikan tombol ada
        logoutButton.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Konfirmasi Keluar',
                text: "Apakah Anda yakin ingin keluar dari sistem?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Keluar',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        });
    }
});
</script>