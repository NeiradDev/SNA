<!-- app/Views/layouts/main.php -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SNA BESTPC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- (Opcional) Solo íconos. NO requiere Bootstrap CSS/JS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">

    <?= $this->renderSection('styles') ?>
</head>
<body>

  <!-- Botón hamburguesa (visible en móvil por CSS) -->
  <button class="hamburger" id="btnSidebarToggle"
          aria-controls="site-nav" aria-expanded="false" aria-label="Abrir menú">
    <span aria-hidden="true">☰</span>
    <span class="hamburger-text">Menú</span>
  </button>

  <!-- GRID: Sidebar + Main -->
  <div class="site-wrap">
    <?= view('layouts/partials/sidebar') ?>

    <main id="app-main" role="main">
      <?= $this->renderSection('contenido') ?>
    </main>
  </div>

  <!-- JS vanilla global (hamburguesa móvil) -->
  <script>
  (function () {
    var btn = document.getElementById('btnSidebarToggle');
    var sidebar = document.getElementById('site-nav');
    var OPEN_CLASS = 'sidebar-open';

    function openSidebar() {
      document.documentElement.classList.add(OPEN_CLASS);
      document.body.classList.add(OPEN_CLASS);
      if (btn) btn.setAttribute('aria-expanded', 'true');
    }
    function closeSidebar() {
      document.documentElement.classList.remove(OPEN_CLASS);
      document.body.classList.remove(OPEN_CLASS);
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    if (btn) {
      btn.addEventListener('click', function () {
        var isOpen = document.documentElement.classList.contains(OPEN_CLASS);
        if (isOpen) closeSidebar(); else openSidebar();
      });
    }

    // Cerrar al hacer click fuera (móvil)
    document.addEventListener('click', function (e) {
      var isOpen = document.documentElement.classList.contains(OPEN_CLASS);
      if (!isOpen) return;
      if (sidebar && !sidebar.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
        closeSidebar();
      }
    });

    // Cerrar con Escape
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeSidebar();
    });

    // Auto-cerrar si pasa a desktop
    window.addEventListener('resize', function () {
      if (window.innerWidth > 960) closeSidebar();
    });
  })();
  </script>

  <?= $this->renderSection('scripts') ?>
</body>
</html>