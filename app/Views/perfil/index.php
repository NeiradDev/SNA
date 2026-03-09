<?= $this->extend('layouts/main') ?> 

<?= $this->section('contenido') ?>

<?php
/**
 * =========================================================
 * Vista: perfil/index.php
 * =========================================================
 * ✅ Cambios implementados:
 * - helper('form') para old()
 * - Teléfono con prefijo +593 por defecto
 * - Correo separado en usuario + dominio
 * - Dominio por defecto @bestpc.ec
 * - Checkbox para habilitar otros dominios
 * - Checkbox para mostrar/ocultar cambio de contraseña ojo
 * =========================================================
 */

helper('form');

/**
 * =========================================================
 * Preparación de correo para precargar el formulario
 * =========================================================
 */
$currentEmail = old('correo');

if ($currentEmail === null) {
    $currentEmail = $correo ?? '';
}

$currentEmail = trim((string) $currentEmail);

$emailUserPart   = '';
$emailDomainPart = '@bestpc.ec';

if ($currentEmail !== '' && filter_var($currentEmail, FILTER_VALIDATE_EMAIL)) {
    $emailParts = explode('@', $currentEmail, 2);

    if (count($emailParts) === 2) {
        $emailUserPart   = $emailParts[0];
        $emailDomainPart = '@' . strtolower($emailParts[1]);
    }
}

$allowedDomains = [
    '@bestpc.ec',
    '@gmail.com',
    '@outlook.com',
    '@hotmail.com',
    '@yahoo.com',
    '@live.com',
];

$useOtherDomainChecked = ($emailDomainPart !== '@bestpc.ec');

/**
 * =========================================================
 * Teléfono para precarga
 * =========================================================
 */
$phoneValue = old('telefono');
if ($phoneValue === null) {
    $phoneValue = $telefono ?? '';
}
$phoneValue = trim((string) $phoneValue);

// Si no hay teléfono, dejamos +593 por defecto
if ($phoneValue === '') {
    $phoneValue = '+593';
}
?>

<style>
    .profile-card{
        border: 1px solid rgba(0,0,0,.06);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 10px 24px rgba(0,0,0,.06);
    }

    .profile-section-title{
        font-weight: 700;
    }

    .profile-soft-box{
        border: 1px dashed rgba(0,0,0,.12);
        border-radius: 12px;
        padding: 12px;
        background: rgba(0,0,0,.015);
    }

    .password-box-hidden{
        display: none;
    }

    .email-domain-select[disabled]{
        background-color: #e9ecef;
        opacity: 1;
    }

    .phone-hint,
    .email-hint{
        font-size: .82rem;
    }
</style>

<div class="container py-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="m-0">Mi Perfil</h5>

        <?php if (isset($activo)) : ?>
            <span class="badge <?= ((int)$activo === 1) ? 'bg-success' : 'bg-secondary' ?>">
                <?= ((int)$activo === 1) ? 'Activo' : 'Inactivo' ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- =========================
         MENSAJES (FLASHDATA)
    ========================== -->
    <?php if (session()->getFlashdata('success')) : ?>
        <div class="alert alert-success py-2"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')) : ?>
        <div class="alert alert-danger py-2"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('info')) : ?>
        <div class="alert alert-info py-2"><?= esc(session()->getFlashdata('info')) ?></div>
    <?php endif; ?>

    <div class="card profile-card border-0 p-3">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="profile-section-title">Información personal</div>
            <small class="text-muted">Datos registrados</small>
        </div>

        <div class="row g-2">
            <div class="col-12 col-md-6">
                <div class="small text-muted">Nombre</div>
                <div class="fw-semibold">
                    <?= esc(trim(($nombres ?? '') . ' ' . ($apellidos ?? ''))) ?>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="small text-muted">Cédula</div>
                <div class="fw-semibold">
                    <?= esc($cedula ?? '') ?>
                </div>
            </div>
        </div>

        <hr class="my-2">

        <div class="profile-section-title mb-1">Información laboral</div>

        <div class="row g-2">
            <div class="col-6 col-md-4">
                <div class="small text-muted">Cargo</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_cargo ?? ($cargo_nombre ?? '')) ?>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="small text-muted">Área</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_area ?? (string)($id_area ?? '')) ?>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="small text-muted">División</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_division ?? '') ?>
                </div>
            </div>

            <div class="col-6 col-md-4">
                <div class="small text-muted">Agencia</div>
                <div class="fw-semibold text-truncate">
                    <?= esc($nombre_agencia ?? (string)($id_agencias ?? '')) ?>
                </div>
            </div>

            <?php if (!empty($supervisor_nombre)) : ?>
                <div class="col-12 col-md-4">
                    <div class="small text-muted">Supervisor</div>
                    <div class="fw-semibold text-truncate">
                        <?= esc($supervisor_nombre) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="alert alert-warning py-2 px-2 mt-3 mb-0 small" role="alert">
            <b>Importante:</b> Si algún dato está erróneo, comuníquese con el administrador.
        </div>
    </div>

    <div class="card profile-card border-0 p-3 mt-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="profile-section-title">Seguridad y contacto</div>
            <small class="text-muted">Puedes actualizar tu correo, teléfono y contraseña</small>
        </div>

        <form action="<?= base_url('index.php/perfil/update-credentials') ?>" method="post" autocomplete="off" id="profileForm">
            <?= csrf_field() ?>

            <div class="row g-3">
                <!-- =========================
                     CORREO
                ========================== -->
                <div class="col-12">
                    <div class="profile-soft-box">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <label class="form-label small text-muted mb-0">Correo</label>

                            <div class="form-check m-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="allowOtherDomains"
                                    <?= $useOtherDomainChecked ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="allowOtherDomains">
                                    Usar otro dominio
                                </label>
                            </div>
                        </div>

                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-7">
                                <label class="form-label small text-muted mb-1">Usuario del correo</label>
                                <input
                                    type="text"
                                    id="email_user_part"
                                    class="form-control"
                                    value="<?= esc($emailUserPart) ?>"
                                    placeholder="nombre.apellido">
                            </div>

                            <div class="col-12 col-md-5">
                                <label class="form-label small text-muted mb-1">Dominio</label>
                                <select
                                    id="email_domain_part"
                                    class="form-select email-domain-select"
                                    <?= $useOtherDomainChecked ? '' : 'disabled' ?>>
                                    <?php foreach ($allowedDomains as $domain) : ?>
                                        <option value="<?= esc($domain) ?>" <?= $emailDomainPart === $domain ? 'selected' : '' ?>>
                                            <?= esc($domain) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mt-2 email-hint text-muted">
                            Por defecto el correo se guardará con <b>@bestpc.ec</b>. Si marcas “Usar otro dominio”, podrás elegir otro proveedor.
                        </div>

                        <!-- Campo oculto real que se envía al backend -->
                        <input type="hidden" name="correo" id="correo_final" value="<?= esc($currentEmail) ?>">
                    </div>
                </div>

                <!-- =========================
                     TELÉFONO
                ========================== -->
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Número de teléfono</label>
                    <input
                        type="text"
                        name="telefono"
                        id="telefono"
                        class="form-control"
                        value="<?= esc($phoneValue) ?>"
                        placeholder="+593991234567"
                        maxlength="20"
                        autocomplete="off">
                    <small class="text-muted phone-hint">
                        Debe ser un celular ecuatoriano con código de país. Ejemplo: +593991234567
                    </small>
                </div>

                <div class="col-12">
                    <hr class="my-1">
                </div>

                <!-- =========================
                     CHECK CAMBIAR CONTRASEÑA
                ========================== -->
                <div class="col-12">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="changePasswordToggle"
                            name="change_password_toggle"
                            value="1">
                        <label class="form-check-label fw-semibold" for="changePasswordToggle">
                            Quiero cambiar mi contraseña
                        </label>
                    </div>
                </div>

                <!-- =========================
                     BLOQUE CONTRASEÑA OCULTO
                ========================== -->
                <div class="col-12 password-box-hidden" id="passwordFieldsBox">
                    <div class="profile-soft-box">
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Contraseña actual</label>
                                <input
                                    type="password"
                                    name="current_password"
                                    id="current_password"
                                    class="form-control"
                                    placeholder="••••••••">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Nueva contraseña</label>
                                <input
                                    type="password"
                                    name="new_password"
                                    id="new_password"
                                    class="form-control"
                                    placeholder="Mínimo 6 caracteres">
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted mb-1">Confirmar nueva contraseña</label>
                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    class="form-control"
                                    placeholder="Repite la contraseña">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="<?= site_url('home') ?>" class="btn btn-outline-secondary">Volver</a>
                <button type="submit" class="btn btn-dark">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('profileForm');

    const emailUserPartInput   = document.getElementById('email_user_part');
    const emailDomainSelect    = document.getElementById('email_domain_part');
    const allowOtherDomainsChk = document.getElementById('allowOtherDomains');
    const hiddenEmailInput     = document.getElementById('correo_final');

    const phoneInput = document.getElementById('telefono');

    const changePasswordToggle = document.getElementById('changePasswordToggle');
    const passwordFieldsBox    = document.getElementById('passwordFieldsBox');
    const currentPasswordInput = document.getElementById('current_password');
    const newPasswordInput     = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    function updateEmailDomainMode() {
        if (allowOtherDomainsChk.checked) {
            emailDomainSelect.disabled = false;
        } else {
            emailDomainSelect.value = '@bestpc.ec';
            emailDomainSelect.disabled = true;
        }
        buildFinalEmail();
    }

    function buildFinalEmail() {
        const userPart = (emailUserPartInput.value || '').trim();
        const domainPart = allowOtherDomainsChk.checked
            ? emailDomainSelect.value
            : '@bestpc.ec';

        if (userPart !== '') {
            hiddenEmailInput.value = userPart + domainPart;
        } else {
            hiddenEmailInput.value = '';
        }
    }

    function updatePasswordVisibility() {
        if (changePasswordToggle.checked) {
            passwordFieldsBox.style.display = 'block';
        } else {
            passwordFieldsBox.style.display = 'none';
            currentPasswordInput.value = '';
            newPasswordInput.value = '';
            confirmPasswordInput.value = '';
        }
    }

    function enforcePhonePrefix() {
        let value = phoneInput.value || '';

        if (value.trim() === '') {
            phoneInput.value = '+593';
            return;
        }

        if (!value.startsWith('+593')) {
            const onlyDigits = value.replace(/[^\d]/g, '');

            if (onlyDigits.startsWith('593')) {
                phoneInput.value = '+' + onlyDigits;
            } else if (onlyDigits.startsWith('0')) {
                phoneInput.value = '+593' + onlyDigits.substring(1);
            } else if (onlyDigits.startsWith('9')) {
                phoneInput.value = '+593' + onlyDigits;
            } else {
                phoneInput.value = '+593';
            }
        }
    }

    emailUserPartInput.addEventListener('input', buildFinalEmail);
    emailDomainSelect.addEventListener('change', buildFinalEmail);
    allowOtherDomainsChk.addEventListener('change', updateEmailDomainMode);

    changePasswordToggle.addEventListener('change', updatePasswordVisibility);

    phoneInput.addEventListener('focus', function () {
        if ((phoneInput.value || '').trim() === '') {
            phoneInput.value = '+593';
        }
    });

    phoneInput.addEventListener('blur', enforcePhonePrefix);

    form.addEventListener('submit', function () {
        buildFinalEmail();
        enforcePhonePrefix();
    });

    updateEmailDomainMode();
    updatePasswordVisibility();
    enforcePhonePrefix();
});
</script>

<?= $this->endSection() ?>