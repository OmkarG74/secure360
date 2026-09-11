<?php

declare(strict_types=1);

/**
 * Role-Based Access Control (RBAC) Permissions Configuration
 */

return [
    'roles' => [
        ROLE_SUPERADMIN => [
            'description' => 'System Superadministrator (Full multi-tenant authority)',
            'permissions' => [
                'superadmin.dashboard',
                'organisations.view',
                'organisations.create',
                'organisations.edit',
                'organisations.status',
                'superadmin.settings',
                'audit_logs.view_all',
            ],
        ],
        ROLE_ADMIN => [
            'description' => 'Organisation Administrator (Tenant operations manager)',
            'permissions' => [
                'admin.dashboard',
                'clients.view',
                'clients.create',
                'clients.edit',
                'clients.delete',
                'sites.view',
                'sites.create',
                'sites.edit',
                'sites.delete',
                'guards.view',
                'guards.create',
                'guards.edit',
                'guards.status',
                'contracts.view',
                'contracts.create',
                'contracts.edit',
                'attendance.view',
                'attendance.manage',
                'admin.settings',
            ],
        ],
        ROLE_GUARD => [
            'description' => 'Field Guard (Mobile App API user)',
            'permissions' => [
                'guard.api.auth',
                'guard.api.profile',
                'guard.api.assignments',
                'guard.api.attendance',
                'guard.api.sites',
            ],
        ],
    ],
];
