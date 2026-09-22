<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Secure360 - Superadmin Master Panel') ?></title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="shortcut icon" href="<?= asset('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>
    <div class="app-container">
        <!-- Reusable Sidebar -->
        <?php App\Core\View::component('components/sidebar', ['panel' => 'superadmin']); ?>

        <div class="app-main">
            <!-- Reusable Topbar -->
            <?php App\Core\View::component('components/topbar', ['pageTitle' => $pageTitle ?? 'Superadmin Master Control']); ?>

            <!-- Page Content -->
            <main class="app-content">
                <?php App\Core\View::component('components/alerts'); ?>
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
</body>
</html>
