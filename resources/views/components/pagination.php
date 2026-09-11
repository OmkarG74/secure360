<?php
/**
 * Reusable Pagination Component Blueprint
 */
$currentPage = $currentPage ?? 1;
$totalPages = $totalPages ?? 1;
?>
<?php if ($totalPages > 1): ?>
<div class="pagination-wrapper" style="display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;font-size:0.875rem;color:var(--color-text-secondary);">
    <div>Page <?= e($currentPage) ?> of <?= e($totalPages) ?></div>
    <div style="display:flex;gap:0.5rem;">
        <?php if ($currentPage > 1): ?>
            <a href="?page=<?= e($currentPage - 1) ?>" class="btn" style="background:#fff;border:1px solid var(--color-border);padding:0.35rem 0.75rem;">Previous</a>
        <?php endif; ?>
        <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?= e($currentPage + 1) ?>" class="btn" style="background:#fff;border:1px solid var(--color-border);padding:0.35rem 0.75rem;">Next</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
