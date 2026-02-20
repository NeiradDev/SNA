<?php
/**
 * Partial: pages/_partials/usuarios_table.php
 *
 * ✅ Objetivo:
 * - Renderizar la tabla de usuarios para DataTables
 * - Ahora incluye: correo y teléfono
 *
 * Variables esperadas:
 * - $usuarios: array de usuarios (cada row debe traer: correo, telefono)
 */
?>

<div class="table-responsive">
  <table id="usersTable" class="table table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th style="width:70px;">ID</th>
        <th>Nombre</th>
        <th>Documento</th>
        <th>Correo</th>
        <th>Teléfono</th>
        <th>Agencia</th>
        <th>División</th>
        <th>Área</th>
        <th>Cargo</th>
        <th>Supervisor</th>
        <th style="width:90px;">Estado</th>
        <th style="width:120px;">Acciones</th>
      </tr>
    </thead>

    <tbody>
      <?php foreach (($usuarios ?? []) as $u): ?>
        <?php
          // -----------------------------
          // Normalización de valores
          // -----------------------------
          $id        = (int)($u['id_user'] ?? 0);
          $fullName  = trim((string)($u['nombres'] ?? '') . ' ' . (string)($u['apellidos'] ?? ''));
          $doc       = (string)($u['cedula'] ?? '');

          // ✅ Nuevos
          $email     = trim((string)($u['correo'] ?? ''));
          $phone     = trim((string)($u['telefono'] ?? ''));

          $agency    = (string)($u['nombre_agencia'] ?? '');
          $division  = (string)($u['nombre_division'] ?? '');
          $area      = (string)($u['nombre_area'] ?? '');
          $cargo     = (string)($u['nombre_cargo'] ?? '');
          $sup       = (string)($u['supervisor_nombre'] ?? '');

          $activeInt = (int)($u['activo'] ?? 0);
          $isActive  = $activeInt === 1;
        ?>

        <tr>
          <td class="text-muted fw-semibold"><?= esc((string)$id) ?></td>

          <td>
            <div class="fw-semibold"><?= esc($fullName) ?></div>
          </td>

          <td>
            <span class="text-muted"><?= esc($doc) ?></span>
          </td>

          <!-- ✅ CORREO -->
          <td>
            <?php if ($email !== ''): ?>
              <a href="mailto:<?= esc($email) ?>" class="text-decoration-none">
                <?= esc($email) ?>
              </a>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>

          <!-- ✅ TELÉFONO -->
          <td>
            <?php if ($phone !== ''): ?>
              <span><?= esc($phone) ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>

          <td><?= esc($agency) ?></td>
          <td><?= esc($division) ?></td>
          <td><?= esc($area) ?></td>
          <td><?= esc($cargo) ?></td>

          <td>
            <?= $sup !== '' ? esc($sup) : '<span class="text-muted">—</span>' ?>
          </td>

          <td>
            <?php if ($isActive): ?>
              <span class="badge bg-success-subtle text-success border border-success-subtle">Activo</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactivo</span>
            <?php endif; ?>
          </td>

          <td>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-dark"
                 href="<?= base_url('usuarios/editar/' . $id) ?>">
                <i class="bi bi-pencil-square me-1"></i>Editar
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
