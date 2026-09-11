<?php
/**
 * Alerts Component
 * Renders session flash alerts (success, error, info, warning)
 */
$types = ['success' => '#10b981', 'error' => '#ef4444', 'info' => '#2563eb', 'warning' => '#f59e0b'];
?>
<div class="alerts-container" style="margin-bottom:1.5rem;">
    <?php foreach ($types as $type => $color): ?>
        <?php if ($msg = App\Core\Session::flash($type)): ?>
            <div class="alert alert-<?= e($type) ?>" style="padding:0.875rem 1.25rem;border-radius:var(--radius-sm);background:#fff;border-left:4px solid <?= $color ?>;box-shadow:var(--shadow-sm);margin-bottom:0.75rem;font-size:0.875rem;color:var(--color-text-primary);">
                <?= e($msg) ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
