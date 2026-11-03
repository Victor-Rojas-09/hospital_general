<?php
$user = current_user();
$activePage = $activePage ?? 'inicio';
$navigation = [
    ['page' => 'inicio', 'label' => 'Inicio', 'icon' => '⌂', 'roles' => ['administrador', 'recepcion', 'medico']],
    ['page' => 'pacientes', 'label' => 'Pacientes', 'icon' => '♙', 'roles' => ['administrador', 'recepcion', 'medico']],
    ['page' => 'infraestructura', 'label' => 'Plantas y camas', 'icon' => '▦', 'roles' => ['administrador', 'recepcion']],
    ['page' => 'ingresos', 'label' => 'Ingresos y camas', 'icon' => '＋', 'roles' => ['administrador', 'recepcion']],
    ['page' => 'tarjetas', 'label' => 'Tarjetas de visita', 'icon' => '▤', 'roles' => ['administrador', 'recepcion']],
    ['page' => 'catalogos', 'label' => 'Médicos y diagnósticos', 'icon' => '✚', 'roles' => ['administrador']],
    ['page' => 'atencion', 'label' => 'Atención médica', 'icon' => '⚕', 'roles' => ['administrador', 'medico']],
    ['page' => 'reportes', 'label' => 'Reportes', 'icon' => '▥', 'roles' => ['administrador', 'recepcion', 'medico']],
    ['page' => 'historico', 'label' => 'Archivo histórico', 'icon' => '🗎', 'roles' => ['administrador', 'recepcion', 'medico']],
    ['page' => 'usuarios', 'label' => 'Usuarios', 'icon' => '🅤', 'roles' => ['administrador']],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark">+</span>
            <span><strong>Hospital General</strong><small>Gestión hospitalaria</small></span>
        </a>
        <div class="sidebar-label">MENÚ PRINCIPAL</div>
        <nav class="side-nav">
            <?php foreach ($navigation as $item): ?>
                <?php if (in_array($user['role'], $item['roles'], true)): ?>
                    <a class="nav-link<?= $activePage === $item['page'] ? ' active' : '' ?>" href="dashboard.php?page=<?= e($item['page']) ?>">
                        <span class="nav-icon"><?= e($item['icon']) ?></span><?= e($item['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="user-mini"><span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span><span><strong><?= e($user['name']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></span></div>
            <form action="logout.php" method="post">
                <?= csrf_field() ?>
                <button class="logout-link" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>
    <main class="main-area">
        <header class="topbar">
            <div><span class="eyebrow">SISTEMA ADMINISTRATIVO</span><h1><?= e($pageTitle ?? 'Panel') ?></h1></div>
            <div class="topbar-date"><?= date('d/m/Y') ?></div>
        </header>
        <div class="content-area">
            <?php $notice = flash(''); if ($notice): ?>
                <div class="alert <?= e($notice['type']) ?>" role="alert"><?= e($notice['message']) ?></div>
            <?php endif; ?>
