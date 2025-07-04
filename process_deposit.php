<?php
// process_deposit.php

session_start(); // Selalu mulai session di awal file

// Muat koneksi database dan library Composer
require_once "db.php";
require_once __DIR__ . '/vendor/autoload.php';

// --- PENGATURAN DEBUGGING ---
// Aktifkan baris-baris ini untuk melihat error PHP di browser selama pengembangan.
// PASTIKAN UNTUK MENGHAPUS/KOMENTARI DI LINGKUNGAN PRODUKSI!
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// --- KONFIGURASI MIDTRANS ---
// Ganti dengan SERVER KEY Anda yang sebenarnya dari Midtrans Dashboard
// Contoh: 'SB-Mid-server-YOURSERVERKEY' untuk Sandbox, atau 'Mid-server-YOURSERVERKEY' untuk Produksi
\Midtrans\Config::$serverKey = 'SB-Mid-server-KbdWD4dq_FnGvq8y1spiPNqh'; // <<< Pastikan ini Server Key Anda yang Benar!
\Midtrans\Config::$isProduction = false; // Ubah ke TRUE jika sudah di lingkungan produksi (LIVE)
\Midtrans\Config::$isSanitized = true;   // Aktifkan sanitasi input
\Midtrans\Config::$is3ds = true;         // Aktifkan 3D Secure untuk kartu kredit

// Cek apakah request datang dari metode POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari request POST, gunakan null coalescing operator untuk mencegah undefined index
    $id_tabungan = $_POST['id_tabungan'] ?? null;
    $amount = $_POST['jumlah'] ?? null;
    $keterangan = $_POST['keterangan'] ?? '';
    $nama_pengguna = $_POST['nama_pengguna'] ?? '';
    
    // Ambil ID pengguna dari session. Ini diasumsikan sudah diatur saat login.
    $id_pengguna = $_SESSION['user_id'] ?? null; 

    // --- VALIDASI INPUT DASAR ---
    // Pastikan semua data penting ada dan valid
    if (empty($id_tabungan) || !is_numeric($id_tabungan) || $id_tabungan <= 0) {
        echo json_encode(['error' => 'ID Tabungan tidak valid.']);
        exit();
    }
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        echo json_encode(['error' => 'Jumlah setoran tidak valid. Jumlah harus angka positif.']);
        exit();
    }
    if (empty($nama_pengguna)) {
        echo json_encode(['error' => 'Nama pengguna tidak ditemukan.']);
        exit();
    }
    if (empty($id_pengguna)) {
        echo json_encode(['error' => 'ID Pengguna tidak ditemukan di sesi. Harap login kembali.']);
        exit();
    }

    // --- PERSIAPAN TRANSAKSI MIDTRANS ---

    // Buat order ID unik untuk transaksi Midtrans. Penting untuk rekonsiliasi.
    $order_id = 'TRX-' . $id_tabungan . '-' . uniqid(); 

    // Detail Transaksi yang akan dikirim ke Midtrans
    $transaction_details = [
        'order_id' => $order_id,
        'gross_amount' => (int)$amount, // Pastikan gross_amount adalah integer
    ];

    // Detail Pelanggan (opsional tapi sangat disarankan untuk fraud detection)
    $customer_details = [
        'first_name' => $nama_pengguna,
        // Anda bisa menambahkan detail lain dari sesi atau database jika ada:
        // 'email' => $_SESSION['user_email'] ?? '', 
        // 'phone' => $_SESSION['user_phone'] ?? '', 
    ];

    // Parameter lengkap untuk membuat transaksi Snap
    $params = [
        'transaction_details' => $transaction_details,
        'customer_details' => $customer_details,
        'item_details' => [ // Item yang dibeli (opsional, tapi bagus untuk detail laporan)
            [
                'id' => 'deposit-'.$id_tabungan,
                'price' => (int)$amount,
                'quantity' => 1,
                'name' => 'Setoran Tabungan ' . $nama_pengguna
            ]
        ],
        // --- PERBAIKAN UNTUK ERROR "expiry unit & duration must present" ---
        // Jika Anda ingin mengatur masa berlaku transaksi, pastikan 'unit' dan 'time' ada dan valid.
        // Jika tidak, hapus atau komentari seluruh blok 'expiry'.
        // 'expiry' => [ 
        //     'unit' => 'hours', // Contoh: 'hours', 'minutes', 'days'
        //     'time' => 24,      // Contoh: 24 jam (sesuaikan durasi yang diinginkan)
        // ],
    ];

    try {
        // --- PROSES DENGAN MIDTRANS ---
        // Panggil Midtrans Snap API untuk mendapatkan token
        $snapToken = \Midtrans\Snap::getSnapToken($params);

        // --- SIMPAN TRANSAKSI DI DATABASE (STATUS PENDING) ---
        // Ini crucial untuk melacak transaksi yang belum selesai dan untuk rekonsiliasi via webhook.
        $stmt = $pdo->prepare("INSERT INTO transaksi (id_tabungan, id_pengguna, jenis, jumlah, tanggal, keterangan, status_pembayaran, order_id) VALUES (?, ?, 'masuk', ?, NOW(), ?, 'pending', ?)");
        $stmt->execute([$id_tabungan, $id_pengguna, $amount, 'Setoran via Midtrans: ' . $keterangan, $order_id]);

        // Berikan Snap Token kembali ke frontend dalam format JSON
        echo json_encode(['token' => $snapToken]);

    } catch (Exception $e) {
        // Tangani kesalahan dari Midtrans API atau proses PHP lainnya
        error_log("Midtrans API / PHP Error in process_deposit.php: " . $e->getMessage() . " for Order ID: " . ($order_id ?? 'N/A'));
        echo json_encode(['error' => 'Gagal memproses transaksi: ' . $e->getMessage()]);
    }

} else {
    // Jika bukan metode POST, kembalikan error
    echo json_encode(['error' => 'Metode permintaan tidak valid. Hanya POST yang diizinkan.']);
}

exit(); // Pastikan tidak ada output lain setelah ini
?>