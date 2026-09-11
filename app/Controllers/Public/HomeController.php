<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;

/**
 * Public Website Controller
 * Handles Landing, About, Services, Features, and Contact sections
 */
class HomeController extends Controller
{
    public function index(): void
    {
        $this->render('public/index', [
            'pageTitle' => 'Secure360 - Security Operations Management Platform',
        ], 'layouts/main');
    }
}
