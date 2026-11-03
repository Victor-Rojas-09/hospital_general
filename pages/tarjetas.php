<?php
require_role(['administrador', 'recepcion']);
$pdo = db();
$error = '';

if (is_post()) {
    verify_csrf();
    $action = post_value('action');
    try {
        if ($action === 'create') {
            $admissionId = (int) post_value('id_ingreso');
            $start = post_value('hora_inicio');
            $end = post_value('hora_fin');
            if ($admissionId < 1 || $start === '' || $end === '' || $start >= $end) {
                throw new RuntimeException('Selecciona un ingreso y un horario válido: la hora de inicio debe ser anterior a la hora final.');
            }

            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id_ingreso FROM ingresos WHERE id_ingreso = ? FOR UPDATE');
            $lock->execute([$admissionId]);
            if (!$lock->fetch()) {
                throw new RuntimeException('El ingreso seleccionado ya no está activo.');
            }
            $activeSlotsStmt = $pdo->prepare(
                'SELECT numero_tarjeta FROM tarjetas_visita WHERE id_ingreso = ? AND activa = 1 FOR UPDATE'
            );
            $activeSlotsStmt->execute([$admissionId]);
            $activeSlots = array_map('intval', $activeSlotsStmt->fetchAll(PDO::FETCH_COLUMN));
            $freeSlots = array_values(array_diff([1, 2, 3, 4], $activeSlots));
            if (!$freeSlots) {
                throw new RuntimeException('Este paciente ya tiene cuatro tarjetas activas. Desactiva una antes de emitir otra.');
            }
            $slot = $freeSlots[0];

            $insert = $pdo->prepare(
                'INSERT INTO tarjetas_visita (id_ingreso, numero_tarjeta, hora_inicio, hora_fin)
                 VALUES (?, ?, ?, ?)'
            );
            $insert->execute([$admissionId, $slot, $start, $end]);
            $pdo->commit();
            flash('success', 'Tarjeta de visita emitida correctamente.');
            go('dashboard.php?page=tarjetas');
        }

        if ($action === 'deactivate') {
            $cardId = (int) post_value('id_tarjeta');
            $stmt = $pdo->prepare('UPDATE tarjetas_visita SET activa = 0 WHERE id_tarjeta = ? AND activa = 1');
            $stmt->execute([$cardId]);
            flash('success', $stmt->rowCount() ? 'Tarjeta desactivada.' : 'La tarjeta ya estaba desactivada.');
            go('dashboard.php?page=tarjetas');
        }

        throw new RuntimeException('Acción no reconocida.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible procesar la tarjeta.';
    }
}

$admissions = $pdo->query(
    'SELECT i.id_ingreso, p.nombre, p.apellidos
       FROM ingresos i JOIN pacientes p ON p.id_paciente = i.id_paciente
      ORDER BY p.apellidos, p.nombre'
)->fetchAll();
$cards = $pdo->query(
    'SELECT t.*, i.id_paciente, p.nombre, p.apellidos, i.id_ingreso
       FROM tarjetas_visita t
       JOIN ingresos i ON i.id_ingreso = t.id_ingreso
       JOIN pacientes p ON p.id_paciente = i.id_paciente
      ORDER BY t.creada_en DESC'
)->fetchAll();
?>
<div class="page-intro"><div><h2>Tarjetas de visita</h2><p class="muted">Emite pases con horarios específicos y controla el límite de cuatro tarjetas activas por paciente.</p></div><span class="count-pill"><?= count($cards) ?> tarjetas activas/registradas</span></div>
<?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="content-grid">
    <section class="panel form-panel"><div class="panel-heading"><div><h3>Emitir tarjeta</h3><p class="muted">El sistema asigna uno de los cuatro espacios disponibles (1 a 4).</p></div></div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <label>Paciente / ingreso<select name="id_ingreso" required><option value="">Selecciona un ingreso activo</option><?php foreach ($admissions as $item): ?><option value="<?= e($item['id_ingreso']) ?>"><?= e($item['nombre'] . ' ' . $item['apellidos'] . ' · Ingreso #' . $item['id_ingreso']) ?></option><?php endforeach; ?></select></label>
            <div class="form-row"><label>Hora de inicio<input type="time" name="hora_inicio" required></label><label>Hora de fin<input type="time" name="hora_fin" required></label></div>
            <button class="button primary" type="submit" <?= !$admissions ? 'disabled' : '' ?>>Emitir tarjeta</button>
            <?php if (!$admissions): ?><small class="help-text">No hay ingresos activos para asignar tarjetas.</small><?php endif; ?>
        </form>
    </section>
    <section class="panel"><div class="panel-heading"><div><h3>Tarjetas emitidas</h3><p class="muted">Las tarjetas desactivadas se conservan para trazabilidad.</p></div></div>
        <div class="table-wrap"><table><thead><tr><th>Paciente</th><th>Tarjeta</th><th>Horario</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
        <?php foreach ($cards as $card): ?>
            <tr><td><strong><?= e($card['nombre'] . ' ' . $card['apellidos']) ?></strong><small>Ingreso #<?= e($card['id_ingreso']) ?></small></td><td>#<?= e($card['numero_tarjeta']) ?></td><td><?= e(substr($card['hora_inicio'], 0, 5) . ' – ' . substr($card['hora_fin'], 0, 5)) ?></td><td><span class="tag <?= $card['activa'] ? 'success' : 'muted-tag' ?>"><?= $card['activa'] ? 'Activa' : 'Inactiva' ?></span></td><td><?php if ($card['activa']): ?><form method="post" class="inline-form" data-confirm="¿Desactivar esta tarjeta?"><?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="id_tarjeta" value="<?= e($card['id_tarjeta']) ?>"><button class="button secondary small" type="submit">Desactivar</button></form><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$cards): ?><tr><td colspan="5" class="empty-cell">No hay tarjetas emitidas.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</div>
<div class="notice-box">El límite de cuatro tarjetas activas se controla dentro de una transacción bloqueando el ingreso. La columna generada `slot_activo` y su índice único impiden duplicar un espacio activo. Al desactivar una tarjeta, el espacio queda disponible, pero el registro anterior se conserva.</div>
