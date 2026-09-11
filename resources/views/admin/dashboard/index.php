<?php
/**
 * Admin Dashboard View
 * Displays real-time operational metrics fetched from secure360_v2
 * Features Live Security Pulse matching Screenshot 1
 */
$m = $metrics ?? [
    'totalCustomers' => 0,
    'totalSites' => 0,
    'totalGuards' => 0,
    'activeContracts' => 0,
    'activeCheckIns' => 0,
];
$recentAttendance = $recentAttendance ?? [];
$recentActivities = $recentActivities ?? [];
?>

<?php
$currentUser = $user ?? auth() ?? [];
$orgName = $currentUser['organization_name'] ?? 'Apex Security Services';
$userName = $currentUser['full_name'] ?? 'Operations Admin';
$roleName = $currentUser['role_name'] ?? 'Organisation Admin';
$orgId = $currentUser['organization_id'] ?? (\App\Core\Auth::organisationId() ?? 1);
?>
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <h1 style="font-size: 1.625rem; font-weight: 700; color: #0f172a; margin: 0;"><?= e($orgName) ?></h1>
            <span style="font-size: 0.75rem; font-weight: 600; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.2rem 0.6rem; border-radius: 9999px;">
                Org #<?= e($orgId) ?>
            </span>
        </div>
        <p style="font-size: 0.875rem; color: #64748b; margin: 0;">
            Logged in as <strong><?= e($userName) ?></strong> &bull; <span style="color: #475569;"><?= e($roleName) ?></span> &bull; <code>secure360_v2</code> active
        </p>
    </div>
    
    <!-- Quick Actions -->
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <a href="<?= url('/admin/clients/register') ?>" class="btn btn-outline" style="font-size: 0.8125rem; text-decoration: none; padding: 0.5rem 0.9rem; border-color: #cbd5e1; color: #334155;">
            + Add Client
        </a>
        <a href="<?= url('/admin/guards/setup') ?>" class="btn btn-primary" style="font-size: 0.8125rem; text-decoration: none; padding: 0.5rem 1rem; font-weight: 600;">
            + Setup Guard
        </a>
    </div>
</div>

<?php App\Core\View::component('components/alerts'); ?>

<!-- Top Metrics Row -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="card" style="margin-bottom:0; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Clients / Customers</div>
            <div style="width: 32px; height: 32px; border-radius: 6px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.35rem;">
            <?= e($m['totalCustomers']) ?>
        </div>
        <a href="<?= url('/admin/clients-sites') ?>" style="font-size: 0.75rem; color: #2563eb; text-decoration: none; font-weight: 500; display: inline-block; margin-top: 0.35rem;">
            Manage Accounts &rarr;
        </a>
    </div>

    <div class="card" style="margin-bottom:0; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Protected Sites</div>
            <div style="width: 32px; height: 32px; border-radius: 6px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
        </div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.35rem;">
            <?= e($m['totalSites']) ?>
        </div>
        <span style="font-size: 0.75rem; color: #64748b; display: inline-block; margin-top: 0.35rem;">
            Active perimeter posts
        </span>
    </div>

    <div class="card" style="margin-bottom:0; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Guards Roster</div>
            <div style="width: 32px; height: 32px; border-radius: 6px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
        </div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #2563eb; margin-top: 0.35rem;">
            <?= e($m['totalGuards']) ?>
        </div>
        <a href="<?= url('/admin/guards') ?>" style="font-size: 0.75rem; color: #2563eb; text-decoration: none; font-weight: 500; display: inline-block; margin-top: 0.35rem;">
            View Personnel &rarr;
        </a>
    </div>

    <div class="card" style="margin-bottom:0; padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Active Contracts</div>
            <div style="width: 32px; height: 32px; border-radius: 6px; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.35rem;">
            <?= e($m['activeContracts']) ?>
        </div>
        <a href="<?= url('/admin/contracts') ?>" style="font-size: 0.75rem; color: #2563eb; text-decoration: none; font-weight: 500; display: inline-block; margin-top: 0.35rem;">
            Service Coverage &rarr;
        </a>
    </div>

    <div class="card" style="margin-bottom:0; padding: 1.25rem; border-color: #a7f3d0; background: #f0fdf4;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="font-size: 0.8125rem; color: #065f46; font-weight: 600;">Guards On Duty Now</div>
            <div style="width: 32px; height: 32px; border-radius: 6px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                <span style="width: 10px; height: 10px; border-radius: 50%; background: #16a34a; animation: pulse 2s infinite;"></span>
            </div>
        </div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #166534; margin-top: 0.35rem;">
            <?= e($m['activeCheckIns']) ?>
        </div>
        <span style="font-size: 0.75rem; color: #15803d; font-weight: 500; display: inline-block; margin-top: 0.35rem;">
            Live GPS telemetry
        </span>
    </div>
</div>

<!-- Operational Split Section: Live Telemetry + Recent Activity -->
<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 1.75rem; align-items: start; margin-bottom: 2rem;">
    
    <!-- Live Guard Duty Logs -->
    <div class="card" style="padding: 1.75rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
            <div>
                <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a;">Live Field Attendance</h3>
                <p style="font-size: 0.8125rem; color: #64748b;">Telemetry received from the Flutter Guard app.</p>
            </div>
            <a href="<?= url('/admin/attendance') ?>" style="font-size: 0.8125rem; color: #2563eb; text-decoration: none; font-weight: 600;">
                View All &rarr;
            </a>
        </div>

        <?php if (empty($recentAttendance)): ?>
            <div style="padding: 2.5rem 1rem; text-align: center; color: #94a3b8;">
                <p style="font-size: 0.875rem;">No active check-ins recorded today.</p>
                <p style="font-size: 0.75rem; margin-top: 0.25rem;">When guards check in on mobile, live GPS telemetry displays here.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <?php foreach ($recentAttendance as $att): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem; border: 1px solid #f1f5f9; border-radius: 8px; background: #fafafa;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8125rem;">
                                <?= strtoupper(substr($att['guard_name'] ?? 'G', 0, 2)) ?>
                            </div>
                            <div>
                                <div style="font-size: 0.875rem; font-weight: 600; color: #0f172a;">
                                    <?= e($att['guard_name']) ?> 
                                    <span style="font-size: 0.75rem; font-family: monospace; color: #64748b;">(<?= e($att['employee_code']) ?>)</span>
                                </div>
                                <div style="font-size: 0.75rem; color: #475569;">
                                    Post: <strong><?= e($att['site_name'] ?? 'General Post') ?></strong>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; <?= (int)$att['status'] === 0 ? 'background: #ecfdf5; color: #059669;' : 'background: #f1f5f9; color: #475569;' ?>">
                                <?= (int)$att['status'] === 0 ? 'On Duty' : 'Completed' ?>
                            </span>
                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">
                                In: <?= date('g:i A', strtotime($att['check_in_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- System Architecture & Security Pulse -->
    <div class="card" style="padding: 1.75rem;">
        <h3 style="font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin-bottom: 0.25rem;">Live Security Pulse</h3>
        <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 1.25rem;">Engine &amp; API synchronization health</p>

        <div style="display: flex; flex-direction: column; gap: 0.85rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                <span style="font-size: 0.8125rem; color: #334155; font-weight: 500;">Core Database</span>
                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #10b981; font-weight: 600;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> secure360_v2
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                <span style="font-size: 0.8125rem; color: #334155; font-weight: 500;">Flutter REST API</span>
                <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #10b981; font-weight: 600;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span> /api/v1 (Ready)
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                <span style="font-size: 0.8125rem; color: #334155; font-weight: 500;">Security Tokens</span>
                <span style="font-size: 0.75rem; color: #2563eb; font-weight: 600;">
                    SHA-256 Bearer
                </span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                <span style="font-size: 0.8125rem; color: #334155; font-weight: 500;">Multi-Tenancy</span>
                <span style="font-size: 0.75rem; color: #16a34a; font-weight: 600;">
                    Isolated (<?= e($orgName) ?> #<?= e($orgId) ?>)
                </span>
            </div>
        </div>

        <div style="margin-top: 1.5rem; padding: 0.85rem; border-radius: 8px; background: #eff6ff; border: 1px solid #dbeafe;">
            <p style="font-size: 0.75rem; color: #1e40af; line-height: 1.5;">
                <strong>Guard Mobile App Ready</strong>: Guards login via <code>/api/v1/auth/guard/login</code> and push live GPS &amp; selfie checks directly into this system.
            </p>
        </div>
    </div>

</div>
