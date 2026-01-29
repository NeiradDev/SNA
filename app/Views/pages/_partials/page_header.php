<?php

?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0"><?= esc($title ?? '') ?></h2>
        <?php if (!empty($subtitle)): ?>
            <p class="text-muted mb-0"><?= esc($subtitle) ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($actionUrl)): ?>
        <a href="<?= esc($actionUrl) ?>" class="btn btn-dark px-4 shadow-sm">
            <?php if (!empty($actionIcon)): ?>
                <i class="<?= esc($actionIcon) ?> me-2"></i>
            <?php endif; ?>
            <?= esc($actionText ?? 'Acción') ?>
        </a>
    <?php endif; ?>
</div>
