<?php
require_role(['administrador', 'recepcion', 'medico']);
$pdo = db();
$selectedAdmission = (int) get_value('ingreso', '0');
$search = get_value('q');
$history = $pdo->query(
    'SELECT h.*
       FROM historico_ingresos h
      ORDER BY h.fecha_alta DESC'
)->fetchAll();

if ($search !== '') {
    $normalizedSearch = strtoupper((string) preg_replace('/[\s.\-]+/u', '', $search));
    $history = array_values(array_filter(
        $history,
        static function (array $item) use ($search, $normalizedSearch): bool {
            $patientName = $item['paciente_nombre'] . ' ' . $item['paciente_apellidos'];
            $matchesName = function_exists('mb_stripos')
                ? mb_stripos($patientName, $search, 0, 'UTF-8') !== false
                : stripos($patientName, $search) !== false;
            $matchesAdmission = str_contains((string) $item['id_ingreso'], $search);
            $matchesPatient = str_contains((string) $item['id_paciente'], $search);

            if ($matchesName || $matchesAdmission || $matchesPatient) {
                return true;
            }

            // NSS y DNI están cifrados en la base de datos; se comparan descifrados de forma exacta.
            $nss = strtoupper((string) preg_replace('/[\s.\-]+/u', '', decrypt_sensitive($item['nss_cifrado'])));
            $dni = strtoupper((string) preg_replace('/[\s.\-]+/u', '', decrypt_sensitive($item['dni_cifrado'])));

            return $normalizedSearch !== ''
                && ($normalizedSearch === $nss || $normalizedSearch === $dni);
        }
    ));
}
$selected = null;
$assignments = $cards = $visits = $diagnoses = [];
if ($selectedAdmission > 0) {
    $stmt = $pdo->prepare('SELECT * FROM historico_ingresos WHERE id_ingreso = ?');
    $stmt->execute([$selectedAdmission]);
    $selected = $stmt->fetch() ?: null;
    if ($selected) {
        $stmt = $pdo->prepare('SELECT * FROM historico_asignaciones_cama WHERE id_ingreso = ? ORDER BY fecha_inicio');
        $stmt->execute([$selectedAdmission]); $assignments = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT * FROM historico_tarjetas_visita WHERE id_ingreso = ? ORDER BY numero_tarjeta');
        $stmt->execute([$selectedAdmission]); $cards = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT * FROM historico_visitas_medicas WHERE id_ingreso = ? ORDER BY fecha_hora');
        $stmt->execute([$selectedAdmission]); $visits = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT * FROM historico_diagnosticos_paciente WHERE id_ingreso = ? ORDER BY fecha_diagnostico');
        $stmt->execute([$selectedAdmission]); $diagnoses = $stmt->fetchAll();
    }
}
?>
<div class="page-intro">
    <div>
        <h2>Archivos históricos de pacientes</h2>
        <p class="muted">Consulta los ingresos dados de alta y los datos trasladados desde el módulo operativo.</p>
    </div>
    <span class="count-pill"><?= count($history) ?> ingresos archivados</span>
</div>

<?php if ($selected): ?>
<section class="panel" id="detalle-ingreso">
    <div class="panel-heading">
        <div>
            <h3>Detalle del ingreso #<?= e($selected['id_ingreso']) ?></h3>
            <p class="muted"><?= e($selected['paciente_nombre'] . ' ' . $selected['paciente_apellidos']) ?> · NSS <?= e(decrypt_sensitive($selected['nss_cifrado'])) ?> · DNI <?= e(decrypt_sensitive($selected['dni_cifrado'])) ?></p>
        </div>
        <a class="text-link" href="dashboard.php?page=historico<?= $search !== '' ? '&amp;q=' . rawurlencode($search) : '' ?>">Cerrar detalle</a>
    </div>

    <div class="detail-grid">
        <div class="detail-box">
            <span>Fecha de ingreso</span>
            <strong><?= e(date('d/m/Y H:i', strtotime($selected['fecha_ingreso']))) ?></strong>
        </div>
        <div class="detail-box">
            <span>Fecha de alta</span>
            <strong><?= e(date('d/m/Y H:i', strtotime($selected['fecha_alta']))) ?></strong>
        </div>
        <div class="detail-box">
            <span>Asignaciones</span>
            <strong><?= count($assignments) ?></strong>
        </div>
        <div class="detail-box">
            <span>Visitas médicas</span>
            <strong><?= count($visits) ?></strong>
        </div>
    </div>

    <div class="two-column history-columns">
        <div>
            <h4>Recorrido por camas</h4>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Planta / cama</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $item): ?>
                            <tr>
                                <td><?= e($item['numero_planta'] . ' · ' . $item['nombre_planta'] . ' / ' . $item['numero_cama']) ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime($item['fecha_inicio']))) ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime($item['fecha_fin']))) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$assignments): ?>
                            <tr>
                                <td colspan="3" class="empty-cell">Sin asignaciones.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h4>Tarjetas de visita</h4>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Horario</th>
                            <th>Estado al alta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cards as $item): ?>
                            <tr>
                                <td>#<?= e($item['numero_tarjeta']) ?></td>
                                <td><?= e(substr($item['hora_inicio'], 0, 5) . ' – ' . substr($item['hora_fin'], 0, 5)) ?></td>
                                <td><?= $item['activa'] ? 'Activa' : 'Inactiva' ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$cards): ?>
                            <tr>
                                <td colspan="3" class="empty-cell">Sin tarjetas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h4>Visitas médicas</h4>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Médico</th>
                            <th>Especialidad</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $item): ?>
                            <tr>
                                <td><?= e($item['medico_nombre']) ?></td>
                                <td><?= e($item['especialidad'] ?: '—') ?></td>
                                <td><?= e(date('d/m/Y H:i', strtotime($item['fecha_hora']))) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$visits): ?>
                            <tr>
                                <td colspan="3" class="empty-cell">Sin visitas.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <h4>Diagnósticos</h4>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnoses as $item): ?>
                            <tr>
                                <td><?= e($item['codigo_diagnostico']) ?></td>
                                <td><?= e(decrypt_sensitive($item['descripcion_cifrada'])) ?></td>
                                <td><?= e(date('d/m/Y', strtotime($item['fecha_diagnostico']))) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$diagnoses): ?>
                            <tr>
                                <td colspan="3" class="empty-cell">Sin diagnósticos.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h3>Ingresos archivados</h3>
            <p class="muted">Selecciona un ingreso para consultar sus detalles.</p>
        </div>
    </div>
    <form method="get" class="search-form">
        <input type="hidden" name="page" value="historico">
        <input type="search" name="q" placeholder="Nombre, NSS, DNI, ID de paciente o ingreso..." value="<?= e($search) ?>">
        <button class="button secondary" type="submit">Buscar</button>
        <?php if ($search !== ''): ?>
            <a class="text-link" href="dashboard.php?page=historico">Limpiar</a>
        <?php endif; ?>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Ingreso</th>
                    <th>Alta</th>
                    <th>Datos archivados</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['paciente_nombre'] . ' ' . $item['paciente_apellidos']) ?></strong>
                            <small>ID paciente <?= e($item['id_paciente']) ?></small>
                        </td>
                        <td>
                            #<?= e($item['id_ingreso']) ?>
                            <small><?= e(date('d/m/Y H:i', strtotime($item['fecha_ingreso']))) ?></small>
                        </td>
                        <td><?= e(date('d/m/Y H:i', strtotime($item['fecha_alta']))) ?></td>
                        <td><a class="text-link" href="dashboard.php?page=historico&amp;ingreso=<?= e($item['id_ingreso']) ?><?= $search !== '' ? '&amp;q=' . rawurlencode($search) : '' ?>#detalle-ingreso">Ver detalle ↓</a></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$history): ?>
                    <tr>
                        <td colspan="4" class="empty-cell"><?= $search !== '' ? 'No se encontraron ingresos con ese criterio.' : 'Todavía no hay ingresos archivados. El alta se realiza desde Ingresos y camas.' ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($selectedAdmission > 0 && !$selected): ?>
    <div class="alert error">No se encontró ese ingreso en el archivo histórico.</div>
<?php endif; ?>