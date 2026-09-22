-- =============================================================
-- Migration 002: Per-Organisation Unique Codes
-- Purpose : employee_code and site_code must be unique per
--           organisation, not globally across the entire table.
--           Drops global unique keys and replaces them with
--           composite unique keys scoped to organization_id.
-- Date    : 2026-09-21
-- =============================================================

-- -----------------------------------------------------------------
-- users.employee_code  →  unique per organisation
-- -----------------------------------------------------------------
ALTER TABLE `users`
    DROP INDEX  `uq_users_employee_code`,
    ADD  UNIQUE KEY `uq_users_org_employee_code` (`organization_id`, `employee_code`);

-- -----------------------------------------------------------------
-- sites.site_code  →  unique per organisation
-- -----------------------------------------------------------------
ALTER TABLE `sites`
    DROP INDEX  `uq_sites_site_code`,
    ADD  UNIQUE KEY `uq_sites_org_site_code` (`organization_id`, `site_code`);
