<?php
/**
 * Reusable Topbar Component for Admin and Superadmin Panels
 */
?>
<header class="app-topbar">
    <div class="topbar-left">
        <h2 style="font-size:1.125rem;font-weight:600;color:var(--color-text-primary);">
            <?= e($pageTitle ?? 'Operations') ?>
        </h2>
    </div>
    <div class="topbar-right" style="display:flex;align-items:center;gap:1rem;">
        <span style="font-size:0.875rem;color:var(--color-text-secondary);">
            <strong><?= e(auth()['full_name'] ?? auth()['name'] ?? 'User') ?></strong>
            (<?= e(ucfirst(auth()['role_name'] ?? auth()['role'] ?? 'Admin')) ?>)
            <?php if (!empty(auth()['organization_name'])): ?>
                &bull; <span style="color:#2563eb;font-weight:500;"><?= e(auth()['organization_name']) ?></span>
            <?php endif; ?>
        </span>
        <form method="POST" action="<?= url('/logout') ?>" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn" style="background:#f1f5f9;color:#475569;font-size:0.8125rem;padding:0.4rem 0.8rem;">Sign Out</button>
        </form>
    </div>
</header>
