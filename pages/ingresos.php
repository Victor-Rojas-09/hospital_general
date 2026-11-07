<?php
require_role(['administrador', 'recepcion']);
$pdo = db();
$error = '';

if (is_post()) {
    verify_csrf();
    $action = post_value('action');

    try {
        if ($action === 'create') {
            $patientId = (int) post_value('id_paciente');
            $bedParts = explode(':', post_value('cama_compuesta'), 2);
            $plant = isset($bedParts[0]) ? filter_var($bedParts[0], FILTER_VALIDATE_INT) : false;
            $bed = isset($bedParts[1]) ? filter_var($bedParts[1], FILTER_VALIDATE_INT) : false;
            $admissionLocal = post_value('fecha_ingreso');

            if ($patientId < 1 || $plant === false || $plant < 0 || $bed === false || $bed < 1 || !valid_datetime_local($admissionLocal) || strtotime($admissionLocal) > time()) {
                throw new RuntimeException('Completa los datos del ingreso y selecciona una fecha válida.');
            }

            $pdo->beginTransaction();
            $patientLock = $pdo->prepare('SELECT id_paciente FROM pacientes WHERE id_paciente = ? FOR UPDATE');
            $patientLock->execute([$patientId]);
            if (!$patientLock->fetch()) {
                throw new RuntimeException('El paciente seleccionado no existe.');
            }

            $activeCheck = $pdo->prepare('SELECT id_ingreso FROM ingresos WHERE id_paciente = ?');
            $activeCheck->execute([$patientId]);
            if ($activeCheck->fetch()) {
                throw new RuntimeException('El paciente ya tiene un ingreso activo.');
            }

            $bedLock = $pdo->prepare('SELECT numero_cama FROM camas WHERE numero_planta = ? AND numero_cama = ? FOR UPDATE');
            $bedLock->execute([$plant, $bed]);
            if (!$bedLock->fetch()) {
                throw new RuntimeException('La cama seleccionada no existe.');
            }

            $occupied = $pdo->prepare('SELECT id_ingreso FROM ocupaciones_cama WHERE numero_planta = ? AND numero_cama = ?');
            $occupied->execute([$plant, $bed]);
            if ($occupied->fetch()) {
                throw new RuntimeException('La cama ya está ocupada. Selecciona otra cama disponible.');
            }

            $insertAdmission = $pdo->prepare('INSERT INTO ingresos (id_paciente, fecha_ingreso) VALUES (?, ?)');
            $insertAdmission->execute([$patientId, normalize_datetime($admissionLocal)]);
            $admissionId = (int) $pdo->lastInsertId();

            $insertAssignment = $pdo->prepare(
                'INSERT INTO asignaciones_cama (id_ingreso, numero_planta, numero_cama, fecha_inicio)
                 VALUES (?, ?, ?, ?)'
            );
            $insertAssignment->execute([$admissionId, $plant, $bed, normalize_datetime($admissionLocal)]);
            $assignmentId = (int) $pdo->lastInsertId();

            $insertOccupancy = $pdo->prepare(
                'INSERT INTO ocupaciones_cama (numero_planta, numero_cama, id_ingreso, id_asignacion)
                 VALUES (?, ?, ?, ?)'
            );
            $insertOccupancy->execute([$plant, $bed, $admissionId, $assignmentId]);
            $pdo->commit();
            flash('success', 'Ingreso registrado y cama asignada correctamente.');
            go('dashboard.php?page=ingresos');
        }

        if ($action === 'move') {
            $admissionId = (int) post_value('id_ingreso');
            $bedParts = explode(':', post_value('cama_compuesta'), 2);
            $plant = isset($bedParts[0]) ? filter_var($bedParts[0], FILTER_VALIDATE_INT) : false;
            $bed = isset($bedParts[1]) ? filter_var($bedParts[1], FILTER_VALIDATE_INT) : false;
            $moveLocal = post_value('fecha_movimiento');
            if ($admissionId < 1 || $plant === false || $plant < 0 || $bed === false || $bed < 1 || !valid_datetime_local($moveLocal) || strtotime($moveLocal) > time()) {
                throw new RuntimeException('Completa los datos del cambio de cama.');
            }

            $pdo->beginTransaction();
            $admissionLock = $pdo->prepare(
                'SELECT id_ingreso, fecha_ingreso FROM ingresos WHERE id_ingreso = ? FOR UPDATE'
            );
            $admissionLock->execute([$admissionId]);
            $activeAdmission = $admissionLock->fetch();
            if (!$activeAdmission) {
                throw new RuntimeException('El ingreso ya no está activo.');
            }

            $bedLock = $pdo->prepare('SELECT numero_cama FROM camas WHERE numero_planta = ? AND numero_cama = ? FOR UPDATE');
            $bedLock->execute([$plant, $bed]);
            if (!$bedLock->fetch()) {
                throw new RuntimeException('La cama seleccionada no existe.');
            }

            $occupied = $pdo->prepare('SELECT id_ingreso FROM ocupaciones_cama WHERE numero_planta = ? AND numero_cama = ?');
            $occupied->execute([$plant, $bed]);
            $occupant = $occupied->fetch();
            if ($occupant && (int) $occupant['id_ingreso'] !== $admissionId) {
                throw new RuntimeException('La cama seleccionada está ocupada por otro paciente.');
            }

            $current = $pdo->prepare(
                'SELECT o.id_asignacion, o.numero_planta, o.numero_cama, a.fecha_inicio
                   FROM ocupaciones_cama o
                   JOIN asignaciones_cama a ON a.id_asignacion = o.id_asignacion
                  WHERE o.id_ingreso = ? FOR UPDATE'
            );
            $current->execute([$admissionId]);
            $currentBed = $current->fetch();
            $moveAt = normalize_datetime($moveLocal);
            if (!$currentBed) {
                if (strtotime($moveAt) < strtotime($activeAdmission['fecha_ingreso'])) {
                    throw new RuntimeException('La fecha de asignación no puede ser anterior al ingreso hospitalario.');
                }

                $insertAssignment = $pdo->prepare(
                    'INSERT INTO asignaciones_cama (id_ingreso, numero_planta, numero_cama, fecha_inicio)
                     VALUES (?, ?, ?, ?)'
                );
                $insertAssignment->execute([$admissionId, $plant, $bed, $moveAt]);
                $newAssignmentId = (int) $pdo->lastInsertId();

                $insertOccupancy = $pdo->prepare(
                    'INSERT INTO ocupaciones_cama (numero_planta, numero_cama, id_ingreso, id_asignacion)
                     VALUES (?, ?, ?, ?)'
                );
                $insertOccupancy->execute([$plant, $bed, $admissionId, $newAssignmentId]);

                $pdo->commit();
                flash('success', 'Cama asignada correctamente al paciente activo.');
                go('dashboard.php?page=ingresos');
            }

            if ((int) $currentBed['numero_planta'] === $plant && (int) $currentBed['numero_cama'] === $bed) {
                throw new RuntimeException('El paciente ya está asignado a esa cama.');
            }

            if (strtotime($moveAt) < strtotime($currentBed['fecha_inicio'])) {
                throw new RuntimeException('La fecha del movimiento no puede ser anterior a la asignación actual.');
            }
            $finishOld = $pdo->prepare('UPDATE asignaciones_cama SET fecha_fin = ? WHERE id_asignacion = ? AND fecha_fin IS NULL');
            $finishOld->execute([$moveAt, $currentBed['id_asignacion']]);
            if ($finishOld->rowCount() !== 1) {
                throw new RuntimeException('No se pudo cerrar la asignación anterior.');
            }
            $deleteCurrent = $pdo->prepare('DELETE FROM ocupaciones_cama WHERE id_ingreso = ?');
            $deleteCurrent->execute([$admissionId]);

            $insertAssignment = $pdo->prepare(
                'INSERT INTO asignaciones_cama (id_ingreso, numero_planta, numero_cama, fecha_inicio)
                 VALUES (?, ?, ?, ?)'
            );
            $insertAssignment->execute([$admissionId, $plant, $bed, $moveAt]);
            $newAssignmentId = (int) $pdo->lastInsertId();
            $insertOccupancy = $pdo->prepare(
                'INSERT INTO ocupaciones_cama (numero_planta, numero_cama, id_ingreso, id_asignacion)
                 VALUES (?, ?, ?, ?)'
            );
            $insertOccupancy->execute([$plant, $bed, $admissionId, $newAssignmentId]);

            $pdo->commit();
            flash('success', 'Cambio de cama registrado. El movimiento anterior quedó en el historial.');
            go('dashboard.php?page=ingresos');
        }

        if ($action === 'discharge') {
            $admissionId = (int) post_value('id_ingreso');

            if ($admissionId < 1) {
                throw new RuntimeException('Ingreso no válido.');
            }

            $pdo->beginTransaction();

            // Disabled visit cards
            $updateCards = $pdo->prepare(
                'UPDATE tarjetas_visita SET activa = 0 WHERE id_ingreso = ?'
            );
            $updateCards->execute([$admissionId]);

            // Post Receipt
            $archive = $pdo->prepare('CALL sp_archivar_ingreso(?)');
            $archive->execute([$admissionId]);

            while ($archive->nextRowset()) {
                // Clean Results
            }
            $archive->closeCursor();

            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            flash('success', 'Alta registrada. El ingreso y sus detalles fueron trasladados al archivo histórico.');
            go('dashboard.php?page=ingresos');
        }

        throw new RuntimeException('Acción no reconocida.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'No fue posible completar la operación. Verifica la disponibilidad de la cama y los datos.';
    }
}

$patients = $pdo->query(
    "SELECT p.id_paciente, p.nombre, p.apellidos
     FROM pacientes p
     WHERE NOT EXISTS (
         SELECT 1
         FROM ingresos i
         WHERE i.id_paciente = p.id_paciente
     )
     AND NOT EXISTS (
         SELECT 1
         FROM historico_ingresos hi
         WHERE hi.id_paciente = p.id_paciente
     )
     ORDER BY p.apellidos, p.nombre"
)->fetchAll();
$freeBeds = $pdo->query(
    'SELECT c.numero_planta, c.numero_cama, p.nombre AS planta_nombre
       FROM camas c JOIN plantas p ON p.numero_planta = c.numero_planta
       LEFT JOIN ocupaciones_cama o ON o.numero_planta = c.numero_planta AND o.numero_cama = c.numero_cama
      WHERE o.numero_planta IS NULL
      ORDER BY c.numero_planta, c.numero_cama'
)->fetchAll();
$activeAdmissions = $pdo->query(
    'SELECT i.id_ingreso, i.fecha_ingreso, p.id_paciente, p.nombre, p.apellidos,
            oc.numero_planta, oc.numero_cama, pl.nombre AS planta_nombre
       FROM ingresos i
       JOIN pacientes p ON p.id_paciente = i.id_paciente
       LEFT JOIN ocupaciones_cama oc ON oc.id_ingreso = i.id_ingreso
       LEFT JOIN plantas pl ON pl.numero_planta = oc.numero_planta
      ORDER BY i.fecha_ingreso DESC'
)->fetchAll();

// Search only current admissions; archived stays are not eligible for bed changes.
$activeSearch = get_value('buscar_activo');
if ($activeSearch !== '') {
    $stmt = $pdo->prepare(
        'SELECT i.id_ingreso, i.fecha_ingreso, p.id_paciente, p.nombre, p.apellidos,
                oc.numero_planta, oc.numero_cama, pl.nombre AS planta_nombre
           FROM ingresos i
           JOIN pacientes p ON p.id_paciente = i.id_paciente
           LEFT JOIN ocupaciones_cama oc ON oc.id_ingreso = i.id_ingreso
           LEFT JOIN plantas pl ON pl.numero_planta = oc.numero_planta
          WHERE p.nombre LIKE :name
             OR p.apellidos LIKE :surname
             OR CONCAT(p.nombre, CHAR(32), p.apellidos) LIKE :full_name
             OR CAST(i.id_ingreso AS CHAR) = :admission_id
             OR CAST(p.id_paciente AS CHAR) = :patient_id
          ORDER BY i.fecha_ingreso DESC'
    );
    $stmt->execute([
        'name' => '%' . $activeSearch . '%',
        'surname' => '%' . $activeSearch . '%',
        'full_name' => '%' . $activeSearch . '%',
        'admission_id' => $activeSearch,
        'patient_id' => $activeSearch,
    ]);
    $matchingAdmissions = $stmt->fetchAll();
} else {
    $matchingAdmissions = $activeAdmissions;
}

$plants = $pdo->query('SELECT numero_planta, nombre FROM plantas ORDER BY numero_planta')->fetchAll();
?>

<div class="page-intro">
    <div>
        <h2>Admisión y ubicación</h2>
        <p class="muted">Registra ingresos, cambia pacientes de cama y procesa el alta hospitalaria.</p>
    </div>
    <span class="count-pill"><?= count($activeAdmissions) ?> ingresos activos</span>
</div>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<div class="content-grid">
    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3>Nuevo ingreso</h3>
                <p class="muted">El paciente y la cama quedan asociados en una transacción.</p>
            </div>
        </div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <label>Paciente
                <select name="id_paciente" required>
                    <option value="">Selecciona un paciente</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?= e($patient['id_paciente']) ?>"><?= e($patient['apellidos'] . ', ' . $patient['nombre']) ?> (ID <?= e($patient['id_paciente']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Cama disponible
                <select name="cama_compuesta" id="new-bed-select" required>
                    <option value="">Selecciona planta y cama</option>
                    <?php foreach ($freeBeds as $bed): ?>
                        <option value="<?= e($bed['numero_planta'] . ':' . $bed['numero_cama']) ?>"><?= e('Planta ' . $bed['numero_planta'] . ' · ' . $bed['planta_nombre'] . ' / Cama ' . $bed['numero_cama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fecha y hora de ingreso
                <input type="datetime-local" name="fecha_ingreso" value="<?= date('Y-m-d\TH:i') ?>" required>
            </label>
            <button class="button primary" type="submit" <?= (!$patients || !$freeBeds) ? 'disabled' : '' ?>>Registrar ingreso</button>
            <?php if (!$patients): ?>
                <small class="help-text">Primero registra un paciente que no tenga un ingreso activo.</small>
            <?php endif; ?>
            <?php if (!$freeBeds): ?>
                <small class="help-text">No hay camas disponibles. Registra una planta o libera una cama.</small>
            <?php endif; ?>
        </form>
    </section>

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3>Asignación de cama</h3>
                <p class="muted">Busca un paciente hospitalizado para asignarle una cama o registrar un traslado. Solo aparecen ingresos activos.</p>
            </div>
        </div>
        <form method="get" class="search-form" role="search">
            <input type="hidden" name="page" value="ingresos">
            <input type="search" name="buscar_activo" aria-label="Buscar paciente activo" placeholder="Nombre, apellidos o ID" value="<?= e($activeSearch) ?>">
            <button class="button secondary" type="submit">Buscar</button>
            <?php if ($activeSearch !== ''): ?>
                <a class="text-link" href="dashboard.php?page=ingresos">Limpiar</a>
            <?php endif; ?>
        </form>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move">
            <label>Paciente activo
                <select name="id_ingreso" required>
                    <option value="">Selecciona un ingreso</option>
                    <?php foreach ($matchingAdmissions as $admission): ?>
                        <option value="<?= e($admission['id_ingreso']) ?>"><?= e($admission['nombre'] . ' ' . $admission['apellidos'] . ' · Ingreso #' . $admission['id_ingreso']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($activeSearch !== '' && !$matchingAdmissions): ?>
                <small class="help-text">No se encontraron pacientes activos con ese criterio de búsqueda.</small>
            <?php elseif (!$matchingAdmissions): ?>
                <small class="help-text">No hay ingresos activos para asignar o cambiar de cama.</small>
            <?php endif; ?>
            <label>Nueva cama disponible
                <select name="cama_compuesta" id="move-bed-select" required>
                    <option value="">Selecciona planta y cama</option>
                    <?php foreach ($freeBeds as $bed): ?>
                        <option value="<?= e($bed['numero_planta'] . ':' . $bed['numero_cama']) ?>"><?= e('Planta ' . $bed['numero_planta'] . ' / Cama ' . $bed['numero_cama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fecha y hora de asignación / movimiento
                <input type="datetime-local" name="fecha_movimiento" value="<?= date('Y-m-d\TH:i') ?>" required>
            </label>
            <button class="button secondary" type="submit" <?= (!$matchingAdmissions || !$freeBeds) ? 'disabled' : '' ?>>Asignar / cambiar cama</button>
        </form>
    </section>
</div>

<section class="panel">
    <div class="panel-heading">
        <div>
            <h3>Pacientes hospitalizados</h3>
            <p class="muted">El alta mueve sus datos relacionados a las tablas históricas.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Ingreso</th>
                    <th>Ubicación actual</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activeAdmissions as $admission): ?>
                    <tr>
                        <td>
                            <strong><?= e($admission['nombre'] . ' ' . $admission['apellidos']) ?></strong>
                            <small>ID paciente <?= e($admission['id_paciente']) ?></small>
                        </td>
                        <td>
                            #<?= e($admission['id_ingreso']) ?>
                            <small><?= e(date('d/m/Y H:i', strtotime($admission['fecha_ingreso']))) ?></small>
                        </td>
                        <td><?= $admission['numero_planta'] === null ? 'Sin cama' : e('Planta ' . $admission['numero_planta'] . ' · ' . $admission['planta_nombre'] . ' / Cama ' . $admission['numero_cama']) ?></td>
                        <td>
                            <form method="post" class="inline-form" data-confirm="¿Dar de alta y archivar toda la información de este ingreso?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="discharge">
                                <input type="hidden" name="id_ingreso" value="<?= e($admission['id_ingreso']) ?>">
                                <button class="button danger small" type="submit">Dar de alta</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$activeAdmissions): ?>
                    <tr>
                        <td colspan="4" class="empty-cell">No hay pacientes hospitalizados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>