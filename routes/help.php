<?php if (!defined('APP_BOOT') && PHP_SAPI !== 'cli') { http_response_code(404); exit; } // nur über index.php ?>
<?php

function help_page(array $p): void {
    render('help', ['page_title' => 'Hilfe & Anleitung']);
}
