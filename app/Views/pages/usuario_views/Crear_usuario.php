<?= $this->extend('layouts/main') ?>
<?= $this->section('contenido') ?>

<div class="container-fluid">
<?php $errors = session()->getFlashdata('errors'); ?>

<?php if ($errors): ?>
    <!-- Feedback de validación -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>Revisa los datos
                    </h5>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="mb-0">
                        <?php foreach ($errors as $msg): ?>
                            <li><?= esc($msg) ?></li>
                        <?php endforeach ?>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-dark px-4" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('feedbackModal');
            if (el && window.bootstrap) new bootstrap.Modal(el).show();
        });
    </script>
<?php endif ?>

<div class="row">
<div class="col-12 col-lg-10">
<div class="card border-0 shadow-sm pt-2">
<div class="card-body p-4">

<?php
/* ==========================================================
   Helpers de vista
   ========================================================== */
if (!function_exists('html_attrs')) {
    function html_attrs(array $attrs): string {
        $out = [];
        foreach ($attrs as $k => $v) {
            if (is_bool($v)) {
                if ($v) $out[] = $k; // atributo booleano
                continue;
            }
            if ($v === null) continue;
            $out[] = $k . '="' . esc((string)$v, 'attr') . '"';
        }
        return implode(' ', $out);
    }
}
if (!function_exists('input_tag')) {
    function input_tag(string $name, string $type = 'text', array $extra = []): string {
        $base = [
            'type'  => $type,
            'name'  => $name,
            'class' => 'form-control bg-light border-0',
            'value' => old($name) !== null ? old($name) : '',
        ];
        return '<input ' . html_attrs(array_merge($base, $extra)) . '>';
    }
}
?>

<form action="<?= base_url('usuarios/guardar') ?>" method="POST">
    <?= csrf_field() ?>

    <h5 class="mb-4 text-muted">
        <i class="bi bi-person-badge me-2"></i>Información Personal
    </h5>

    <div class="row g-3">
        <!-- Nombres -->
        <div class="col-md-6">
            <label class="form-label fw-bold small">Nombres</label>
            <?= input_tag('nombres', 'text', ['required' => true, 'maxlength' => 32, 'placeholder' => 'Ej. Juan Carlos']) ?>
        </div>

        <!-- Apellidos -->
        <div class="col-md-6">
            <label class="form-label fw-bold small">Apellidos</label>
            <?= input_tag('apellidos', 'text', ['required' => true, 'maxlength' => 32, 'placeholder' => 'Ej. Armas']) ?>
        </div>

        <!-- Documento -->
        <div class="col-md-3">
            <label class="form-label fw-bold small">Tipo de documento</label>
            <select name="doc_type" id="doc_type" class="form-select bg-light border-0" required>
                <option value="CEDULA"   <?= old('doc_type') !== 'PASAPORTE' ? 'selected' : '' ?>>Cédula</option>
                <option value="PASAPORTE" <?= old('doc_type') === 'PASAPORTE' ? 'selected' : '' ?>>Pasaporte/CI/NU</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label fw-bold small">Número de documento</label>
            <?= input_tag('cedula', 'text', ['id' => 'doc_number', 'required' => true, 'placeholder' => 'Ingrese el número']) ?>
            <div class="form-text small text-muted" id="doc_help"></div>
        </div>

        <!-- Contraseña -->
        <div class="col-md-6">
            <label class="form-label fw-bold small">Contraseña</label>
            <input type="password" name="password" class="form-control bg-light border-0" required>
        </div>

        <!-- Asignación -->
        <div class="col-12 my-4">
            <h5 class="text-muted"><i class="bi bi-building me-2"></i>Asignación y Estructura</h5>
            <hr class="opacity-25">
        </div>

        <!-- Agencia -->
        <div class="col-md-3">
            <label class="form-label fw-bold small">Agencia</label>
            <select name="id_agencias" id="id_agencias" class="form-select bg-light border-0" required>
                <option value="">Seleccione…</option>
                <?php foreach ($agencias ?? [] as $a): ?>
                    <option value="<?= esc($a['id_agencias']) ?>" <?= old('id_agencias') == $a['id_agencias'] ? 'selected' : '' ?>>
                        <?= esc($a['nombre_agencia']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <!-- División -->
        <div class="col-md-3">
            <label class="form-label fw-bold small">División</label>
            <select name="id_division" id="id_division" class="form-select bg-light border-0">
                <option value="">Seleccione…</option>
                <?php foreach ($division ?? [] as $d): ?>
                    <option value="<?= esc($d['id_division']) ?>" <?= old('id_division') == $d['id_division'] ? 'selected' : '' ?>>
                        <?= esc($d['nombre_division']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <!-- Área -->
        <div class="col-md-3">
            <label class="form-label fw-bold small">Área</label>
            <select name="id_area" id="id_area" class="form-select bg-light border-0" required>
                <option value="">Seleccione…</option>
                <?php foreach ($areas ?? [] as $ar): ?>
                    <option value="<?= esc($ar['id_area']) ?>" <?= old('id_area') == $ar['id_area'] ? 'selected' : '' ?>>
                        <?= esc($ar['nombre_area']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <!-- Cargo -->
        <div class="col-md-3">
            <label class="form-label fw-bold small">Cargo</label>
            <select name="id_cargo" id="id_cargo" class="form-select bg-light border-0" required>
                <option value="">Seleccione…</option>
            </select>
        </div>

        <!-- Supervisor -->
        <div class="col-md-3">
            <label class="form-label fw-bold small">Supervisor</label>
            <select name="id_supervisor" id="id_supervisor" class="form-select bg-light border-0">
                <option value="0">Sin supervisor</option>
            </select>
        </div>

        <!-- Estado y acciones -->
        <div class="col-12 mt-5 d-flex justify-content-between align-items-center">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= old('activo') === null || old('activo') ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Usuario activo</label>
            </div>
            <div>
                <a href="<?= base_url('usuarios') ?>" class="btn btn-light px-4 me-2">Cancelar</a>
                <button type="submit" class="btn btn-dark px-5 shadow-sm">Registrar Usuario</button>
            </div>
        </div>
    </div>
</form>

</div>
</div>
</div>

<!-- Ayuda -->
<div class="col-md-2">
    <div class="card border-0 bg-dark text-white shadow-sm mb-3">
        <div class="card-body">
            <h6 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Ayuda</h6>
            <p class="small opacity-75">
                Verifique que el documento no exista. Los campos estructurales son obligatorios para reportes.
            </p>
        </div>
    </div>
</div>

</div> <!-- .row -->
</div> <!-- .container-fluid -->

<script>
(() => {
    // Helpers DOM
    const byId = (id) => document.getElementById(id);

    /* Documento: reglas de formato */
    const docType   = byId('doc_type');
    const docNumber = byId('doc_number');
    const docHelp   = byId('doc_help');

    const DOC = {
        CEDULA: { regex: /[^0-9]/g, max: 10, msg: 'Cédula: solo números, 10 caracteres.' },
        OTHER:  { regex: /[^a-zA-Z0-9]/g, max: 15, msg: 'Pasaporte/CI/NU: alfanumérico, 15 caracteres.' }
    };

    const applyDocRules = () => {
        const r = (docType.value === 'CEDULA') ? DOC.CEDULA : DOC.OTHER;
        docNumber.value     = docNumber.value.replace(r.regex, '').slice(0, r.max);
        docNumber.maxLength = r.max;
        docNumber.inputMode = (docType.value === 'CEDULA') ? 'numeric' : 'text';
        docNumber.placeholder = (docType.value === 'CEDULA')
            ? 'Solo números (10 dígitos)'
            : 'Letras y números (hasta 15)';
        docHelp.textContent = r.msg;
    };

    docType.addEventListener('change', applyDocRules);
    docNumber.addEventListener('input', applyDocRules);
    document.addEventListener('DOMContentLoaded', applyDocRules);

    /* Combos dinámicos */
    const area       = byId('id_area');
    const cargo      = byId('id_cargo');
    const supervisor = byId('id_supervisor');

    const URLS = {
        cargos: "<?= base_url('usuarios/api/cargos') ?>",
        sups:   "<?= base_url('usuarios/api/supervisores') ?>"
    };

    const fetchJson = async (url) => {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return [];
        return res.json();
    };

    const loadCargos = async (idArea) => {
        cargo.innerHTML = '<option value="">Cargando…</option>';
        const data = await fetchJson(`${URLS.cargos}?id_area=${encodeURIComponent(idArea)}`);
        cargo.innerHTML = '<option value="">Seleccione…</option>' + data.map(i =>
            `<option value="${i.id_cargo}">${i.nombre_cargo}</option>`
        ).join('');
        const oldCargo = "<?= esc((string) old('id_cargo')) ?>";
        if (oldCargo) cargo.value = oldCargo;
    };

    const loadSupervisors = async (idArea) => {
        supervisor.innerHTML = '<option value="0">Cargando…</option>';
        const data = await fetchJson(`${URLS.sups}?id_area=${encodeURIComponent(idArea)}`);
        supervisor.innerHTML = '<option value="0">Sin supervisor</option>' + data.map(i =>
            `<option value="${i.id_user}">${i.supervisor_label}</option>`
        ).join('');
        const oldSup = "<?= esc((string) old('id_supervisor')) ?>";
        if (oldSup) supervisor.value = oldSup;
    };

    area.addEventListener('change', async () => {
        const idArea = area.value;
        if (!idArea) {
            cargo.innerHTML = '<option value="">Seleccione…</option>';
            supervisor.innerHTML = '<option value="0">Sin supervisor</option>';
            return;
        }
        await Promise.all([loadCargos(idArea), loadSupervisors(idArea)]);
    });

    // Restaurar combos si vuelve con old('id_area')
    document.addEventListener('DOMContentLoaded', () => {
        const initialArea = area.value || "<?= esc((string) old('id_area')) ?>";
        if (initialArea) {
            area.value = initialArea;
            Promise.all([loadCargos(initialArea), loadSupervisors(initialArea)]);
        }
    });
})();
</script>

<?= $this->endSection() ?>