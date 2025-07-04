<?php
// navbar.php

// Pastikan session sudah dimulai di file utama (misal: dashboard.php) sebelum meng-include navbar.php
// Namun, sebagai safety measure, bisa ditambahkan di sini juga


// Set zona waktu PHP ke Asia/Jakarta (WIB)
// Ini penting agar fungsi waktu PHP, jika digunakan, konsisten.
// Sebaiknya ini diletakkan di db.php atau file konfigurasi global.
// date_default_timezone_set('Asia/Jakarta'); // Anda bisa uncomment ini jika perlu

$waktuSholat = [
    'Subuh' => '04:50', // Pastikan waktu ini sudah disesuaikan dengan Pekanbaru (WIB)
    'Dzuhur' => '12:15',
    'Ashar' => '15:30',
    'Maghrib' => '18:20',
    'Isya' => '19:45'
];

// Convert PHP array to JSON for JavaScript
$waktuSholatJson = json_encode($waktuSholat);
?>
<nav class="main-header navbar navbar-expand navbar-dark navbar-custom">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
    </ul>

    <div class="prayer-time-container" style="flex-grow: 1; overflow: hidden; margin-left: 15px;">
        <div id="prayer-time-display" class="prayer-time-text">
            <i class="fas fa-mosque"></i> Memuat waktu sholat...
        </div>
    </div>

    <ul class="navbar-nav ml-auto">
        <li class="nav-item">
            <div class="realtime-clock-display">
                <i class="far fa-clock"></i> <span id="realtime-clock"></span>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>
    </ul>
</nav>

<script>
// Data waktu sholat dari PHP ke JavaScript
const waktuSholat = <?php echo $waktuSholatJson; ?>;

// Fungsi untuk update jam realtime
function updateClock() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    document.getElementById('realtime-clock').textContent = `${hours}:${minutes}:${seconds}`;
}

// Fungsi untuk update waktu sholat terdekat
function updateNearestPrayer() {
    const now = new Date();
    const currentHours = now.getHours();
    const currentMinutes = now.getMinutes();
    const currentTimeInMinutes = currentHours * 60 + currentMinutes;
    
    let nearestPrayer = '';
    let nearestTime = '';
    let smallestPositiveDiff = Infinity; // Waktu terdekat di masa depan
    let lastPrayerPassed = ''; // Waktu sholat terakhir yang terlewat (untuk kasus semua sholat sudah lewat hari ini)

    const prayerTimesArray = Object.entries(waktuSholat).map(([prayer, time]) => {
        const [h, m] = time.split(':').map(Number);
        return { prayer, time, minutes: h * 60 + m };
    });

    // Urutkan waktu sholat untuk penanganan yang lebih mudah
    prayerTimesArray.sort((a, b) => a.minutes - b.minutes);

    for (let i = 0; i < prayerTimesArray.length; i++) {
        const { prayer, time, minutes } = prayerTimesArray[i];
        
        let diff = minutes - currentTimeInMinutes;
        
        if (diff > 0) { // Jika waktu sholat masih di masa depan
            if (diff < smallestPositiveDiff) {
                smallestPositiveDiff = diff;
                nearestPrayer = prayer;
                nearestTime = time;
            }
        } else { // Jika waktu sholat sudah lewat
            lastPrayerPassed = prayer; // Catat sholat terakhir yang terlewat
        }
    }

    let displayText;
    if (nearestPrayer === '') {
        // Jika semua waktu sholat sudah lewat hari ini, maka sholat terdekat adalah Subuh besok
        const subuhBesok = prayerTimesArray.find(p => p.prayer === 'Subuh');
        if (subuhBesok) {
            let diffToSubuhTomorrow = (1440 - currentTimeInMinutes) + subuhBesok.minutes;
            const hoursLeft = Math.floor(diffToSubuhTomorrow / 60);
            const minutesLeft = diffToSubuhTomorrow % 60;
            displayText = `Waktu Subuh Pukul ${subuhBesok.time} (${hoursLeft} jam ${minutesLeft} menit lagi)`;
        } else {
             displayText = `Tidak ada waktu sholat terdaftar.`; // Fallback jika Subuh tidak ada
        }
       
    } else {
        const hoursLeft = Math.floor(smallestPositiveDiff / 60);
        const minutesLeft = smallestPositiveDiff % 60;

        if (smallestPositiveDiff === 0) { // Tepat waktu sholat
             displayText = `Waktu ${nearestPrayer} sudah tiba.`;
        } else if (smallestPositiveDiff < 60) { // Kurang dari 1 jam lagi
            displayText = `Waktu ${nearestPrayer} Pukul ${nearestTime} (${minutesLeft} menit lagi)`;
        } else {
            displayText = `Waktu ${nearestPrayer} Pukul ${nearestTime} (${hoursLeft} jam ${minutesLeft} menit lagi)`;
        }
    }

    const prayerDisplay = document.getElementById('prayer-time-display');
    prayerDisplay.innerHTML = `<i class="fas fa-mosque"></i> ${displayText}`;
    
    // Pastikan tidak ada animasi CSS yang aktif dari sebelumnya jika ada
    prayerDisplay.style.animation = 'none';
    prayerDisplay.style.transform = 'translateX(0)';
}


// Jalankan fungsi pertama kali saat DOM siap
document.addEventListener('DOMContentLoaded', function() {
    updateClock(); // Set jam segera
    updateNearestPrayer(); // Set waktu sholat segera

    // Update setiap detik
    setInterval(updateClock, 1000);
    // Update waktu sholat setiap menit (pada detik ke-0)
    // Cukup panggil setiap menit, tidak perlu cek detik di dalam updateClock
    setInterval(updateNearestPrayer, 60 * 1000); // 60.000 milidetik = 1 menit
});
</script>