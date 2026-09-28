<?php
/**
 * Guard Setup & Edit View
 * Replicates Screenshot 4: Photo upload dropzone, auto-generated code, credentials provisioning
 * Handles both Create (Setup Guard) and Update (Edit Guard) seamlessly
 */
$isEdit = !empty($isEdit) && !empty($guard);
$guard = $guard ?? [];
$nextCode = $nextCode ?? ($guard['employee_code'] ?? 'GRD-101');
$formAction = $isEdit ? url('/admin/guards/' . (int)$guard['guard_id'] . '/edit') : url('/admin/guards/setup');
$pageTitleText = $isEdit ? 'Edit Guard' : 'Guard Setup';
$hasPhoto = !empty($guard['photo_url']);
?>

<div class="page-container">
    <!-- Form Back Navigation -->
    <a href="<?= url('/admin/guards') ?>" class="form-back-nav">
        <svg fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Back to Guards</span>
    </a>

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-header-title"><?= e($pageTitleText) ?></h1>
        </div>
    </div>

    <?php App\Core\View::component('components/alerts'); ?>

    <form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div style="display: grid; grid-template-columns: 340px 1fr; gap: 1.75rem; align-items: start;">
            
            <!-- Left Column: Photo Upload Dropzone & Operational Preview -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="card" style="padding: 1.75rem; text-align: center;">
                    <h3 style="font-size: 0.9375rem; font-weight: 600; color: #0f172a; margin-bottom: 0.25rem;">Portrait Photo</h3>

                    <!-- Dropzone Area -->
                    <div id="dropzoneContainer" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 2rem 1rem; background: #f8fafc; cursor: pointer; transition: all 0.2s ease; position: relative;" onclick="document.getElementById('guardPhotoInput').click();">
                        <input type="file" id="guardPhotoInput" name="photo" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewPhoto(event)">
                        
                        <div id="dropzonePlaceholder" style="<?= $hasPhoto ? 'display: none;' : '' ?>">
                            <div style="width: 56px; height: 56px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">
                                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="4"/></svg>
                            </div>
                            <p style="font-size: 0.875rem; font-weight: 600; color: #2563eb; margin-bottom: 0.25rem;">Click to upload photo</p>
                            <p style="font-size: 0.75rem; color: #94a3b8;">JPG, PNG, or WEBP up to 2MB</p>
                        </div>

                        <div id="dropzonePreview" style="<?= $hasPhoto ? 'display: block;' : 'display: none;' ?>">
                            <img id="previewImage" src="<?= $hasPhoto ? url('/' . $guard['photo_url']) : '' ?>" alt="Guard Preview" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto 0.75rem auto; border: 3px solid #2563eb; display: block;">
                            <p style="font-size: 0.75rem; color: #2563eb; font-weight: 500;">Click to change photo</p>
                        </div>
                    </div>

                    <div style="margin-top: 1.25rem; text-align: left; background: #eff6ff; border: 1px solid #dbeafe; border-radius: 8px; padding: 0.85rem;">
                        <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                            <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 1px;"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                            <p style="font-size: 0.75rem; color: #1e40af; line-height: 1.4;">
                                The portrait photo is synced to the Flutter mobile app and compared during GPS geofence check-ins.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Personal & Mobile Credentials Form -->
            <div class="card" style="padding: 1.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 600;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a;">Guard Credentials &amp; Profile</h3>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    <!-- Employee Code & Status -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                                Badge / Employee Code
                            </label>
                            <input type="text" name="employee_code" class="form-control" value="<?= e($isEdit ? ($guard['employee_code'] ?? '') : $nextCode) ?>" readonly style="background: #f8fafc; font-family: monospace; font-weight: 700; color: #2563eb;">
                            <span style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem; display: block;"><?= $isEdit ? 'Assigned sequential badge ID' : 'Auto-generated sequential badge ID' ?></span>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                                Operational Status
                            </label>
                            <select name="status" class="form-control">
                                <option value="0" <?= ($isEdit && (int)($guard['guard_status'] ?? 0) === 0) ? 'selected' : (!$isEdit ? 'selected' : '') ?>>Active / Deployable</option>
                                <option value="1" <?= ($isEdit && (int)($guard['guard_status'] ?? 0) === 1) ? 'selected' : '' ?>>Inactive / Suspended</option>
                            </select>
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Full Name <span style="color: #ef4444;">*</span>
                        </label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <input type="text" name="full_name" class="form-control" placeholder="David Vance" value="<?= e($isEdit ? ($guard['full_name'] ?? '') : '') ?>" required>
                        </div>
                    </div>

                    <!-- Email & Phone -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                                Email Address (Mobile Login) <span style="color: #ef4444;">*</span>
                            </label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <input type="email" name="email" class="form-control" placeholder="guard@apexsecurity.com" value="<?= e($isEdit ? ($guard['email'] ?? '') : '') ?>" required>
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                                Phone Number
                            </label>
                            <div class="input-icon-wrapper">
                                <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <input type="text" name="phone" class="form-control" placeholder="+1 (555) 0199" value="<?= e($isEdit ? ($guard['phone'] ?? '') : '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Password for Flutter Mobile App -->
                    <div>
                        <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 0.4rem;">
                            Mobile App Password <?= $isEdit ? '<span style="font-weight: 400; color: #64748b;">(Leave blank to keep unchanged)</span>' : '<span style="color: #ef4444;">*</span>' ?>
                        </label>
                        <div class="input-icon-wrapper">
                            <svg class="input-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            <input type="password" name="password" class="form-control" placeholder="<?= $isEdit ? 'Leave blank to keep current password' : 'Create login password for mobile Flutter app' ?>" <?= $isEdit ? '' : 'required' ?> minlength="6">
                        </div>
                        <span style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; display: block;">
                            <?= $isEdit ? 'Minimum 6 characters if changing. The guard will use this password to authenticate on the Flutter mobile app.' : 'Minimum 6 characters. The guard will use this email and password to authenticate on the Flutter mobile app.' ?>
                        </span>
                    </div>

                    <!-- Action Buttons -->
                    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; align-items: center; gap: 1rem;">
                        <a href="<?= url('/admin/guards') ?>" class="btn btn-outline" style="text-decoration: none; padding: 0.65rem 1.25rem;">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                            <?= $isEdit ? 'Save Changes' : 'Save &amp; Provision Guard' ?>
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </form>
</div>

<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('previewImage');
            if (previewImg) {
                previewImg.src = e.target.result;
            }
            const placeholder = document.getElementById('dropzonePlaceholder');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            const previewDiv = document.getElementById('dropzonePreview');
            if (previewDiv) {
                previewDiv.style.display = 'block';
            }
        };
        reader.readAsDataURL(file);
    }
}
</script>
