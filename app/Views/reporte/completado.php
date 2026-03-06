<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<h2 >Usuarios pendientes (semana actual)</h2>

<?php if (!empty($usuarios)) : ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>Nombres</th>
                    <th>Apellidos</th>
                    <th>Teléfono</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $i => $u) : ?>
                    <tr>
                        <td><?= esc($u->nombres) ?></td>
                        <td><?= esc($u->apellidos) ?></td>
                        <td><?= esc($u->telefono) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="5">
                        Total pendientes: <?= count($usuarios) ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
<?php else : ?>
    <div class="alert alert-success" role="alert">
        No hay usuarios pendientes esta semana.
    </div>
<?php endif; ?>

<?= $this->endSection() ?>