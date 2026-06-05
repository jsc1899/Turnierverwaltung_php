<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'Turnierverwaltung') ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= url('static/style.css') ?>">
  <link rel="icon" type="image/x-icon" href="<?= url('static/favicon.ico') ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url() ?>">
      <img src="<?= url('static/logo_unionsaxen.jpg') ?>"
           alt="Sport Union Saxen"
           height="56"
           style="border-radius:50%;border:2px solid #f0a800;">
      Turnierverwaltung
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= url() ?>">Turniere</a></li>
        <?php if (can_edit()): ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('players') ?>">Spielerregister</a></li>
        <?php endif; ?>
        <?php if (is_admin()): ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('admin/users') ?>">Benutzer</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <?php $u = current_user(); ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= url('nennung/link') ?>">
            <i class="bi bi-person-gear me-1"></i>Nennung verwalten
          </a>
        </li>
        <?php if ($u): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= e($u['username']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small"><?= e($u['email']) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= url('logout') ?>">Abmelden</a></li>
          </ul>
        </li>
        <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="<?= url('login') ?>">Anmelden</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= url('register') ?>">Registrieren</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="container pb-5">
  <?php foreach ($flashes as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
    <?= isset($f['html']) && $f['html'] ? $f['message'] : e($f['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endforeach; ?>

  <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($extra_js)): ?>
<?= $extra_js ?>
<?php endif; ?>
</body>
</html>
