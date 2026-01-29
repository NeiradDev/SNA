<?php
$success = session()->getFlashdata('success') ?? '';
if (!$success) return;
?>
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">
                    <i class="bi bi-check-circle me-2"></i>Registro exitoso
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0"><?= esc($success) ?></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-dark px-4" data-bs-dismiss="modal">Perfecto</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('successModal');
  if (el && window.bootstrap) new bootstrap.Modal(el).show();
});
</script>