<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SNA BESTPC</title>
    <title>SNA BESTPC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= base_url('assets/css/style2.css') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <?= $this->renderSection('styles') ?>
</head>
<body>
<body>

    <div>
       <?= view('layouts/partials/sidebar', [
    'menuAllowed' => $menuAllowed ?? []
]) ?>
    </div>

    <main id="main">
      <?= $this->renderSection('contenido') ?>
    </main>

    <script src="<?= base_url('assets/js/script.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
