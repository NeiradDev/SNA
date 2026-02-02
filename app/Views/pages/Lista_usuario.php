<?= $this->extend('layouts/main') ?>

<?= $this->section('styles') ?>
<style>
  .table-hover tbody tr:hover { background-color: rgba(0,0,0,.02); cursor: pointer; }
  .dataTables_wrapper { padding: 12px 12px 0 12px; }
  .table-responsive { overflow: visible; }
  .dataTables_wrapper .dt-buttons{ display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; }
  .dataTables_wrapper .dt-buttons .btn{ border-radius: .5rem !important; border: 1px solid rgba(0,0,0,.25) !important; line-height: 1.2 !important; padding: .38rem .75rem !important; box-shadow: none !important; background-clip: padding-box; }
  .dataTables_wrapper .dataTables_filter{ margin-bottom: .5rem; }
  .dataTables_wrapper .dataTables_filter input { border-radius: .5rem; padding: .375rem .75rem; margin-left: .5rem; }
  .dataTables_wrapper .dataTables_paginate .page-link { border-radius: .5rem; margin: 0 .15rem; }
  .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate { padding-bottom: 12px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('contenido') ?>
<div class="container-fluid pt-2">

  <!-- Flash success modal (reusable) -->
  <?= view('layouts/partials/flash_success_modal') ?>

  <!-- Header -->
  <?= view('pages/_partials/page_header', [
      'title'      => 'Gestión de Personal',
      'subtitle'   => 'Listado de usuarios registrados en el sistema',
      'actionUrl'  => base_url('usuarios/nuevo'),
      'actionText' => 'Nuevo Usuario',
      'actionIcon' => 'bi bi-person-plus'
  ]) ?>

  <!-- Card + Tabla -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <?= view('pages/_partials/usuarios_table', ['usuarios' => $usuarios]) ?>
    </div>
  </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
  <?= view('layouts/partials/datatables_assets') ?>
  <script>
    document.addEventListener('DOMContentLoaded', ()=> {
      initDataTable('#usersTable');
    });
  </script>
<?= $this->endSection() ?>