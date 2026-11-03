<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

$pages = [
    'inicio' => ['file' => 'inicio.php', 'title' => 'Panel principal'],
    'pacientes' => ['file' => 'pacientes.php', 'title' => 'Gestión de pacientes'],
    'infraestructura' => ['file' => 'infraestructura.php', 'title' => 'Plantas y camas'],
    'ingresos' => ['file' => 'ingresos.php', 'title' => 'Ingresos y asignación de camas'],
    'tarjetas' => ['file' => 'tarjetas.php', 'title' => 'Tarjetas de visita'],
    'catalogos' => ['file' => 'catalogos.php', 'title' => 'Médicos y diagnósticos'],
    'atencion' => ['file' => 'atencion.php', 'title' => 'Atención médica'],
    'reportes' => ['file' => 'reportes.php', 'title' => 'Reportes y estadísticas'],
    'historico' => ['file' => 'historico.php', 'title' => 'Archivo histórico'],
    'usuarios' => ['file' => 'usuarios.php', 'title' => 'Usuarios y permisos'],
];

$page = get_value('page', 'inicio');
if (!isset($pages[$page])) {
    $page = 'inicio';
}
$pageTitle = $pages[$page]['title'];
$activePage = $page;

require __DIR__ . '/includes/header.php';
require __DIR__ . '/pages/' . $pages[$page]['file'];
require __DIR__ . '/includes/footer.php';
