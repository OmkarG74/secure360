<?php
/**
 * Breadcrumb Component
 * Usage: View::component('components/breadcrumb', ['crumbs' => [['label' => 'Home', 'url' => '/'], ['label' => 'Clients']]])
 */
?>
<nav class="breadcrumb" style="font-size:0.8125rem;color:var(--color-text-muted);margin-bottom:1rem;">
    <?php if (!empty($crumbs) && is_array($crumbs)): ?>
        <?php foreach ($crumbs as $index => $crumb): ?>
            <?php if ($index > 0): ?>
                <span style="margin: 0 0.5rem;">/</span>
            <?php endif; ?>
            <?php if (!empty($crumb['url'])): ?>
                <a href="<?= e($crumb['url']) ?>" style="color:var(--color-text-secondary);"><?= e($crumb['label']) ?></a>
            <?php else: ?>
                <span style="color:var(--color-text-primary);font-weight:500;"><?= e($crumb['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</nav>
