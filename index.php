<?php
session_start(); // Selalu panggil session_start() di awal file PHP yang menggunakan session

// Pastikan db.php hanya dimuat sekali jika file ini dipanggil langsung via browser
// Jika file ini dipanggil via AJAX, require_once akan menangani bahwa db.php hanya termuat sekali
require_once "db.php";

// Bagian PHP: Menangani permintaan AJAX POST untuk Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); // Memberi tahu browser bahwa respons adalah JSON
    
    // Inisialisasi respons default
    $response = [
        'status' => 'error',
        'message' => 'Terjadi kesalahan tidak terduga.'
    ];

    try {
        // Objek $pdo sudah tersedia dari db.php karena sudah di-require_once di atas.
        // Tidak perlu membuat koneksi PDO lagi di sini.

        $username_email = $_POST['username_email'] ?? '';
        $password_input = $_POST['password'] ?? '';

        // Validasi input dasar
        if (empty($username_email) || empty($password_input)) {
            $response['message'] = 'Mohon lengkapi username/email dan password.';
            echo json_encode($response);
            exit();
        }

        // Gunakan prepared statement untuk mencegah SQL Injection
        // Asumsi: 'email' adalah kolom yang digunakan untuk login dan bersifat unik
        $stmt = $pdo->prepare("SELECT id_pengguna, email, password, role, nama FROM pengguna WHERE email = ?");
        
        // Bind parameter dan eksekusi statement
        $stmt->execute([$username_email]);
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC); // Ambil satu baris hasil

        if ($user) { // Jika pengguna ditemukan
            // --- VERIFIKASI PASSWORD MENGGUNAKAN password_verify() (DIREKOMENDASIKAN) ---
            // Pastikan password di kolom 'password' pada tabel 'pengguna' sudah tersimpan sebagai hash dari password_hash().
            if (password_verify($password_input, $user['password'])) {
                // Autentikasi berhasil
                
                // AKTIFKAN SESI DI SINI
                $_SESSION['user_id'] = $user['id_pengguna'];
                $_SESSION['user_name'] = $user['nama'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                
                $response = [
                    'status' => 'success',
                    'message' => 'Login berhasil! Selamat datang, ' . htmlspecialchars($user['nama']) . '.',
                    'user' => [ // Data user bisa dikirim kembali jika diperlukan oleh frontend
                        'id_pengguna' => $user['id_pengguna'],
                        'nama' => htmlspecialchars($user['nama']),
                        'role' => htmlspecialchars($user['role']),
                        'email' => htmlspecialchars($user['email'])
                    ]
                ];
            } else {
                // Password tidak cocok
                $response['message'] = 'Username/Email atau password salah.';
            }
        } else {
            // Pengguna tidak ditemukan
            $response['message'] = 'Username/Email atau password salah.';
        }

    } catch (PDOException $e) {
        // Tangani error koneksi atau query database
        $response['message'] = 'Koneksi database atau query gagal: ' . $e->getMessage();
        error_log("Login PDO Error: " . $e->getMessage()); // Catat error untuk debugging
    }

    echo json_encode($response);
    exit(); // Penting: Hentikan eksekusi setelah mengirim respons JSON
}

// Jika bukan permintaan POST, tampilkan halaman login HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login System - Futuristik</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --bg-color: #1a1a1a; /* Hampir hitam */
            --neon-blue: #00FFFF;
            --neon-blue-light: #00E5E5;
            --text-color: #f0f0f0;
            --form-bg: rgba(255, 255, 255, 0.05); /* Latar belakang form transparan */
            --border-color: rgba(0, 255, 255, 0.3);
            --placeholder-color: rgba(255, 255, 255, 0.6);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            overflow: hidden; /* Penting untuk animasi partikel */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Particles.js Container */
        #particles-js {
            position: absolute;
            width: 100%;
            height: 100%;
            background-color: var(--bg-color);
            z-index: 0;
        }

        /* Main Login Wrapper */
        .login-wrapper {
            position: relative;
            z-index: 1; /* Di atas partikel */
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            background-color: var(--form-bg);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.4), 0 0 60px rgba(0, 255, 255, 0.2); /* Neon glow */
            text-align: center;
            backdrop-filter: blur(5px); /* Efek blur pada latar belakang form */
            -webkit-backdrop-filter: blur(5px);
            transition: all 0.3s ease; /* Transisi halus untuk responsif/hover */
        }

        .login-container:hover {
            box-shadow: 0 0 40px rgba(0, 255, 255, 0.6), 0 0 80px rgba(0, 255, 255, 0.3); /* Glow lebih kuat saat hover */
        }

        .login-header {
            margin-bottom: 30px;
        }

        .logo-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 2px solid var(--neon-blue);
            box-shadow: 0 0 15px var(--neon-blue);
            transition: all 0.3s ease;
        }

        .logo-img:hover {
            transform: scale(1.05);
            box-shadow: 0 0 25px var(--neon-blue-light);
        }

        .login-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--neon-blue);
            margin-bottom: 10px;
            text-shadow: 0 0 8px var(--neon-blue-light);
        }

        .login-header p {
            font-size: 1rem;
            opacity: 0.8;
        }

        /* Form Styling */
        .login-form .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .login-form .input-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--neon-blue);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .login-form input[type="text"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 15px 15px 15px 50px; /* Padding kiri untuk ikon */
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .login-form input[type="text"]::placeholder,
        .login-form input[type="password"]::placeholder {
            color: var(--placeholder-color);
        }

        .login-form input[type="text"]:focus,
        .login-form input[type="password"]:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: 0 0 10px var(--neon-blue);
            background-color: rgba(0, 0, 0, 0.5); /* Sedikit lebih gelap saat fokus */
        }

        /* Password Toggle Button */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--neon-blue);
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: var(--neon-blue-light);
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 15px;
            background-color: var(--neon-blue);
            border: none;
            border-radius: 8px;
            color: var(--bg-color);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.5); /* Neon glow pada tombol */
            transition: all 0.3s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-login:hover {
            background-color: var(--neon-blue-light);
            transform: translateY(-3px); /* Efek sedikit terangkat */
            box-shadow: 0 0 30px rgba(0, 255, 255, 0.7); /* Glow lebih kuat saat hover */
        }

        .btn-login:active {
            transform: translateY(0); /* Kembali normal saat diklik */
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
        }

        /* Footer Links */
        .login-footer {
            margin-top: 30px;
            font-size: 0.95rem;
        }

        .login-footer a {
            color: var(--neon-blue);
            text-decoration: none;
            transition: color 0.3s ease, text-shadow 0.3s ease;
        }

        .login-footer a:hover {
            color: var(--neon-blue-light);
            text-decoration: underline;
            text-shadow: 0 0 5px var(--neon-blue);
        }

        .login-footer .separator {
            color: var(--text-color);
            margin: 0 10px;
            opacity: 0.7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                padding: 30px 20px;
                max-width: 380px;
            }
            .login-header h1 {
                font-size: 1.8rem;
            }
            .login-header p {
                font-size: 0.9rem;
            }
            .login-form input {
                padding: 12px 12px 12px 45px;
            }
            .login-form .input-group .icon {
                left: 10px;
                font-size: 1rem;
            }
            .password-toggle {
                right: 10px;
                font-size: 1rem;
            }
            .btn-login {
                padding: 12px;
                font-size: 1rem;
            }
            .login-footer {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 25px 15px;
                border-radius: 10px;
            }
            .login-header h1 {
                font-size: 1.6rem;
            }
            .logo-img {
                width: 70px;
                height: 70px;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div> 
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <img src="img/hero.jpg" alt="Logo Sistem" class="logo-img">
                <h1>Selamat Datang</h1>
                <p>Masuk untuk mengakses dashboard Anda.</p>
            </div>
            <form id="loginForm" class="login-form">
                <div class="input-group">
                    <i class="fas fa-user icon"></i>
                    <input type="text" id="username_email" name="username_email" placeholder="Username atau Email" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <button type="button" id="togglePassword" class="password-toggle"><i class="fas fa-eye"></i></button>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Konfigurasi Particles.js
        particlesJS('particles-js', {
          "particles": {
            "number": {
              "value": 80, // Jumlah partikel
              "density": {
                "enable": true,
                "value_area": 800
              }
            },
            "color": {
              "value": "#00FFFF" // Warna neon blue untuk partikel
            },
            "shape": {
              "type": "circle", // Bentuk partikel: lingkaran
              "stroke": {
                "width": 0,
                "color": "#000000"
              },
              "polygon": {
                "nb_sides": 5
              }
            },
            "opacity": {
              "value": 0.5, // Opasitas partikel
              "random": true,
              "anim": {
                "enable": false,
                "speed": 1,
                "opacity_min": 0.1,
                "sync": false
              }
            },
            "size": {
              "value": 3, // Ukuran partikel
              "random": true,
              "anim": {
                "enable": false,
                "speed": 40,
                "size_min": 0.1,
                "sync": false
              }
            },
            "line_linked": {
              "enable": true, // Garis penghubung antar partikel
              "distance": 150, // Jarak garis terlihat
              "color": "#00FFFF", // Warna garis neon blue
              "opacity": 0.4,
              "width": 1
            },
            "move": {
              "enable": true,
              "speed": 2, // Kecepatan gerak partikel (lambat)
              "direction": "none",
              "random": false,
              "straight": false,
              "out_mode": "out",
              "bounce": false,
              "attract": {
                "enable": false,
                "rotateX": 600,
                "rotateY": 1200
              }
            }
          },
          "interactivity": {
            "detect_on": "canvas",
            "events": {
              "onhover": {
                "enable": true,
                "mode": "grab" // Efek saat hover: 'grab' (menarik), 'bubble', 'repulse'
              },
              "onclick": {
                "enable": true,
                "mode": "push" // Efek saat klik: 'push' (menambah partikel), 'remove', 'bubble', 'repulse'
              },
              "resize": true
            },
            "modes": {
              "grab": {
                "distance": 140,
                "line_linked": {
                  "opacity": 1
                }
              },
              "bubble": {
                "distance": 400,
                "size": 40,
                "duration": 2,
                "opacity": 8,
                "speed": 3
              },
              "repulse": {
                "distance": 200,
                "duration": 0.4
              },
              "push": {
                "particles_nb": 4
              },
              "remove": {
                "particles_nb": 2
              }
            }
          },
          "retina_detect": true
        });

        // JavaScript untuk Logika Form
        $(document).ready(function() {
            // Toggle password visibility
            $('#togglePassword').on('click', function() {
                const passwordField = $('#password');
                const passwordFieldType = passwordField.attr('type');
                if (passwordFieldType === 'password') {
                    passwordField.attr('type', 'text');
                    $(this).find('i').removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordField.attr('type', 'password');
                    $(this).find('i').removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });

            // Form submission (menggunakan AJAX)
            $('#loginForm').on('submit', function(e) {
                e.preventDefault(); // Mencegah submit form default

                const btn = $(this).find('.btn-login');
                const originalBtnHtml = btn.html(); // Simpan HTML asli tombol

                // Tampilkan loading state
                btn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Memproses...').prop('disabled', true);

                // Ambil data form
                const formData = $(this).serialize(); // Serialize data for AJAX

                // Kirim data ke file PHP ini sendiri menggunakan AJAX
                $.ajax({
                    url: '<?php echo basename(__FILE__); ?>', // Menunjuk ke file login.php ini sendiri
                    type: 'POST',
                    data: formData,
                    dataType: 'json', // Harap respons dalam format JSON
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Login Berhasil!',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 2000
                            }).then(() => {
                                window.location.href = 'dashboard.php'; // Ganti dengan halaman dashboard Anda
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Login Gagal',
                                text: response.message
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error, xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan',
                            text: 'Tidak dapat terhubung ke server. Silakan coba lagi nanti.'
                        });
                    },
                    complete: function() {
                        // Kembalikan tombol ke keadaan semula setelah proses selesai
                        btn.html(originalBtnHtml).prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>