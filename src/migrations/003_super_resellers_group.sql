-- Super Resellers: grupo reseller que puede crear sub-resellers.
-- Los admins normales crean aqui sus super resellers; el super reseller
-- crea sus resellers desde el panel reseller (mismo access code).
SET @resellers_id = (SELECT MIN(`group_id`) FROM `users_groups` WHERE `is_reseller` = 1);
INSERT INTO `users_groups` (`group_name`, `is_admin`, `is_reseller`, `total_allowed_gen_trials`, `total_allowed_gen_in`, `delete_users`, `allowed_pages`, `can_delete`, `create_sub_resellers`, `reseller_client_connection_logs`, `can_view_vod`, `allow_download`, `minimum_trial_credits`, `allow_restrictions`, `allow_change_username`, `allow_change_password`, `subresellers`, `is_super_admin`)
SELECT 'Super Resellers', 0, 1, 100000, 'month', 1, '[]', 1, 1, 1, 1, 1, 0, 1, 1, 1, CONCAT('[', @resellers_id, ']'), 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `users_groups` WHERE `group_name` = 'Super Resellers');

-- El super reseller crea lineas con los mismos paquetes que los resellers:
-- se agrega el grupo nuevo a todo paquete disponible para los resellers.
SET @super_resellers_id = (SELECT `group_id` FROM `users_groups` WHERE `group_name` = 'Super Resellers' ORDER BY `group_id` LIMIT 1);
UPDATE `users_packages`
SET `groups` = JSON_ARRAY_APPEND(`groups`, '$', @super_resellers_id)
WHERE `groups` IS NOT NULL
  AND JSON_CONTAINS(`groups`, @resellers_id, '$')
  AND NOT JSON_CONTAINS(`groups`, @super_resellers_id, '$');
