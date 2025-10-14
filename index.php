<?php
// Set default timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Start session (to check login status)
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$userEmail  = $isLoggedIn ? $_SESSION['email'] : '';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <title>e-Kursus ALPHA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .carousel-caption { background: rgba(0, 0, 0, 0.5); padding: 30px; border-radius: 10px; }
    .btn-kursus { font-size: 1.2rem; padding: 15px 30px; border-radius: 8px; font-weight: bold; }
    .section-title { font-weight: bold; font-size: 1.5rem; color: #0C3C60; }
    .info-card { background: white; border-left: 6px solid #0C3C60; border-radius: 5px; padding: 25px;
                 box-shadow: 0 4px 8px rgba(0,0,0,0.05); margin-bottom: 30px; transition: transform 0.3s ease; }
    .info-card:hover { transform: translateY(-5px); }
    footer { background: #0C3C60; color: white; text-align: center; padding: 20px; margin-top: 40px; }
    .nav-action-btn { margin-left: 10px; padding: 8px 15px; border-radius: 6px; font-weight: 500; transition: all 0.3s ease; display: inline-flex; align-items: center; text-decoration:none; }
    .nav-action-btn i { margin-right: 8px; }
    .login-btn { background-color: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3); }
    .login-btn:hover { background-color: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }
    .register-btn { background-color: #ffc107; color: #212529; border: 1px solid #ffc107; }
    .register-btn:hover { background-color: #e0a800; transform: translateY(-2px); }
    .profile-btn { background-color: #17a2b8; color: white; border: 1px solid #17a2b8; }
    .profile-btn:hover { background-color: #138496; transform: translateY(-2px); }
    .datetime-display { background-color: rgba(0, 0, 0, 0.1); padding: 8px 15px; border-radius: 20px; font-size: 0.9rem; display: inline-flex; align-items: center; margin-left: 15px; }
    .datetime-display i { margin-right: 8px; }
    /* Hubungi Kami Section */
    .hubungi-section { background-color: #f0f8ff; padding: 50px 0; margin-top: 40px; }
    .hubungi-title { color: #0C3C60; font-weight: bold; margin-bottom: 30px; text-align: center; }
    .hubungi-content { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-bottom: 30px; }
    .hubungi-content h4 { color: #0C3C60; margin-top: 20px; margin-bottom: 10px; }
    .hubungi-content h4:first-child { margin-top: 0; }
    .hubungi-map { border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .hubungi-map iframe { width: 100%; height: 450px; border: none; }
    .social-media-links { margin-top: 15px; }
    .contact-icon { color: #0C3C60; margin-right: 10px; width: 20px; text-align: center; }
    .user-email { color: white; margin-right: 10px; font-weight: 500; }
    /* Office hours table */
    .hours-table { width: fit-content; margin-left: 25px; }
    .hours-table td { padding: 6px 14px; border-bottom: 1px solid #e5e5e5; }
    .hours-table tr:last-child td { border-bottom: 0; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="assets/img/logo_apm.jpeg" width="50" height="50" class="d-inline-block align-text-top me-2" alt="Logo APM">
      <span>e-Kursus ALPHA</span>
    </a>
    <div class="d-flex align-items-center ms-auto">
      <div class="datetime-display text-white">
        <i class="fas fa-calendar-alt"></i>
        <span id="datetime"><?php echo date('l, j F Y, h:i:s A'); ?></span>
      </div>

      <?php if ($isLoggedIn): ?>
        <span class="user-email">
          <i class="fas fa-user"></i> <?php echo htmlspecialchars($userEmail); ?>
        </span>
        <a href="dashboard.php" class="nav-action-btn profile-btn">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="logout.php" class="nav-action-btn login-btn">
          <i class="fas fa-sign-out-alt"></i> Log Keluar
        </a>
      <?php else: ?>
        <a href="login.php" class="nav-action-btn login-btn">
          <i class="fas fa-sign-in-alt"></i> Log Masuk
        </a>
        <a href="register.php" class="nav-action-btn register-btn">
          <i class="fas fa-user-plus"></i> Daftar Akaun
        </a>
        <a href="kemaskini.php" class="nav-action-btn profile-btn">
        <i class="fas fa-user-edit"></i> Kemaskini Profil
      </a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- CAROUSEL -->
<div id="carouselExample" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img src="assets/img/slide1.jpg" class="d-block w-100" alt="Slide 1" style="height: 70vh; object-fit: cover;">
      <div class="carousel-caption d-none d-md-block">
        <h1 class="text-white fw-bold">e-Kursus ALPHA</h1>
        <p class="text-white">Platform atas talian untuk permohonan kursus-kursus latihan Angkatan Pertahanan Awam di Akademi Latihan Pertahanan Awam (ALPHA)</p>
        <a href="permohonan.php" class="btn btn-info text-dark btn-kursus shadow">
          <i class="fas fa-book"></i> MOHON KURSUS
        </a>
      </div>
    </div>
    <div class="carousel-item">
      <img src="assets/img/slide2.jpeg" class="d-block w-100" alt="Slide 2" style="height: 70vh; object-fit: cover;">
      <div class="carousel-caption d-none d-md-block">
        <h1 class="text-white fw-bold">Latihan Berkualiti</h1>
        <p class="text-white">Program latihan terkini untuk memenuhi keperluan angkatan pertahanan awam</p>
        <a href="kursus.php" class="btn btn-warning text-dark btn-kursus shadow">
          <i class="fas fa-graduation-cap"></i> LIHAT KURSUS
        </a>
      </div>
    </div>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
  </button>
</div>

<!-- CONTENT SECTION -->
<div class="container my-5">
  <div class="row">
    <div class="col-md-6">
      <div class="info-card">
        <h4 class="section-title"><i class="fas fa-bullseye me-2"></i>OBJEKTIF</h4>
        <p>Menyediakan program, latihan dan kursus dalam bidang pertahanan awam bagi meningkatkan kefahaman, kecekapan dan kemahiran personel angkatan serta masyarakat yang berdaya tahan terhadap bencana. </div>
      <div class="info-card">
        <h4 class="section-title"><i class="fas fa-eye me-2"></i>VISI</h4>
        <p>Menjadikan sebuah institusi latihan pertahanan awam yang mantap dan unggul dalam menyediakan anggota angkatan serta masyarakat yang berdaya tahan terhadap bencana
      </div>
    </div>
    <div class="col-md-6">
      <div class="info-card">
        <h4 class="section-title"><i class="fas fa-rocket me-2"></i>MISI</h4>
        <p>Ke arah institusi kecemerlangan ilmu pertahanan awam di peringkat negara dan antarabangsa.</p>
      </div>
      <div class="info-card">
        <h4 class="section-title"><i class="fas fa-quote-left me-2"></i>MOTO</h4>
        <p>ALPHA memacu kecemerlangan.</p>
      </div>
    </div>
  </div>
</div>

<!-- HUBUNGI KAMI SECTION -->
<section id="hubungi" class="hubungi-section">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <div class="hubungi-title">
          <h2><i class="fas fa-envelope me-2"></i>Hubungi ALPHA</h2>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-6">
        <div class="hubungi-content">
          <h4><i class="fas fa-info-circle me-2"></i>Maklumat ALPHA</h4>
          <p class="mb-4">Akademi Latihan Pertahanan Awam (ALPHA)</p>

          <h4><i class="fas fa-map-marker-alt me-2"></i>Alamat:</h4>
          <p>
            <i class="fas fa-building contact-icon"></i> Lot 14617, Persiaran Institusi Bangi,<br>
            <i class="fas fa-map-pin contact-icon"></i> Kawasan Institusi Bangi,<br>
            <i class="fas fa-city contact-icon"></i> 43000 Kajang, Selangor, Malaysia
          </p>

          <h4><i class="fas fa-phone me-2"></i>Telefon:</h4>
          <p>
            <i class="fas fa-phone-alt contact-icon"></i> <a href="tel:+60389262991" class="text-decoration-none">03-8926 2991</a><br>
            <i class="fas fa-phone-alt contact-icon"></i> <a href="tel:+60389262993" class="text-decoration-none">03-8926 2993</a>
          </p>

          <h4><i class="fas fa-envelope me-2"></i>Email:</h4>
          <p>
            <i class="fas fa-at contact-icon"></i> <a href="mailto:proalpha@civildefence.gov.my" class="text-decoration-none">proalpha@civildefence.gov.my</a><br>
            <i class="fas fa-at contact-icon"></i> <a href="mailto:alpha@apm.gov.my" class="text-decoration-none">alpha@apm.gov.my</a>
          </p>

          <h4><i class="fas fa-globe me-2"></i>Laman Web:</h4>
          <p>
            <i class="fas fa-link contact-icon"></i> <a href="https://www.civildefence.gov.my/utama" target="_blank" rel="noopener" class="text-decoration-none">civildefence.gov.my/alpha</a>
          </p>

          <!-- FIXED: Proper office hours block -->
          <h4><i class="fas fa-clock me-2"></i>Waktu Pejabat:</h4>
          <p style="margin-left:25px; font-weight:600;">
            <i class="fas fa-door-open contact-icon"></i> Masa Rehat (1.00 - 2.00 tengah hari)
          </p>

          <table class="hours-table table table-sm align-middle">
            <tbody>
              <tr><td>Isnin</td><td>8.00 pagi – 5.30 petang</td></tr>
              <tr><td>Selasa</td><td>8.00 pagi – 5.30 petang</td></tr>
              <tr><td>Rabu</td><td>8.00 pagi – 5.30 petang</td></tr>
              <tr><td>Khamis</td><td>8.00 pagi – 5.30 petang</td></tr>
              <tr><td>Jumaat</td><td>8.00 pagi – 5.30 petang</td></tr>
              <tr><td>Sabtu</td><td class="text-danger">Tutup</td></tr>
              <tr><td>Ahad</td><td class="text-danger">Tutup</td></tr>
            </tbody>
          </table>
          <!-- END hours block -->
        </div>
      </div>

      <div class="col-md-6">
        <div class="hubungi-map mb-4">
          <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3984.4596796510946!2d101.73673627622539!3d2.9698670542259524!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cdcba05b38c20b%3A0xf28bdd19554f5bfa!2sALPHA!5e0!3m2!1sms!2smy!4v1730265611945!5m2!1sms!2smy" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>

        <div class="hubungi-content">
          <h4><i class="fas fa-hashtag me-2"></i>Media Sosial:</h4>
          <div class="social-media-links">
            <a href="https://www.instagram.com/alpha_bangi?igshid=YmMyMTA2M2Y=" target="_blank" rel="noopener" class="btn btn-outline-primary mb-2 me-2">
              <i class="fab fa-instagram me-2"></i>Instagram
            </a>
            <a href="https://twitter.com/JPAM_ALPHA?t=Q3aStyQjvhkmfdOXLSVpIA&s=09" target="_blank" rel="noopener" class="btn btn-outline-info mb-2 me-2">
              <i class="fab fa-twitter me-2"></i>Twitter
            </a>
            <a href="https://m.youtube.com/channel/UCXz37Po1IxdxnthcTg17Pag?noapp=1" target="_blank" rel="noopener" class="btn btn-outline-danger mb-2">
              <i class="fab fa-youtube me-2"></i>YouTube
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <p>&copy; <?php echo date("Y"); ?> e-Kursus ALPHA | Hak Cipta Terpelihara</p>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Update datetime display every second (Malay locale)
  function updateDateTime() {
    const now = new Date();
    const options = {
      weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
      hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true
    };
    document.getElementById('datetime').textContent =
      now.toLocaleString('ms-MY', options);
  }
  updateDateTime();              // run once on load
  setInterval(updateDateTime, 1000); // then keep updating
</script>
</body>
</html>
