<?php
/**
 * LineMassController — edición masiva de líneas.
 */
class LineMassController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();
        $this->render('line_mass');
    }
}
