<?php
/**
 * Superadmin Master Dashboard View
 * Displays multi-tenant metrics fetched from secure360_v2
 */
$m = $metrics ?? [
    'totalOrgs' => 0,
    'activeOrgs' => 0,
    'totalUsers' => 0,
    'totalGuards' => 0,
];
?>
<div class="page-header" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; font-weight: 700; color: #0f172a;">Superadmin Master Control Center</h1>
    <p style="font-size: 0.875rem; color: #64748b;">Global platform overview across all customer organisations.</p>
</div>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="card" style="margin-bottom:0;">
        <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Customer Organisations</div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">
            <?= e($m['totalOrgs']) ?>
        </div>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Total tenant accounts</div>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Active Tenants</div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #10b981; margin-top: 0.25rem;">
            <?= e($m['activeOrgs']) ?>
        </div>
        <div style="font-size: 0.75rem; color: #10b981; margin-top: 0.25rem;">Operating normally</div>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Platform Users</div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">
            <?= e($m['totalUsers']) ?>
        </div>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Admins &amp; Guards</div>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div style="font-size: 0.8125rem; color: #64748b; font-weight: 500;">Registered Guards</div>
        <div style="font-size: 1.875rem; font-weight: 700; color: #2563eb; margin-top: 0.25rem;">
            <?= e($m['totalGuards']) ?>
        </div>
        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Field personnel</div>
    </div>
</div>

<div class="card" style="margin-top:1.5rem;">
    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.75rem; color: #0f172a;">Live Database Connection</h3>
    <p style="color: #475569; font-size: 0.875rem; line-height: 1.6;">
        Superadmin module is wired directly to <code>organizations</code>, <code>users</code>, and <code>roles</code> tables in <code>secure360_v2</code>.
    </p>
</div>
