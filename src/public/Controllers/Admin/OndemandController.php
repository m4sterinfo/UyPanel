<?php
/**
 * OndemandController — escáner On-Demand.
 */
class OndemandController extends BaseAdminController
{
    public function index()
    {
        $this->setTitle('On-Demand Scanner');
        $this->render('ondemand');
    }
}
