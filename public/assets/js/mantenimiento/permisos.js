
  function checkAllPermissions(state) {
    document.querySelectorAll('.permission-checkbox').forEach(function (checkbox) {
      checkbox.checked = !!state;
    });
    updateCheckedStyles();
  }

  function toggleModule(moduleKey, state) {
    document.querySelectorAll('.module-' + moduleKey).forEach(function (checkbox) {
      checkbox.checked = !!state;
    });
    updateCheckedStyles();
  }

  function toggleModuleBody(moduleKey) {
    var body = document.getElementById('module-body-' + moduleKey);
    if (!body) return;

    body.style.display = (body.style.display === 'none') ? '' : 'none';
  }

  function expandAllModules() {
    document.querySelectorAll('[id^="module-body-"]').forEach(function (el) {
      el.style.display = '';
    });
  }

  function collapseAllModules() {
    document.querySelectorAll('[id^="module-body-"]').forEach(function (el) {
      el.style.display = 'none';
    });
  }

  function updateCheckedStyles() {
    document.querySelectorAll('.ws-permission-item').forEach(function (item) {
      var checkbox = item.querySelector('.permission-checkbox');
      if (!checkbox) return;

      item.classList.toggle('is-checked', checkbox.checked);
    });

    document.querySelectorAll('.ws-target-item').forEach(function (item) {
      var checkbox = item.querySelector('.target-cargo-checkbox');
      if (!checkbox) return;

      item.classList.toggle('is-checked', checkbox.checked);
    });
  }

  function syncMultiTargetsToForm() {
    var hiddenContainer = document.getElementById('multiTargetsHidden');
    if (!hiddenContainer) return;

    hiddenContainer.innerHTML = '';

    document.querySelectorAll('.target-cargo-checkbox:checked').forEach(function (checkbox) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'target_cargo_ids[]';
      input.value = checkbox.value;
      hiddenContainer.appendChild(input);
    });
  }

  function updateMultiSelectedNote() {
    var checked = document.querySelectorAll('.target-cargo-checkbox:checked');
    var note = document.getElementById('multiSelectedNote');
    if (!note) return;

    note.textContent = checked.length === 0
      ? 'No has seleccionado cargos destino aún.'
      : 'Cargos destino seleccionados: ' + checked.length;
  }

  function updateSelectionMode() {
    var singleMode = document.getElementById('mode_single');
    var multiBox = document.getElementById('multiTargetBox');

    if (!singleMode || !multiBox) return;

    if (singleMode.checked) {
      multiBox.classList.add('d-none');
    } else {
      multiBox.classList.remove('d-none');
    }
  }

  document.getElementById('mode_single')?.addEventListener('change', updateSelectionMode);
  document.getElementById('mode_multi')?.addEventListener('change', updateSelectionMode);

  document.querySelectorAll('.target-cargo-checkbox').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
      updateCheckedStyles();
      updateMultiSelectedNote();
      syncMultiTargetsToForm();
    });
  });

  document.getElementById('permissionForm')?.addEventListener('submit', function () {
    syncMultiTargetsToForm();
  });

  updateSelectionMode();
  updateCheckedStyles();
  updateMultiSelectedNote();
  syncMultiTargetsToForm();