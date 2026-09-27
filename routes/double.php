<?php if (!defined('APP_BOOT') && PHP_SAPI !== 'cli') { http_response_code(404); exit; } // nur über index.php ?>
<?php

function list_doubles(array $p): void {
    require_edit();
    redirect('players#tab-doppel');
}

function create_double(array $p): void {
    require_edit();
    redirect('players#tab-doppel');
}

function delete_double(array $p): void {
    require_edit();
    redirect('players#tab-doppel');
}
