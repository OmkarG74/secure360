<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Multi-Tenant Isolation Middleware
 * Ensures the authenticated user is scoped to a valid, active organisation tenant
 */
class TenantMiddleware
{
    public function handle(Request $request, Response $response): void
    {
        // Superadmin operates globally across all tenants
        if (Auth::role() === ROLE_SUPERADMIN) {
            return;
        }

        $orgId = Auth::organisationId();

        if (empty($orgId)) {
            $response->setStatusCode(403)->html(
                "<!DOCTYPE html><html><head><title>Tenant Error - Secure360</title></head><body style=\"font-family: sans-serif; padding: 3rem; text-align: center;\"><h1>Tenant Error</h1><p>Your account is not assigned to an active organisation.</p></body></html>"
            );
            return;
        }

        // Verify Organisation Status
        $db = \App\Core\Database::getConnection();
        $stmt = $db->prepare("SELECT id, status, name FROM organizations WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $orgId]);
        $org = $stmt->fetch();

        if (!$org || (int)$org['status'] !== 0) {
            $response->setStatusCode(403)->html(
                "<!DOCTYPE html><html><head><title>Organisation Suspended - Secure360</title><style>body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f8fafc; color: #0f172a; } .card { background: #fff; padding: 2.5rem; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 480px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); } h1 { font-size: 1.25rem; color: #b45309; margin-bottom: 0.75rem; } p { font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem; } a { display: inline-block; padding: 0.5rem 1.25rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 0.875rem; }</style></head><body><div class=\"card\"><h1>Organisation Suspended</h1><p>Your organisation is currently suspended. Please contact the administrator.</p><a href=\"" . url('/logout') . "\">Log Out</a></div></body></html>"
            );
            return;
        }

        // Verify Individual User Status
        $userId = Auth::id();
        if ($userId) {
            $userStmt = $db->prepare("SELECT id, status FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1");
            $userStmt->execute(['id' => $userId]);
            $userRec = $userStmt->fetch();

            if (!$userRec || (int)$userRec['status'] !== STATUS_ACTIVE) {
                Auth::logout();
                $response->redirect('/login?error=account_deactivated');
                return;
            }
        }

        // Check Subscription Status & Expiry
        $subService = new \App\Services\SubscriptionService();
        $sub = (new \App\Models\Subscription())->findCurrentByOrganization($orgId);

        if ($sub) {
            $dynamicStatus = (new \App\Models\Subscription())->calculateDynamicStatus($sub);

            if ($dynamicStatus === 'suspended') {
                $response->setStatusCode(403)->html(
                    "<!DOCTYPE html><html><head><title>Subscription Suspended - Secure360</title><style>body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f8fafc; color: #0f172a; } .card { background: #fff; padding: 2.5rem; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 480px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); } h1 { font-size: 1.25rem; color: #b45309; margin-bottom: 0.75rem; } p { font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1.5rem; } a { display: inline-block; padding: 0.5rem 1.25rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 0.875rem; }</style></head><body><div class=\"card\"><h1>Subscription Suspended</h1><p>Your organisation's subscription is currently suspended. Please contact the administrator.</p><a href=\"" . url('/logout') . "\">Log Out</a></div></body></html>"
                );
                return;
            }

            if ($dynamicStatus === 'expired') {
                $uri = $request->getUri();
                $method = $request->getMethod();

                // Disallow creating/mutating guards, contracts, clients, sites when subscription is expired
                $restrictedMutations = [
                    '/admin/guards/setup',
                    '/admin/guards',
                    '/admin/contracts/create',
                    '/admin/contracts',
                    '/admin/clients/register',
                ];

                $isRestricted = false;
                foreach ($restrictedMutations as $path) {
                    if (str_contains($uri, $path)) {
                        $isRestricted = true;
                        break;
                    }
                }

                if ($method === 'POST' || $isRestricted) {
                    // Allow billing and notification actions even if expired
                    if (!str_contains($uri, '/admin/billing') && !str_contains($uri, '/admin/notifications')) {
                        \App\Core\Session::setFlash('error', 'Subscription expired. Adding guards, activating guards, and creating contracts are restricted until renewed.');
                        $response->redirect('/admin/billing');
                        return;
                    }
                }
            }
        }
    }
}
