<?php
/**
 * LineListController — lista de líneas.
 */
class LineListController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->render('lines');
    }
}
