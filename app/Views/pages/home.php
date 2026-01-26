<?= $this->extend('layouts/main') ?>

<?= $this->section('titulo') ?>
    Bestpc SNA
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>

<div class="container-fluid">
    <div class="row flex-nowrap">
        
        <?= view('layouts/partials/sidebar') ?>

        <main class="col py-3 overflow-auto" role="main">
            <div class="container-fluid">
                <h3>Bienvenido al Sistema</h3>
                <p>CONTENIDO</p>
            </div>
        </main>

    </div>
</div>

<?= $this->endSection() ?>