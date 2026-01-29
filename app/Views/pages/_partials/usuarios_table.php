<?php helper('ui'); ?>
<div class="table-responsive">
  <table id="usersTable" class="table table-hover align-middle mb-0 w-100">
    <thead class="bg-light">
      <tr>
        <th class="px-4 py-3 border-0 text-muted small fw-bold text-uppercase d-none no-export">ID</th>
        <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Nombre Completo</th>
        <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Documento</th>
        <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Cargo</th>
        <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Área</th>
        <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Supervisor</th>
        <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Agencia</th>
        <th class="py-3 border-0 text-muted small fw-bold text-uppercase">Estado</th>
        <th class="px-4 py-3 border-0 text-muted small fw-bold text-uppercase text-end no-export">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($usuarios)): ?>
        <?php foreach ($usuarios as $user): ?>
          <tr>
            <td class="px-4 d-none"><?= esc($user['id_user']) ?></td>
            <td><div class="fw-bold text-dark"><?= esc(($user['nombres']??'').' '.($user['apellidos']??'')) ?></div></td>
            <td><?= esc($user['cedula'] ?? '') ?></td>
            <td><span class="badge bg-light text-dark border fw-normal"><?= esc($user['nombre_cargo'] ?? $user['id_cargo'] ?? '—') ?></span></td>
            <td><?= esc($user['nombre_area'] ?? '—') ?></td>
            <td><?= esc($user['supervisor_nombre'] ?? '—') ?></td>
            <td><?= esc($user['nombre_agencia'] ?? '—') ?></td>
            <td><?= badge_estado($user['activo'] ?? 0) ?></td>
            <td class="px-4 text-end"><?= btn_outline_edit(base_url('usuarios/editar/'.($user['id_user']??0))) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="9" class="text-center py-5">
            <i class="bi bi-people text-muted display-4"></i>
            <p class="mt-3 text-muted">No hay usuarios registrados actualmente.</p>
            <a href="<?= base_url('usuarios/nuevo') ?>" class="btn btn-sm btn-dark">Registrar el primero</a>
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>