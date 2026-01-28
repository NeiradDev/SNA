<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="container-fluid">

    <?php
        /**
         * Flashdata de errores:
         * - El Controller guarda errores como: ->with('errors', [...])
         * - Aquí los leemos para mostrarlos en un modal Bootstrap
         */
        $errors = session()->getFlashdata('errors');
    ?>

    <!-- ============================================================
         MODAL DE ERRORES
         - Se muestra solo si existen errores en sesión
         - Lista todos los mensajes devueltos por el Controller
         ============================================================ -->
    <?php if (!empty($errors)): ?>
        <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle me-2"></i>Revisa los datos
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <ul class="mb-0">
                            <?php foreach ($errors as $msg): ?>
                                <li><?= esc($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-dark px-4" data-bs-dismiss="modal">Entendido</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auto-abrir el modal cuando la página termine de cargar -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('feedbackModal');
                if (modalEl && window.bootstrap) {
                    new bootstrap.Modal(modalEl).show();
                }
            });
        </script>
    <?php endif; ?>


    <!-- ============================================================
         LAYOUT PRINCIPAL
         - Columna izquierda: formulario
         - Columna derecha: tarjeta de ayuda
         ============================================================ -->
    <div class="row">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm pt-2">
                <div class="card-body p-4">

                    <!-- ============================================================
                         FORMULARIO
                         - action: URL del Controller (store)
                         - csrf_field(): protección CSRF de CodeIgniter
                         ============================================================ -->
                    <form action="<?= base_url('usuarios/guardar') ?>" method="POST">
                        <?= csrf_field() ?>

                        <h5 class="mb-4 text-muted">
                            <i class="bi bi-person-badge me-2"></i>Información Personal
                        </h5>

                        <div class="row g-3">

                            <!-- Nombres -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Nombres</label>
                                <input
                                    type="text"
                                    name="nombres"
                                    class="form-control bg-light border-0"
                                    placeholder="Ej. Juan Carlos"
                                    required
                                    maxlength="32"
                                    value="<?= esc(old('nombres')) ?>"
                                >
                            </div>

                            <!-- Apellidos -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Apellidos</label>
                                <input
                                    type="text"
                                    name="apellidos"
                                    class="form-control bg-light border-0"
                                    placeholder="Ej. Armas"
                                    required
                                    maxlength="32"
                                    value="<?= esc(old('apellidos')) ?>"
                                >
                            </div>

                            <!-- ============================================================
                                 Documento
                                 - doc_type: select (CEDULA / PASAPORTE)
                                 - cedula: input texto controlado por JS:
                                   * CÉDULA: solo números, max 10
                                   * PASAPORTE/CI/NU: alfanumérico, max 15
                                 ============================================================ -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Tipo de documento</label>
                                <select name="doc_type" id="doc_type" class="form-select bg-light border-0" required>
                                    <option value="CEDULA" <?= old('doc_type') === 'CEDULA' || old('doc_type') === null ? 'selected' : '' ?>>
                                        Cédula
                                    </option>
                                    <option value="PASAPORTE" <?= old('doc_type') === 'PASAPORTE' ? 'selected' : '' ?>>
                                        Pasaporte/CI/NU
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Número de documento</label>
                                <input
                                    type="text"
                                    name="cedula"
                                    id="doc_number"
                                    class="form-control bg-light border-0"
                                    placeholder="Ingrese el número"
                                    required
                                    value="<?= esc(old('cedula')) ?>"
                                >
                                <div class="form-text small text-muted" id="doc_help"></div>
                            </div>

                            <!-- Contraseña -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Contraseña</label>
                                <input type="password" name="password" class="form-control bg-light border-0" required>
                            </div>

                            <!-- Sección asignación -->
                            <div class="col-12 my-4">
                                <h5 class="text-muted">
                                    <i class="bi bi-building me-2"></i>Asignación y Estructura
                                </h5>
                                <hr class="opacity-25">
                            </div>

                            <!-- Agencia -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Agencia</label>
                                <select name="id_agencias" id="id_agencias" class="form-select bg-light border-0" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach (($agencias ?? []) as $ag): ?>
                                        <option
                                            value="<?= esc($ag['id_agencias']) ?>"
                                            <?= old('id_agencias') == $ag['id_agencias'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($ag['nombre_agencia']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Área (dispara carga dinámica de cargos y supervisores) -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Área</label>
                                <select name="id_area" id="id_area" class="form-select bg-light border-0" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach (($areas ?? []) as $ar): ?>
                                        <option
                                            value="<?= esc($ar['id_area']) ?>"
                                            <?= old('id_area') == $ar['id_area'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($ar['nombre_area']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Cargo (se llena dinámicamente según área) -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Cargo</label>
                                <select name="id_cargo" id="id_cargo" class="form-select bg-light border-0" required>
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>

                            <!-- Supervisor (se llena dinámicamente según área + gerencia) -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Supervisor</label>
                                <select name="id_supervisor" id="id_supervisor" class="form-select bg-light border-0">
                                    <option value="0">Sin Supervisor</option>
                                </select>
                            </div>

                            <!-- Activo + Botones -->
                            <div class="col-12 mt-5 d-flex justify-content-between align-items-center">
                                <div class="form-check form-switch">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="activo"
                                        id="activo"
                                        <?= old('activo') === null || old('activo') ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="activo">Usuario Activo</label>
                                </div>

                                <div>
                                    <a href="<?= base_url('usuarios') ?>" class="btn btn-light px-4 me-2">Cancelar</a>
                                    <button type="submit" class="btn btn-dark px-5 shadow-sm">
                                        Registrar Usuario
                                    </button>
                                </div>
                            </div>

                        </div>
                    </form>
                    <!-- /FORMULARIO -->

                </div>
            </div>
        </div>

        <!-- Tarjeta de ayuda lateral -->
        <div class="col-12 col-lg-3">
            <div class="card border-0 bg-dark text-white shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold">
                        <i class="bi bi-info-circle me-2"></i>Ayuda
                    </h6>
                    <p class="small opacity-75">
                        Asegúrese de que el <strong>documento</strong> no esté registrado previamente.
                        Los campos con IDs son obligatorios para la estructura de reportes.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // ============================================================
    // 1) Reglas del documento (tipo y formato)
    // - CÉDULA: solo números, max 10
    // - PASAPORTE/CI/NU: alfanumérico, max 15
    // ============================================================
    const docType   = document.getElementById('doc_type');
    const docNumber = document.getElementById('doc_number');
    const docHelp   = document.getElementById('doc_help');

    function applyDocRules() {
        const type = docType.value;

        if (type === 'CEDULA') {
            // Filtra a solo números y corta a 10
            docNumber.value = docNumber.value.replace(/[^0-9]/g, '').slice(0, 10);

            // Ajusta comportamiento del input
            docNumber.maxLength = 10;
            docNumber.inputMode = 'numeric';
            docNumber.placeholder = 'Solo números (10 dígitos)';

            // Texto de ayuda (UI)
            docHelp.textContent = 'Cédula: solo números, máximo 10 caracteres.';
            return;
        }

        // PASAPORTE/CI/NU: alfanumérico, max 15
        docNumber.value = docNumber.value.replace(/[^a-zA-Z0-9]/g, '').slice(0, 15);
        docNumber.maxLength = 15;
        docNumber.inputMode = 'text';
        docNumber.placeholder = 'Letras y números (hasta 15)';
        docHelp.textContent = 'Pasaporte/CI/NU: letras y números, máximo 15 caracteres.';
    }

    // Aplica reglas al escribir y al cambiar el tipo
    docNumber.addEventListener('input', applyDocRules);
    docType.addEventListener('change', applyDocRules);

    // Inicializa al cargar (por si hay old('...'))
    document.addEventListener('DOMContentLoaded', applyDocRules);

    // ============================================================
    // 2) Combos dinámicos (funcionalidad intacta)
    // - Al cambiar Área:
    //   * carga Cargos desde /usuarios/api/cargos?id_area=#
    //   * carga Supervisores desde /usuarios/api/supervisores?id_area=#
    // ============================================================
    const areaSelect       = document.getElementById('id_area');
    const cargoSelect      = document.getElementById('id_cargo');
    const supervisorSelect = document.getElementById('id_supervisor');

    const cargosUrl       = "<?= base_url('usuarios/api/cargos') ?>";
    const supervisorsUrl  = "<?= base_url('usuarios/api/supervisores') ?>";

    function resetSelect(selectEl, firstOptionHtml) {
        selectEl.innerHTML = firstOptionHtml;
    }

    async function loadCargos(areaId) {
        resetSelect(cargoSelect, '<option value="">Cargando...</option>');

        const res = await fetch(`${cargosUrl}?id_area=${encodeURIComponent(areaId)}`, {
            headers: { 'Accept': 'application/json' }
        });

        const data = await res.json();

        let html = '<option value="">Seleccione...</option>';
        data.forEach(item => {
            html += `<option value="${item.id_cargo}">${item.nombre_cargo}</option>`;
        });

        cargoSelect.innerHTML = html;

        // Restaurar selección previa si el form volvió con errores
        const oldCargo = "<?= esc((string) old('id_cargo')) ?>";
        if (oldCargo) cargoSelect.value = oldCargo;
    }

    async function loadSupervisors(areaId) {
        supervisorSelect.innerHTML = '<option value="0">Cargando...</option>';

        const res = await fetch(`${supervisorsUrl}?id_area=${encodeURIComponent(areaId)}`, {
            headers: { 'Accept': 'application/json' }
        });

        const data = await res.json();

        let html = '<option value="0">Sin Supervisor</option>';
        data.forEach(item => {
            // supervisor_label viene del model: "Nombre Apellido — Cargo"
            html += `<option value="${item.id_user}">${item.supervisor_label}</option>`;
        });

        supervisorSelect.innerHTML = html;

        // Restaurar selección previa si el form volvió con errores
        const oldSup = "<?= esc((string) old('id_supervisor')) ?>";
        if (oldSup) supervisorSelect.value = oldSup;
    }

    areaSelect.addEventListener('change', async function () {
        const areaId = this.value;

        // Si no hay área, resetea combos dependientes
        if (!areaId) {
            resetSelect(cargoSelect, '<option value="">Seleccione...</option>');
            resetSelect(supervisorSelect, '<option value="0">Sin Supervisor</option>');
            return;
        }

        // Carga ambos combos en paralelo
        await Promise.all([loadCargos(areaId), loadSupervisors(areaId)]);
    });

    // Si el formulario vuelve con old('id_area'), recargamos cargos y supervisores
    const initialArea = areaSelect.value;
    if (initialArea) {
        Promise.all([loadCargos(initialArea), loadSupervisors(initialArea)]);
    }
})();
</script>

<?= $this->endSection() ?>
