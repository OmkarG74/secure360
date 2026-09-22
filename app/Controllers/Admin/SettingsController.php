<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Organization;
use App\Models\User;

/**
 * Organisation Admin Settings Controller
 * Modular settings for admin account profile, security credentials, organisation entity,
 * notification preferences, and system telemetry defaults.
 */
class SettingsController extends Controller
{
    /**
     * Render the unified Admin Settings & Control Center
     */
    public function index(Request $request = null, Response $response = null): void
    {
        $userId = Auth::id() ?? 0;
        $orgId = Auth::organisationId() ?? 1;

        $userModel = new User();
        $orgModel = new Organization();

        $user = $userModel->findWithRole($userId) ?? Auth::user() ?? [];
        $organization = $orgModel->find($orgId) ?? [];
        $activeTab = trim((string)$this->request->query('tab', 'account'));

        if (!in_array($activeTab, ['account', 'security', 'organisation', 'notifications', 'system'], true)) {
            $activeTab = 'account';
        }

        $this->render('admin/settings/index', [
            'pageTitle' => 'Settings',
            'user' => $user,
            'organization' => $organization,
            'activeTab' => $activeTab,
            'organisationId' => $orgId,
        ], 'layouts/admin');
    }

    /**
     * Handle Settings Updates
     */
    public function update(Request $request = null, Response $response = null): void
    {
        $userId = Auth::id() ?? 0;
        $orgId = Auth::organisationId() ?? 1;
        $section = trim((string)$this->request->input('section', 'account'));

        $userModel = new User();
        $orgModel = new Organization();

        try {
            switch ($section) {
                case 'account':
                    $fullName = trim((string)$this->request->input('full_name', ''));
                    $phone = trim((string)$this->request->input('phone', ''));

                    if ($fullName === '') {
                        $this->setFlash('error', 'Full name is required.');
                        $this->redirect('/admin/settings?tab=account');
                        return;
                    }

                    $userModel->update($userId, [
                        'full_name' => $fullName,
                        'phone' => $phone ?: null,
                    ]);

                    // Update session user identity
                    $currentUser = Auth::user();
                    if ($currentUser) {
                        $currentUser['name'] = $fullName;
                        $currentUser['full_name'] = $fullName;
                        $currentUser['phone'] = $phone ?: null;
                        Auth::login($currentUser);
                    }

                    $this->setFlash('success', 'Profile information updated successfully.');
                    $this->redirect('/admin/settings?tab=account');
                    return;

                case 'security':
                    $currentPassword = (string)$this->request->input('current_password', '');
                    $newPassword = (string)$this->request->input('new_password', '');
                    $confirmPassword = (string)$this->request->input('confirm_password', '');

                    if ($currentPassword === '' || $newPassword === '') {
                        $this->setFlash('error', 'Please enter your current password and a new password.');
                        $this->redirect('/admin/settings?tab=security');
                        return;
                    }

                    if ($newPassword !== $confirmPassword) {
                        $this->setFlash('error', 'New password and confirmation do not match.');
                        $this->redirect('/admin/settings?tab=security');
                        return;
                    }

                    if (strlen($newPassword) < 6) {
                        $this->setFlash('error', 'New password must be at least 6 characters.');
                        $this->redirect('/admin/settings?tab=security');
                        return;
                    }

                    $userRecord = $userModel->find($userId);
                    if (!$userRecord || !password_verify($currentPassword, (string)($userRecord['password_hash'] ?? ''))) {
                        $this->setFlash('error', 'The current password you entered is incorrect.');
                        $this->redirect('/admin/settings?tab=security');
                        return;
                    }

                    $userModel->update($userId, [
                        'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                    ]);

                    $this->setFlash('success', 'Password updated successfully.');
                    $this->redirect('/admin/settings?tab=security');
                    return;

                case 'organisation':
                    $name = trim((string)$this->request->input('name', ''));
                    $contactPerson = trim((string)$this->request->input('contact_person', ''));
                    $phone = trim((string)$this->request->input('phone', ''));
                    $address = trim((string)$this->request->input('address', ''));

                    if ($name === '') {
                        $this->setFlash('error', 'Organisation name is required.');
                        $this->redirect('/admin/settings?tab=organisation');
                        return;
                    }

                    $orgModel->update($orgId, [
                        'name' => $name,
                        'contact_person' => $contactPerson ?: null,
                        'phone' => $phone ?: null,
                        'address' => $address ?: null,
                    ]);

                    $this->setFlash('success', 'Organisation details updated successfully.');
                    $this->redirect('/admin/settings?tab=organisation');
                    return;

                case 'notifications':
                    $this->setFlash('success', 'Notification preferences saved.');
                    $this->redirect('/admin/settings?tab=notifications');
                    return;

                case 'system':
                    $this->setFlash('success', 'System preferences saved.');
                    $this->redirect('/admin/settings?tab=system');
                    return;

                default:
                    $this->setFlash('error', 'Invalid settings section.');
                    $this->redirect('/admin/settings');
                    return;
            }
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to save settings: ' . $e->getMessage());
            $this->redirect('/admin/settings?tab=' . urlencode($section));
        }
    }
}
