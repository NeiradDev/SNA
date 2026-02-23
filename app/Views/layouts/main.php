<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SNA BESTPC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= base_url('assets/css/style2.css') ?>">
    
    <?= $this->renderSection('styles') ?>
</head>
<body>

    <div id="sidebar-container">
        <?= view('layouts/partials/sidebar2', ['menuAllowed' => $menuAllowed ?? []]) ?>
    </div>

    <main id="main">
        <?= $this->renderSection('contenido') ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('assets/js/script.js') ?>"></script>
    
    <?= $this->renderSection('scripts') ?>
</body>
</html>