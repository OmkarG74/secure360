<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;

/**
 * Organisation Admin Settings Controller
 * Modular settings for organisation profile, duty shifts, and notification preferences
 */
class SettingsController extends Controller
{
    public function index(): void
    {
        $this->render('admin/settings/index', [
            'pageTitle' => 'Organisation Settings',
            'organisationId' => Auth::organisationId(),
        ], 'layouts/admin');
    }
}
