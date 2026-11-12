<?php
/**
 * StreamExtractorController — herramienta de extracción de señales multimedia y radio.
 */
class StreamExtractorController extends BaseAdminController
{
    public function index()
    {
        $this->requirePermission();

        $this->setTitle('Extractor de Streams');
        $this->render('stream_extractor');
    }
}
