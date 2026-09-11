<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Secure360 - Security Operations Platform') ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="public-layout">
    <header class="public-header" style="background:#fff;border-bottom:1px solid #e2e8f0;padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;">
        <div class="logo">
            <a href="<?= url('/') ?>" style="font-size:1.25rem;font-weight:700;color:#0f172a;">Secure<span style="color:#2563eb;">360</span></a>
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
