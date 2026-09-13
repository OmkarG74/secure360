<?php
/**
 * Breadcrumb Component
 * Usage: View::component('components/breadcrumb', ['crumbs' => [['label' => 'Home', 'url' => '/'], ['label' => 'Clients']]])
 */
?>
<nav class="app-breadcrumb">
    <?php if (!empty($crumbs) && is_array($crumbs)): ?>
        <?php foreach ($crumbs as $index => $crumb): ?>
            <?php if ($index > 0): ?>
                <span class="breadcrumb-separator">/</span>
            <?php endif; ?>
            <?php if (!empty($crumb['url'])): ?>
                <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
            <?php else: ?>
                <span class="breadcrumb-current"><?= e($crumb['label']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</nav>
