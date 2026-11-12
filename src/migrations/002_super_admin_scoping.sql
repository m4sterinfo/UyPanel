ALTER TABLE `users_groups` ADD COLUMN `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0;
UPDATE `users_groups` SET `is_super_admin` = 1 WHERE `group_id` = 1;
-- Super Admin / multi-tenant scoping (UyPanel):
-- 1) Flag de super admin por grupo: ve todo el panel (admins, resellers, lineas, providers, settings).
-- 2) Los demas grupos admin solo ven su propio arbol (owner_id) y no acceden a paginas sensibles.
