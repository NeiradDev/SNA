<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Sistema</title>

    <!-- 🔸 IMPORTANTE: hace que Bootstrap sea realmente responsivo en móvil -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">

    <?= $this->renderSection('styles') ?>
</head>
<body class="bg-light">

<div class="container-fluid">
    <div class="row flex-md-nowrap">
        <?= view('layouts/partials/sidebar') ?>

        <main class="col py-3 px-md-4">
            <!-- Botón hamburguesa: visible solo en móvil -->
            <div class="d-md-none mb-3">
                <button class="btn btn-dark" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#snaOffcanvas" aria-controls="snaOffcanvas">
                    <i class="bi bi-list"></i> Menú
                </button>
            </div>

            <?= $this->renderSection('contenido') ?>
        </main>
    </div>

    <?= $this->renderSection('scripts') ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>