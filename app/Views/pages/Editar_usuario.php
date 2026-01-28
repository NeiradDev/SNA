<?= $this->extend('layouts/main') ?>

<?= $this->section('contenido') ?>
<div class="container-fluid">

    <?php
        /**
         * ============================================================
         * Vista: Editar_usuario.php
         *
         * Objetivo:
         * - Mostrar formulario para editar un usuario existente
         * - Mantener mismo diseño que Crear_usuario.php
         * - Respetar combos dinámicos:
         *   Área -> carga Cargos y Supervisores por API (fetch)
         *
         * Variables esperadas desde el Controller:
         * - $usuario  : datos del usuario a editar
         * - $agencias : lista de agencias
         * - $areas    : lista de áreas
         *
         * Flashdata:
         * - errors: array de errores devueltos por el Controller para modal
         * ============================================================
         */
        $errors = session()->getFlashdata('errors');

        /**
         * ============================================================
         * Valores base del form:
         * - old() tiene prioridad si el form vuelve por validación fallida
         * - si no hay old(), usamos los valores reales de $usuario
         * ============================================================
         */
        $userId        = (int) ($usuario['id_user'] ?? 0);

        $nombresVal    = old('nombres')   ?? ($usuario['nombres']   ?? '');
        $apellidosVal  = old('apellidos') ?? ($usuario['apellidos'] ?? '');

        // "cedula" ahora es "documento" (puede ser alfanumérico)
        $docNumberVal  = old('cedula')    ?? ($usuario['cedula']    ?? '');

        $agencyVal     = old('id_agencias')   ?? ($usuario['id_agencias']   ?? '');
        $areaVal       = old('id_area')       ?? ($usuario['id_area']       ?? '');
        $cargoVal      = old('id_cargo')      ?? ($usuario['id_cargo']      ?? '');
        $supervisorVal = old('id_supervisor') ?? ($usuario['id_supervisor'] ?? 0);

        /**
         * Activo:
         * - old('activo') puede ser null si no vino del form
         * - si es null, tomamos el valor original del usuario
         */
        $activoOld = old('activo');
        $isActive  = ($activoOld === null)
            ? !empty($usuario['activo'])
            : (bool) $activoOld;

        /**
         * Tipo de documento:
         * - Si el usuario vuelve con old('doc_type'), lo respetamos
         * - Si no, inferimos:
         *   * si el documento tiene solo números => CEDULA
         *   * si tiene letras => PASAPORTE
         */
        $docTypeVal = old('doc_type');
        if ($docTypeVal === null) {
            $docTypeVal = (preg_match('/^\d+$/', (string) $docNumberVal)) ? 'CEDULA' : 'PASAPORTE';
        }
    ?>

    <!-- ============================================================
         MODAL DE ERRORES
         - Se muestra únicamente si hay flashdata('errors')
         - Se auto-abre al cargar la página
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

        <script>
            // Abre el modal al cargar para que el usuario vea los errores inmediatamente
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
         - Izquierda: formulario
         - Derecha: tarjeta de ayuda
         ============================================================ -->
    <div class="row">
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm pt-2">
                <div class="card-body p-4">

                    <!-- ============================================================
                         FORMULARIO DE EDICIÓN
                         - Envía POST a /usuarios/actualizar/{id}
                         - CSRF para seguridad
                         - Password es opcional (si se deja vacío, no cambia)
                         ============================================================ -->
                    <form action="<?= base_url('usuarios/actualizar/' . $userId) ?>" method="POST">
                        <?= csrf_field() ?>

                        <!-- Encabezado del formulario -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1 text-muted">
                                    <i class="bi bi-pencil-square me-2"></i>Editar Usuario
                                </h5>
                                <!-- Si quieres mostrar el ID, descomenta:
                                <div class="small text-muted">ID: <?= esc($userId) ?></div>
                                -->
                            </div>

                            <!-- Botón volver a la lista -->
                            <a href="<?= base_url('usuarios') ?>" class="btn btn-light px-4">
                                Volver
                            </a>
                        </div>

                        <hr class="opacity-25 mb-4">

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
                                    value="<?= esc($nombresVal) ?>"
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
                                    value="<?= esc($apellidosVal) ?>"
                                >
                            </div>

                            <!-- ============================================================
                                 Documento (Tipo + Número)
                                 - doc_type determina reglas del input
                                 - doc_number se “limpia” y limita en JS
                                 ============================================================ -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Tipo de documento</label>
                                <select name="doc_type" id="doc_type" class="form-select bg-light border-0" required>
                                    <option value="CEDULA" <?= $docTypeVal === 'CEDULA' ? 'selected' : '' ?>>Cédula</option>
                                    <option value="PASAPORTE" <?= $docTypeVal === 'PASAPORTE' ? 'selected' : '' ?>>Pasaporte/CI/NU</option>
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
                                    value="<?= esc($docNumberVal) ?>"
                                >
                                <div class="form-text small text-muted" id="doc_help"></div>
                            </div>

                            <!-- Password (opcional) -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Nueva contraseña (opcional)</label>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control bg-light border-0"
                                    placeholder="Dejar vacío para no cambiar"
                                    autocomplete="new-password"
                                >
                                <div class="form-text small text-muted">
                                    Solo se actualizará si escribe una contraseña nueva (mínimo 6 caracteres).
                                </div>
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
                                            <?= (string) $agencyVal === (string) $ag['id_agencias'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($ag['nombre_agencia']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Área (trigger de combos dependientes) -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Área</label>
                                <select name="id_area" id="id_area" class="form-select bg-light border-0" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach (($areas ?? []) as $ar): ?>
                                        <option
                                            value="<?= esc($ar['id_area']) ?>"
                                            <?= (string) $areaVal === (string) $ar['id_area'] ? 'selected' : '' ?>
                                        >
                                            <?= esc($ar['nombre_area']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Cargo (carga dinámica por área) -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small">Cargo</label>
                                <select name="id_cargo" id="id_cargo" class="form-select bg-light border-0" required>
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>

                            <!-- Supervisor (carga dinámica por área + gerencia) -->
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
                                        <?= $isActive ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="activo">Usuario Activo</label>
                                </div>

                                <div>
                                    <a href="<?= base_url('usuarios') ?>" class="btn btn-light px-4 me-2">Cancelar</a>
                                    <button type="submit" class="btn btn-dark px-5 shadow-sm">
                                        Guardar Cambios
                                    </button>
                                </div>
                            </div>

                        </div>
                    </form>
                    <!-- /FORMULARIO -->

                </div>
            </div>
        </div>

        <!-- Tarjeta lateral de ayuda -->
        <div class="col-12 col-lg-3">
            <div class="card border-0 bg-dark text-white shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold">
                        <i class="bi bi-info-circle me-2"></i>Ayuda
                    </h6>
                    <p class="small opacity-75 mb-0">
                        Si no desea cambiar la contraseña, deje el campo “Nueva contraseña” vacío.
                        Verifique que el documento no esté repetido.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // ============================================================
    // 1) Reglas del documento (input dinámico)
    // - Según el tipo seleccionado, limita el input y filtra caracteres
    // ============================================================
    const docType   = document.getElementById('doc_type');
    const docNumber = document.getElementById('doc_number');
    const docHelp   = document.getElementById('doc_help');

    function applyDocRules() {
        const type = docType.value;

        // CÉDULA: solo números, max 10
        if (type === 'CEDULA') {
            docNumber.value = docNumber.value.replace(/[^0-9]/g, '').slice(0, 10);
            docNumber.maxLength = 10;
            docNumber.inputMode = 'numeric';
            docNumber.placeholder = 'Solo números (10 dígitos)';
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

    // Se aplica al escribir y al cambiar el tipo
    docNumber.addEventListener('input', applyDocRules);
    docType.addEventListener('change', applyDocRules);

    // ============================================================
    // 2) Combos dinámicos (cargos y supervisores por área)
    // - Precarga: al entrar, si ya hay un área, carga combos y selecciona valores actuales
    // - Cambio de área: recarga combos dependientes
    // ============================================================
    const areaSelect       = document.getElementById('id_area');
    const cargoSelect      = document.getElementById('id_cargo');
    const supervisorSelect = document.getElementById('id_supervisor');

    const cargosUrl      = "<?= base_url('usuarios/api/cargos') ?>";
    const supervisorsUrl = "<?= base_url('usuarios/api/supervisores') ?>";

    // IDs actuales (del usuario) para re-seleccionarlos después del fetch
    const currentCargoId      = "<?= esc((string) $cargoVal) ?>";
    const currentSupervisorId = "<?= esc((string) $supervisorVal) ?>";

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

        // Selecciona cargo actual (si existe)
        if (currentCargoId) cargoSelect.value = currentCargoId;
    }

    async function loadSupervisors(areaId) {
        supervisorSelect.innerHTML = '<option value="0">Cargando...</option>';

        const res = await fetch(`${supervisorsUrl}?id_area=${encodeURIComponent(areaId)}`, {
            headers: { 'Accept': 'application/json' }
        });

        const data = await res.json();

        let html = '<option value="0">Sin Supervisor</option>';
        data.forEach(item => {
            html += `<option value="${item.id_user}">${item.supervisor_label}</option>`;
        });

        supervisorSelect.innerHTML = html;

        // Selecciona supervisor actual (si existe)
        if (currentSupervisorId) supervisorSelect.value = currentSupervisorId;
    }

    areaSelect.addEventListener('change', async function () {
        const areaId = this.value;

        // Si quitan el área, resetea cargos y supervisores
        if (!areaId) {
            resetSelect(cargoSelect, '<option value="">Seleccione...</option>');
            resetSelect(supervisorSelect, '<option value="0">Sin Supervisor</option>');
            return;
        }

        // Recarga ambos combos en paralelo
        await Promise.all([loadCargos(areaId), loadSupervisors(areaId)]);
    });

    // Inicialización al cargar la página
    document.addEventListener('DOMContentLoaded', function () {
        // 1) Aplica reglas del documento (para placeholder / maxLength)
        applyDocRules();

        // 2) Si ya existe un área seleccionada (usuario), precarga combos
        const initialArea = areaSelect.value;
        if (initialArea) {
            Promise.all([loadCargos(initialArea), loadSupervisors(initialArea)]);
        }
    });
})();
</script>

<?= $this->endSection() ?>
