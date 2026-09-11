<?php
/**
 * Reusable Modal Dialog Component Blueprint
 */
?>
<div id="<?= e($modalId ?? 'generic-modal') ?>" class="modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:999;align-items:center;justify-content:center;">
    <div class="modal-container" style="background:#fff;border-radius:var(--radius-md);width:100%;max-width:500px;box-shadow:var(--shadow-lg);overflow:hidden;">
        <div class="modal-header" style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:1.125rem;font-weight:600;"><?= e($title ?? 'Notice') ?></h3>
            <button type="button" onclick="document.getElementById('<?= e($modalId ?? 'generic-modal') ?>').style.display='none'" style="background:none;border:none;font-size:1.25rem;cursor:pointer;color:var(--color-text-muted);">&times;</button>
        </div>
        <div class="modal-body" style="padding:1.5rem;">
            <?= $body ?? '' ?>
        </div>
    </div>
</div>
