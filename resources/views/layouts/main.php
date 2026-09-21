<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Secure360 - Security Operations Platform') ?></title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="shortcut icon" href="<?= asset('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="public-layout">
    <header class="public-header" style="background:#fff;border-bottom:1px solid #e2e8f0;padding:0.75rem 2rem;display:flex;justify-content:space-between;align-items:center;">
        <div class="logo">
            <a href="<?= url('/') ?>" style="display:flex;align-items:center;text-decoration:none;">
                <img src="<?= asset('images/logo.png') ?>" alt="Secure360" style="height:36px;max-width:180px;object-fit:contain;">
            </a>
        </div>
        <nav>
            <a href="<?= url('/login') ?>" class="btn btn-primary">Sign In</a>
        </nav>
    </header>

    <main>
        <?= $content ?? '' ?>
    </main>
</body>
</html>
