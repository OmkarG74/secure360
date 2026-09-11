<?php
/**
 * Edit Guard Profile View
 */
$guard = $guard ?? [];
?>

<div class="breadcrumb" style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1rem;">
    <a href="<?= url('/admin/guards') ?>" style="color: #2563eb; text-decoration: none;">Guards</a>
    <span style="margin: 0 0.5rem;">/</span>
    <span style="color: #0f172a; font-weight: 500;">Edit <?= e($guard['full_name'] ?? 'Guard') ?></span>
</div>

<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Edit Guard Profile</h1>
    <p style="font-size: 0.875rem; color: #64748b;">Update operational status, contact information, or mobile credentials.</p>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<form method="POST" action="<?= url('/admin/guards/' . $guard['guard_id'] . '/edit') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.75rem; align-items: start;">
        
        <!-- Left: Photo -->
        <div class="card" style="padding: 1.75rem; text-align: center;">
            <h3 style="font-size: 0.9375rem; font-weight: 600; color: #0f172a; margin-bottom: 0.25rem;">Guard Portrait</h3>
            
            <div style="margin: 1.25rem auto;">
                <?php if (!empty($guard['photo_url'])): ?>
                    <img id="previewImage" src="<?= url('/' . $guard['photo_url']) ?>" alt="Guard Photo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #2563eb; display: block; margin: 0 auto;">
                <?php else: ?>
                    <div id="previewInitials" style="width: 120px; height: 120px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 2.25rem; font-weight: 700; margin: 0 auto; border: 2px dashed #93c5fd;">
                        <?= strtoupper(substr($guard['full_name'] ?? 'G', 0, 2)) ?>
                    </div>
                    <img id="previewImage" src="" alt="Guard Photo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #2563eb; display: none; margin: 0 auto;">
                <?php endif; ?>
            </div>

            <input type="file" id="guardPhotoInput" name="photo" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewPhoto(event)">
            <button type="button" class="btn btn-outline" style="font-size: 0.8125rem; width: 100%;" onclick="document.getElementById('guardPhotoInput').click();">
                Change Portrait Photo
            </button>
        </div>

        <!-- Right: Fields -->
        <div class="card" style="padding: 1.75rem;">
            <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                Guard Information
            </h3>

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Badge / Code</label>
                        <input type="text" class="form-control" value="<?= e($guard['employee_code']) ?>" readonly style="background: #f8fafc; font-family: monospace; font-weight: 700;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Status</label>
                        <select name="status" class="form-control">
                            <option value="0" <?= (int)$guard['guard_status'] === 0 ? 'selected' : '' ?>>Active / Deployable</option>
                            <option value="1" <?= (int)$guard['guard_status'] === 1 ? 'selected' : '' ?>>Inactive / Suspended</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?= e($guard['full_name']) ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Email Address</label>
                        <input type="email" class="form-control" value="<?= e($guard['email']) ?>" readonly style="background: #f8fafc;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($guard['phone'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">Reset Password (Leave blank to keep unchanged)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6">
                </div>

                <div style="margin-top: 1rem; display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="<?= url('/admin/guards') ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.65rem 1.25rem;">Cancel</a>
                    <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem;">Save Changes</button>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('previewImage');
            img.src = e.target.result;
            img.style.display = 'block';
            const initials = document.getElementById('previewInitials');
            if (initials) initials.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}
</script>
