<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SNA BESTPC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Estilos Globales -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style2.css') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <!-- Sección para estilos específicos de cada vista -->
    <?= $this->renderSection('styles') ?>
</head>
<body>
    <!-- Punto de inserción para el contenido principal de tus vistas -->
    <div>
    <?= view('layouts/partials/sidebar2') ?>
    </div>
    <main id="main">
      <?= $this->renderSection('contenido') ?>
    </main>
    <script src="<?= base_url('assets/js/script.js') ?>"></script>
  <?= $this->renderSection('scripts') ?>
</body>
</html>