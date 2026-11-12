<?php if (!isset($__viewMode)): ?>
<?php
    include 'session.php';
    include 'functions.php';

    if (!checkPermissions()) {
        goHome();
    }

    $_TITLE = 'Extractor de Streams';
    require_once __DIR__ . '/../layouts/admin.php';
    renderUnifiedLayoutHeader('admin');
endif;
?>
<div class="wrapper boxed-layout-ext"
    <?php if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo ' style="display: none;"';
    } ?>>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-secondary waves-effect" onclick="reloadExtractorFrame();">
                                <i class="mdi mdi-refresh mr-1"></i> <?= $language::get('refresh') ?: 'Recargar'; ?>
                            </button>
                            <a href="/stream_extractor/" target="_blank" rel="noopener" class="btn btn-sm btn-primary waves-effect waves-light">
                                <i class="mdi mdi-open-in-new mr-1"></i> Abrir en ventana completa
                            </a>
                        </div>
                    </div>
                    <h4 class="page-title"><?= $language::get('stream_extractor') ?: 'Extractor de Streams'; ?></h4>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm" style="border-radius: 8px; overflow: hidden; margin-bottom: 24px;">
                    <div class="card-body p-0">
                        <iframe id="extractorFrame"
                                src="/stream_extractor/"
                                style="width: 100%; height: calc(100vh - 210px); min-height: 820px; border: none; display: block;"
                                allow="autoplay; encrypted-media; fullscreen; clipboard-read; clipboard-write">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function reloadExtractorFrame() {
    var iframe = document.getElementById('extractorFrame');
    if (iframe) {
        iframe.src = iframe.src;
    }
}
</script>
<?php
if (function_exists('renderUnifiedLayoutFooter')) {
    renderUnifiedLayoutFooter('admin');
}
?>
