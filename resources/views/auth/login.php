<?php
/**
 * Authentication View Blueprint
 * Web login entry point for Superadmin and Admin users
 */
?>
<div style="min-height: calc(100vh - 70px); display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div class="card" style="width: 100%; max-width: 420px; padding: 2.5rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #0f172a;">Sign in to Secure360</h1>
            <p style="font-size: 0.875rem; color: #475569; margin-top: 0.25rem;">Superadmin & Organisation Admin Portal</p>
        </div>

        <?php App\Core\View::component('components/alerts'); ?>

        <form method="POST" action="<?= url('/login') ?>" style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?= csrf_field() ?>
            <?php if (!empty($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?= e($_GET['redirect']) ?>">
            <?php endif; ?>

            <div>
                <label for="email" style="display: block; font-size: 0.875rem; font-weight: 500; color: #1e293b; margin-bottom: 0.35rem;">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="admin@example.com">
            </div>

            <div>
                <label for="password" style="display: block; font-size: 0.875rem; font-weight: 500; color: #1e293b; margin-bottom: 0.35rem;">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.9375rem;">
                Sign In
            </button>
        </form>

        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; text-align: center;">
            <p style="font-size: 0.8125rem; color: #64748b;">
                Security Guards access operations via the <strong>Secure360 Flutter Mobile App</strong>.
            </p>
        </div>
    </div>
</div>
