<?php
$page_title = 'Fehler ' . ($code ?? 404);
$content = '<div class="text-center py-5">
  <h1 class="display-4">' . ($code ?? 404) . '</h1>
  <p class="lead">' . e($message ?? 'Ein Fehler ist aufgetreten.') . '</p>
  <a href="' . url() . '" class="btn btn-primary">Zur Startseite</a>
</div>';
require __DIR__ . '/_base.php';
