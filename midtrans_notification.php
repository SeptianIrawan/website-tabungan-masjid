<?php
session_start(); // Pastikan session dimulai jika Anda menggunakannya di sini, meskipun tidak kritis untuk notifikasi.
require_once "db.php"; // Ini akan memuat koneksi $pdo Anda
include  "vendor/autoload.php";

// Set konfigurasi Midtrans
\Midtrans\Config::$isProduction = false; // Ubah ke true jika sudah production
\Midtrans\Config::$serverKey = 'SB-Mid-server-KbdWD4dq_FnGvq8y1spiPNqh'; // <<< MASUKKAN SERVER KEY ANDA DARI GAMBAR

// Untuk debugging, hapus di produksi
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
file_put_contents('midtrans_notif.log', date('Y-m-d H:i:s') . " - Notification received.\n", FILE_APPEND);


try {
    // Dapatkan payload notifikasi dari Midtrans
    $notif = new \Midtrans\Notification();

    $transaction = $notif->transaction_status;
    $type = $notif->payment_type;
    $order_id = $notif->order_id;
    $fraud = $notif->fraud_status;
    $gross_amount = $notif->gross_amount; // Ambil gross_amount dari notifikasi

    file_put_contents('midtrans_notif.log', date('Y-m-d H:i:s') . " - Processing Order ID: " . $order_id . ", Status: " . $transaction . ", Amount: " . $gross_amount . "\n", FILE_APPEND);

    $pdo->beginTransaction(); // Mulai transaksi database

    // Cari transaksi yang tertunda berdasarkan order_id
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE order_id = ? AND status_pembayaran = 'pending'");
    $stmt->execute([$order_id]);
    $pending_transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pending_transaction) {
        file_put_contents('midtrans_notif.log', "Order ID: " . $order_id . " - Error: Transaksi tertunda tidak ditemukan atau sudah diproses. Status yang diterima: " . $transaction . "\n", FILE_APPEND);
        http_response_code(400); // Bad request, transaksi tidak ditemukan atau sudah diproses
        $pdo->rollBack(); // Pastikan rollback jika ada error sebelum commit
        exit();
    }

    $id_tabungan = $pending_transaction['id_tabungan'];
    $jumlah_transaksi_tercatat = $pending_transaction['jumlah'];
    $id_pengguna_transaksi = $pending_transaction['id_pengguna'];

    // VALIDASI AMOUNT: Sangat penting untuk keamanan
    // Memastikan jumlah dari Midtrans sama dengan yang tercatat di database saat membuat transaksi pending
    if ($jumlah_transaksi_tercatat != $gross_amount) {
        file_put_contents('midtrans_notif.log', "Order ID: " . $order_id . " - FRAUD ALERT: Jumlah transaksi tidak cocok! Midtrans: " . $gross_amount . ", Tercatat: " . $jumlah_transaksi_tercatat . "\n", FILE_APPEND);
        http_response_code(400); // Bad request atau bisa juga 403 Forbidden
        $pdo->rollBack();
        exit();
    }

    $new_status = 'pending'; // Default status

    if ($transaction == 'capture') {
        // Untuk transaksi kartu kredit atau Gopay yang berhasil dan sudah diotorisasi
        if ($fraud == 'challenge') {
            $new_status = 'challenge';
        } else if ($fraud == 'accept') {
            $new_status = 'success'; // Pembayaran sukses
        }
    } else if ($transaction == 'settlement') {
        // Untuk transaksi non-kartu kredit (VA, QRIS, dll) yang sudah dibayar
        $new_status = 'success'; // Pembayaran sukses
    } else if ($transaction == 'pending') {
        $new_status = 'pending';
    } else if ($transaction == 'deny') {
        $new_status = 'failed';
    } else if ($transaction == 'expire') {
        $new_status = 'expired';
    } else if ($transaction == 'cancel') {
        $new_status = 'cancelled';
    } else {
        file_put_contents('midtrans_notif.log', "Order ID: " . $order_id . " - Warning: Status transaksi tidak dikenal: " . $transaction . "\n", FILE_APPEND);
        http_response_code(400);
        $pdo->rollBack();
        exit();
    }

    // Hanya update saldo tabungan jika statusnya "success" dan belum pernah diupdate
    if ($new_status == 'success' && $pending_transaction['status_pembayaran'] != 'success') {
        // Ambil saldo tabungan saat ini
        $stmt_get_tabungan = $pdo->prepare("SELECT jumlah_tabungan FROM tabungan WHERE id_tabungan = ?");
        $stmt_get_tabungan->execute([$id_tabungan]);
        $current_tabungan = $stmt_get_tabungan->fetch(PDO::FETCH_ASSOC);

        if ($current_tabungan) {
            // Perbarui saldo tabungan
            $updated_balance = $current_tabungan['jumlah_tabungan'] + $jumlah_transaksi_tercatat;
            $stmt_update_tabungan = $pdo->prepare("UPDATE tabungan SET jumlah_tabungan = ? WHERE id_tabungan = ?");
            $stmt_update_tabungan->execute([$updated_balance, $id_tabungan]);
            file_put_contents('midtrans_notif.log', "Order ID: " . $order_id . " - Saldo tabungan diperbarui. Saldo baru: " . $updated_balance . "\n", FILE_APPEND);
        } else {
            file_put_contents('midtrans_notif.log', "Order ID: " . $order_id . " - WARNING: Data tabungan tidak ditemukan untuk id_tabungan: " . $id_tabungan . ". Saldo tidak diperbarui.\n", FILE_APPEND);
        }
    }

    // Perbarui status di tabel transaksi
    $stmt_update_trans = $pdo->prepare("UPDATE transaksi SET status_pembayaran = ?, tanggal = NOW() WHERE order_id = ?");
    $stmt_update_trans->execute([$new_status, $order_id]);

    $pdo->commit(); // Commit transaksi database
    http_response_code(200); // Kirim 200 OK ke Midtrans sebagai konfirmasi
    file_put_contents('midtrans_notif.log', date('Y-m-d H:i:s') . " - Order ID: " . $order_id . " - Status updated to: " . $new_status . ". Response 200 OK.\n", FILE_APPEND);

} catch (Exception $e) {
    $pdo->rollBack(); // Rollback jika ada kesalahan
    error_log("Notification Handler Database Error for Order ID " . ($order_id ?? 'N/A') . ": " . $e->getMessage());
    file_put_contents('midtrans_notif.log', date('Y-m-d H:i:s') . " - Order ID: " . ($order_id ?? 'N/A') . " - General Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500); // Internal server error
}
?>