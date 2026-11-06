<?php
require_role(['administrador', 'recepcion', 'medico']);

$pdo = db();
$patientCount = (int) $pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn();
$admissionCount = (int) $pdo->query('SELECT COUNT(*) FROM ingresos')->fetchColumn();
$occupiedCount = (int) $pdo->query('SELECT COUNT(*) FROM ocupaciones_cama')->fetchColumn();
$bedCount = (int) $pdo->query('SELECT COUNT(*) FROM camas')->fetchColumn();
$availableCount = max(0, $bedCount - $occupiedCount);
$plantStats = $pdo->query(
    'SELECT p.numero_planta, p.nombre, COUNT(c.numero_cama) AS total_camas,
            COUNT(o.numero_cama) AS ocupadas,
            COUNT(c.numero_cama) - COUNT(o.numero_cama) AS disponibles
       FROM plantas p
       LEFT JOIN camas c ON c.numero_planta = p.numero_planta
       LEFT JOIN ocupaciones_cama o ON o.numero_planta = c.numero_planta AND o.numero_cama = c.numero_cama
      GROUP BY p.numero_planta, p.nombre
      ORDER BY p.numero_planta'
)->fetchAll();
$recent = $pdo->query(
    'SELECT i.id_ingreso, i.fecha_ingreso, p.nombre, p.apellidos,
            oc.numero_planta, oc.numero_cama
       FROM ingresos i
       JOIN pacientes p ON p.id_paciente = i.id_paciente
       LEFT JOIN ocupaciones_cama oc ON oc.id_ingreso = i.id_ingreso
      ORDER BY i.fecha_ingreso DESC LIMIT 6'
)->fetchAll();
?>

<section class="welcome-row">
    <div>
        <p class="eyebrow">RESUMEN GENERAL</p>
        <h2>Bienvenido, <?= e(current_user()['name']) ?></h2>
        <p class="muted">Este panel resume la actividad hospitalaria registrada en el sistema.</p>
    </div>
    <?php if (has_role(['administrador', 'recepcion'])): ?>
        <a class="button primary" href="dashboard.php?page=ingresos">+ Registrar ingreso</a>
    <?php endif; ?>
</section>

<section class="stat-grid">
    <article class="stat-card">
        <span class="stat-icon blue">♙</span>
        <div>
            <span class="stat-label">Pacientes registrados</span>
            <strong><?= $patientCount ?></strong>
        </div>
    </article>
    <article class="stat-card">
        <span class="stat-icon purple">＋</span>
        <div>
            <span class="stat-label">Ingresos activos</span>
            <strong><?= $admissionCount ?></strong>
        </div>
    </article>
    <article class="stat-card">
        <span class="stat-icon orange">▦</span>
        <div>
            <span class="stat-label">Camas ocupadas</span>
            <strong><?= $occupiedCount ?></strong>
        </div>
    </article>
    <article class="stat-card">
        <span class="stat-icon green">✓</span>
        <div>
            <span class="stat-label">Camas disponibles</span>
            <strong><?= $availableCount ?></strong>
        </div>
    </article>
</section>

<div class="two-column">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h3>Ocupación por planta</h3>
                <p class="muted">Estado actual de las camas registradas.</p>
            </div>
            <a href="dashboard.php?page=reportes" class="text-link">Ver reporte</a>
        </div>

        <?php if (!$plantStats): ?>
            <p class="empty-state">Todavía no hay plantas registradas.</p>
        <?php else: ?>
            <div class="plant-list">
                <?php foreach ($plantStats as $plant): ?>
                    <?php $percent = (int) $plant['total_camas'] > 0 ? round(((int) $plant['ocupadas'] / (int) $plant['total_camas']) * 100) : 0; ?>
                    <div class="plant-item">
                        <div class="plant-info">
                            <strong>Planta <?= e($plant['numero_planta']) ?> · <?= e($plant['nombre']) ?></strong>
                            <span><?= e($plant['ocupadas']) ?> ocupadas / <?= e($plant['total_camas']) ?> camas</span>
                        </div>
                        <div class="progress-track">
                            <span style="width: <?= $percent ?>%"></span>
                        </div>
                        <small><?= $percent ?>% · <?= e($plant['disponibles']) ?> disponibles</small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h3>Ingresos recientes</h3>
                <p class="muted">Últimos pacientes admitidos.</p>
            </div>
            <a href="dashboard.php?page=ingresos" class="text-link">Ver todos</a>
        </div>

        <?php if (!$recent): ?>
            <p class="empty-state">No hay ingresos activos.</p>
        <?php else: ?>
            <div class="simple-list">
                <?php foreach ($recent as $item): ?>
                    <div class="simple-list-item">
                        <span class="avatar soft"><?= e(strtoupper(substr($item['nombre'], 0, 1))) ?></span>
                        <div class="grow">
                            <strong><?= e($item['nombre'] . ' ' . $item['apellidos']) ?></strong>
                            <small>Ingreso #<?= e($item['id_ingreso']) ?> · <?= e(date('d/m/Y H:i', strtotime($item['fecha_ingreso']))) ?></small>
                        </div>
                        <span class="tag"><?= $item['numero_planta'] === null ? 'Sin cama' : 'P' . e($item['numero_planta']) . ' / C' . e($item['numero_cama']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="notice-box">
    <strong>Recordatorio:</strong> utiliza datos ficticios en este entorno académico. El sistema cifra NSS, DNI y descripciones de diagnósticos antes de almacenarlos.
</div>