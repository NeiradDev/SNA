<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<div class="container py-4">
    <h3 class="mb-3">Mi Perfil</h3>

    <div class="card shadow p-3">
        <p class="mb-2"><b>Nombre:</b> <?= esc(trim(($nombres ?? '') . ' ' . ($apellidos ?? ''))) ?></p>
        <p class="mb-2"><b>Cédula:</b> <?= esc($cedula ?? '') ?></p>
        <p class="mb-2"><b>Cargo:</b> <?= esc($cargo_nombre ?? '') ?></p>
        <p class="mb-2"><b>Nivel:</b> <?= esc($nivel ?? '') ?></p>
        <p class="mb-2"><b>Área:</b> <?= esc((string)($id_area ?? '')) ?></p>
        <p class="mb-0"><b>Agencia:</b> <?= esc((string)($id_agencias ?? '')) ?></p>

        <hr>
    </div>
</div>

<?= $this->endSection() ?>
