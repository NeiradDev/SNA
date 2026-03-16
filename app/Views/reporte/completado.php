<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>

  <link rel="stylesheet" href="<?= base_url('assets/css/reporte/completado.css') ?>">

<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="panel-resumen">

        <div class="contador">
        <div class="numero"><?= count($usuarios) ?></div>
        <div class="label">usuarios pendientes</div>
        </div>

    <div class="acciones-globales">

        <button id="btnRecordarTodos">
        Recordar a todos por WhatsApp
        </button>

        <button id="btnCopiarTelefonos">
        Copiar teléfonos
        </button>

        <button id="btnExportarExcel">
        Exportar Excel
        </button>

    </div>

</div>

<?php if (!empty($usuarios)) : ?>

<div class="tabla-contenedor">
<input
id="buscadorUsuarios"
class="buscador"
placeholder="Buscar usuario..."
>
<table id="tablaPendientes">

    <thead>
    <tr>
    <th>Nombres</th>
    <th>Apellidos</th>
    <th>Teléfono</th>
    <th>Acción</th>
    </tr>
    </thead>

    <tbody>

    <?php foreach ($usuarios as $u) : ?>

        <tr>

        <td><?= esc($u->nombres) ?></td>
        <td><?= esc($u->apellidos) ?></td>

        <td class="telefono">
        <?= esc($u->telefono) ?>
        </td>

        <td>

        <button
        class="btn-wsp"
        data-nombre="<?= esc($u->nombres) ?>"
        data-telefono="<?= esc($u->telefono) ?>"
        >

        WhatsApp

        </button>

        </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>

</div>

<?php else : ?>

<div class="alert-ok">
No hay usuarios pendientes esta semana.
</div>

<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/reporte/completado.js') ?>"></script>
<?= $this->endSection() ?>