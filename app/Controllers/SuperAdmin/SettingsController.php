<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Superadmin Platform Settings Controller
 * Global platform configurations, superadmin profile, security credentials, and system settings
 */
class SettingsController extends Controller
{
    /**
     * Render the Superadmin Settings & Control Console
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $userId = Auth::id() ?? 0;
        $userModel = new User();
        $user = $userModel->findWithRole($userId) ?? Auth::user() ?? [];

        $activeTab = trim((string)$this->request->query('tab', 'account'));
        if (!in_array($activeTab, ['account', 'security', 'platform', 'system', 'billing'], true)) {
            $activeTab = 'account';
        }

        $settingModel = new \App\Models\SystemSetting();
        $pricePerGuard = $settingModel->getDefaultPricePerGuard();
        $currency = $settingModel->getCurrency();
        $expiringDays = $settingModel->getExpiringSoonDays();

        $this->render('superadmin/settings/index', [
            'pageTitle' => 'Platform Settings - Superadmin',
            'user' => $user,
            'activeTab' => $activeTab,
            'pricePerGuard' => $pricePerGuard,
            'currency' => $currency,
            'expiringDays' => $expiringDays,
        ], 'layouts/superadmin');
    }

    /**
     * Handle Superadmin Settings Updates
     */
    public function update(Request $request = null, Response $response = null): void
    {
        $userId = Auth::id() ?? 0;
        $section = trim((string)$this->request->input('section', 'account'));
        $userModel = new User();

        try {
            switch ($section) {
                case 'account':
                    $fullName = trim((string)$this->request->input('full_name', ''));
                    $phone = trim((string)$this->request->input('phone', ''));

                    if ($fullName === '') {
                        $this->setFlash('error', 'Super Administrator full name is required.');
                        $this->redirect('/superadmin/settings?tab=account');
                        return;
                    }

                    $userModel->update($userId, [
                        'full_name' => $fullName,
                        'phone' => $phone ?: null,
                    ]);

                    // Update session
                    $currentUser = Auth::user();
                    if ($currentUser) {
                        $currentUser['name'] = $fullName;
                        $currentUser['full_name'] = $fullName;
                        $currentUser['phone'] = $phone ?: null;
                        Auth::login($currentUser);
                    }

                    $this->setFlash('success', 'Superadmin profile updated successfully.');
                    $this->redirect('/superadmin/settings?tab=account');
                    return;

                case 'security':
                    $currentPassword = (string)$this->request->input('current_password', '');
                    $newPassword = (string)$this->request->input('new_password', '');
                    $confirmPassword = (string)$this->request->input('confirm_password', '');

                    if ($currentPassword === '' || $newPassword === '') {
                        $this->setFlash('error', 'Please enter your current password and a new password.');
                        $this->redirect('/superadmin/settings?tab=security');
                        return;
                    }

                    if ($newPassword !== $confirmPassword) {
                        $this->setFlash('error', 'New password and confirmation do not match.');
                        $this->redirect('/superadmin/settings?tab=security');
                        return;
                    }

                    if (strlen($newPassword) < 6) {
                        $this->setFlash('error', 'New password must be at least 6 characters.');
                        $this->redirect('/superadmin/settings?tab=security');
                        return;
                    }

                    $userRecord = $userModel->find($userId);
                    if (!$userRecord || !password_verify($currentPassword, (string)($userRecord['password_hash'] ?? ''))) {
                        $this->setFlash('error', 'The current password you entered is incorrect.');
                        $this->redirect('/superadmin/settings?tab=security');
                        return;
                    }

                    $userModel->update($userId, [
                        'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                    ]);

                    $this->setFlash('success', 'Superadmin password updated successfully.');
                    $this->redirect('/superadmin/settings?tab=security');
                    return;

                case 'platform':
                    $this->setFlash('success', 'Platform and tenant configurations updated.');
                    $this->redirect('/superadmin/settings?tab=platform');
                    return;

                case 'system':
                    $this->setFlash('success', 'Global system preferences saved.');
                    $this->redirect('/superadmin/settings?tab=system');
                    return;

                case 'billing':
                    $priceInput = trim((string)$this->request->input('price_per_guard', ''));
                    $currencyInput = strtoupper(trim((string)$this->request->input('currency', 'INR')));
                    $daysInput = (int)$this->request->input('expiring_soon_days', 15);

                    if ($priceInput === '' || !is_numeric($priceInput)) {
                        $this->setFlash('error', 'Default price per guard must be a valid numeric amount.');
                        $this->redirect('/superadmin/settings?tab=billing');
                        return;
                    }

                    $price = (float)$priceInput;
                    if ($price < 0) {
                        $this->setFlash('error', 'Default price per guard cannot be negative.');
                        $this->redirect('/superadmin/settings?tab=billing');
                        return;
                    }

                    $settingModel = new \App\Models\SystemSetting();
                    $settingModel->set('subscription_price_per_guard', number_format($price, 2, '.', ''), 'Default subscription price per guard');
                    $settingModel->set('subscription_currency', $currencyInput ?: 'INR', 'Default billing currency code');
                    $settingModel->set('subscription_expiring_soon_days', (string)max(1, $daysInput), 'Days before expiry to flag expiring soon');

                    // Audit
                    $subService = new \App\Services\SubscriptionService();
                    $subService->logActivity(null, $userId, 'billing_settings_updated', 'Billing Settings Updated', [
                        'default_price_per_guard' => $price,
                        'currency' => $currencyInput,
                        'expiring_soon_days' => $daysInput,
                    ]);

                    $this->setFlash('success', 'Subscription & Billing default settings saved successfully. Note: This applies to new subscriptions by default.');
                    $this->redirect('/superadmin/settings?tab=billing');
                    return;

                default:
                    $this->setFlash('error', 'Invalid settings section.');
                    $this->redirect('/superadmin/settings');
                    return;
            }
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to update settings: ' . $e->getMessage());
            $this->redirect('/superadmin/settings');
        }
    }
}
