<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>

<h2>Usuarios pendientes</h2>

<?php if (!empty($usuarios)) : ?>
    <ul>
        <?php foreach ($usuarios as $u) : ?>
            <li><?= esc($u->nombres) ?> <?= esc($u->apellidos) ?></li>
        <?php endforeach; ?>
    </ul>
<?php else : ?>
    <p>No hay usuarios pendientes esta semana.</p>
<?php endif; ?>

<?= $this->endSection() ?>