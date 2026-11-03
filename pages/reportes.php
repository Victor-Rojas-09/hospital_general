<?php
require_role(['administrador', 'recepcion', 'medico']);
$pdo = db();
$from = get_value('desde', date('Y-m-01'));
$to = get_value('hasta', date('Y-m-d'));
$specialty = get_value('especialidad');
$patientId = (int) get_value('id_paciente', '0');
if (!valid_date($from)) { $from = date('Y-m-01'); }
if (!valid_date($to)) { $to = date('Y-m-d'); }

$occupancy = $pdo->query(
    'SELECT p.numero_planta, p.nombre,
            COUNT(c.numero_cama) AS total,
            COUNT(o.numero_cama) AS ocupadas,
            COUNT(c.numero_cama) - COUNT(o.numero_cama) AS disponibles
       FROM plantas p
       LEFT JOIN camas c ON c.numero_planta = p.numero_planta
       LEFT JOIN ocupaciones_cama o ON o.numero_planta = c.numero_planta AND o.numero_cama = c.numero_cama
      GROUP BY p.numero_planta, p.nombre
      ORDER BY p.numero_planta'
)->fetchAll();

$prevalenceSql = '
    SELECT x.codigo_diagnostico, d.descripcion_cifrada, COUNT(*) AS total
      FROM (
            SELECT dp.codigo_diagnostico, dp.fecha_diagnostico, m.especialidad
              FROM diagnosticos_paciente dp
              LEFT JOIN visitas_medicas v ON v.id_visita = dp.id_visita
              LEFT JOIN medicos m ON m.codigo_medico = v.codigo_medico
            UNION ALL
            SELECT hdp.codigo_diagnostico, hdp.fecha_diagnostico, hv.especialidad
              FROM historico_diagnosticos_paciente hdp
              LEFT JOIN historico_visitas_medicas hv ON hv.id_visita = hdp.id_visita
           ) x
      JOIN diagnosticos d ON d.codigo_diagnostico = x.codigo_diagnostico
     WHERE x.fecha_diagnostico BETWEEN ? AND ?
       AND (? = \'\' OR COALESCE(x.especialidad, \'\') = ?)
     GROUP BY x.codigo_diagnostico, d.descripcion_cifrada
     ORDER BY total DESC, x.codigo_diagnostico';
$stmt = $pdo->prepare($prevalenceSql);
$stmt->execute([$from, $to, $specialty, $specialty]);
$prevalence = $stmt->fetchAll();
$specialties = $pdo->query("SELECT DISTINCT especialidad FROM medicos WHERE especialidad IS NOT NULL AND especialidad <> '' ORDER BY especialidad")->fetchAll(PDO::FETCH_COLUMN);
$patients = $pdo->query('SELECT id_paciente, nombre, apellidos FROM pacientes ORDER BY apellidos, nombre')->fetchAll();

$movement = [];
if ($patientId > 0) {
    $movementSql = '
        SELECT x.fecha_inicio, x.fecha_fin, x.numero_planta, x.nombre_planta, x.numero_cama
          FROM (
                SELECT a.fecha_inicio, a.fecha_fin, a.numero_planta, pl.nombre AS nombre_planta, a.numero_cama, i.id_paciente
                  FROM asignaciones_cama a
                  JOIN ingresos i ON i.id_ingreso = a.id_ingreso
                  JOIN plantas pl ON pl.numero_planta = a.numero_planta
                UNION ALL
                SELECT ha.fecha_inicio, ha.fecha_fin, ha.numero_planta, ha.nombre_planta, ha.numero_cama, hi.id_paciente
                  FROM historico_asignaciones_cama ha
                  JOIN historico_ingresos hi ON hi.id_ingreso = ha.id_ingreso
               ) x
         WHERE x.id_paciente = ?
         ORDER BY x.fecha_inicio';
    $stmt = $pdo->prepare($movementSql);
    $stmt->execute([$patientId]);
    $movement = $stmt->fetchAll();
}
?>
<div class="page-intro"><div><h2>Reportes hospitalarios</h2><p class="muted">Ocupación de camas, prevalencia clínica y trazabilidad de pacientes.</p></div></div>
<section class="panel"><div class="panel-heading"><div><h3>Filtros de prevalencia clínica</h3><p class="muted">Incluye diagnósticos de ingresos activos y archivados.</p></div></div>
    <form method="get" class="filter-row"><input type="hidden" name="page" value="reportes"><label>Desde<input type="date" name="desde" value="<?= e($from) ?>"></label><label>Hasta<input type="date" name="hasta" value="<?= e($to) ?>"></label><label>Especialidad<select name="especialidad"><option value="">Todas</option><?php foreach ($specialties as $item): ?><option value="<?= e($item) ?>"<?= selected($specialty, $item) ?>><?= e($item) ?></option><?php endforeach; ?></select></label><button class="button primary" type="submit">Consultar</button></form>
</section>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><div><h3>Ocupación hospitalaria</h3><p class="muted">Porcentaje de camas ocupadas y disponibles por planta.</p></div></div>
        <div class="table-wrap"><table><thead><tr><th>Planta</th><th>Total</th><th>Ocupadas</th><th>Disponibles</th><th>% ocupación</th></tr></thead><tbody>
        <?php foreach ($occupancy as $item): $percent = (int) $item['total'] > 0 ? round(((int) $item['ocupadas'] / (int) $item['total']) * 100, 1) : 0; ?><tr><td><strong><?= e($item['numero_planta']) ?> · <?= e($item['nombre']) ?></strong></td><td><?= e($item['total']) ?></td><td><?= e($item['ocupadas']) ?></td><td><?= e($item['disponibles']) ?></td><td><?= e(number_format($percent, 1, ',', '.')) ?>%</td></tr><?php endforeach; ?>
        <?php if (!$occupancy): ?><tr><td colspan="5" class="empty-cell">No hay plantas registradas.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Diagnósticos más frecuentes</h3><p class="muted">Período: <?= e(date('d/m/Y', strtotime($from))) ?> – <?= e(date('d/m/Y', strtotime($to))) ?></p></div></div>
        <div class="rank-list">
        <?php foreach ($prevalence as $index => $item): ?><div class="rank-item"><span class="rank-number"><?= $index + 1 ?></span><div class="grow"><strong><?= e(decrypt_sensitive($item['descripcion_cifrada'])) ?></strong><small><?= e($item['codigo_diagnostico']) ?></small></div><span class="count-pill"><?= e($item['total']) ?> casos</span></div><?php endforeach; ?>
        <?php if (!$prevalence): ?><p class="empty-state">No hay diagnósticos para los filtros seleccionados.</p><?php endif; ?>
        </div>
    </section>
</div>
<section class="panel"><div class="panel-heading"><div><h3>Trazabilidad de pacientes</h3><p class="muted">Recorrido por plantas y camas durante los ingresos actuales e históricos.</p></div></div>
    <form method="get" class="filter-row"><input type="hidden" name="page" value="reportes"><input type="hidden" name="desde" value="<?= e($from) ?>"><input type="hidden" name="hasta" value="<?= e($to) ?>"><input type="hidden" name="especialidad" value="<?= e($specialty) ?>"><label class="wide-label">Paciente<select name="id_paciente"><option value="0">Selecciona un paciente</option><?php foreach ($patients as $patient): ?><option value="<?= e($patient['id_paciente']) ?>"<?= selected($patientId, $patient['id_paciente']) ?>><?= e($patient['apellidos'] . ', ' . $patient['nombre']) ?></option><?php endforeach; ?></select></label><button class="button secondary" type="submit">Ver recorrido</button></form>
    <?php if ($patientId > 0): ?><div class="table-wrap"><table><thead><tr><th>Planta</th><th>Cama</th><th>Fecha de asignación</th><th>Fecha de salida</th></tr></thead><tbody><?php foreach ($movement as $item): ?><tr><td><?= e($item['numero_planta'] . ' · ' . $item['nombre_planta']) ?></td><td><?= e($item['numero_cama']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($item['fecha_inicio']))) ?></td><td><?= $item['fecha_fin'] ? e(date('d/m/Y H:i', strtotime($item['fecha_fin']))) : '<span class="tag success">Actual</span>' ?></td></tr><?php endforeach; ?><?php if (!$movement): ?><tr><td colspan="4" class="empty-cell">El paciente todavía no tiene movimientos registrados.</td></tr><?php endif; ?></tbody></table></div><?php endif; ?>
</section>
