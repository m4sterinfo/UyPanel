<?php

/**
 * GroupController — Groups (admin/groups.php).
 *
 * Listado de grupos de usuarios con edit/delete (comprueba hasPermissions).
 *
 * Legacy: admin/groups.php (240 líneas)
 * Route:  GET /admin/groups → index()
 */
class GroupController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();

        $this->setTitle('Groups');

        $groups = GroupService::getAll();

        $this->render('groups', [
            'groups' => $groups,
        ]);
    }
}
