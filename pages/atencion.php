<?php
require_role(['administrador', 'medico']);
$pdo = db();
$error = '';

if (is_post()) {
    verify_csrf();
    $action = post_value('action');
    try {
        if ($action === 'visit') {
            $admissionId = (int) post_value('id_ingreso');
            $doctorCode = post_value('codigo_medico');
            $dateTime = post_value('fecha_hora');
            if ($admissionId < 1 || $doctorCode === '' || !valid_datetime_local($dateTime)) {
                throw new RuntimeException('Selecciona el ingreso, el médico y una fecha válida.');
            }
            $stmt = $pdo->prepare('INSERT INTO visitas_medicas (id_ingreso, codigo_medico, fecha_hora) VALUES (?, ?, ?)');
            $stmt->execute([$admissionId, $doctorCode, normalize_datetime($dateTime)]);
            flash('success', 'Visita médica registrada.');
            go('dashboard.php?page=atencion');
        }

        if ($action === 'diagnosis') {
            $admissionId = (int) post_value('id_ingreso');
            $diagnosisCode = post_value('codigo_diagnostico');
            $diagnosisDate = post_value('fecha_diagnostico');
            $visitId = post_value('id_visita');
            if ($admissionId < 1 || $diagnosisCode === '' || !valid_date($diagnosisDate)) {
                throw new RuntimeException('Selecciona el ingreso, el diagnóstico y una fecha válida.');
            }
            $visitValue = $visitId !== '' ? (int) $visitId : null;
            if ($visitValue !== null) {
                $visitCheck = $pdo->prepare(
                    'SELECT codigo_medico FROM visitas_medicas WHERE id_visita = ? AND id_ingreso = ?'
                );
                $visitCheck->execute([$visitValue, $admissionId]);
                $visitDoctor = $visitCheck->fetchColumn();
                if (!$visitDoctor) {
                    throw new RuntimeException('La visita seleccionada no pertenece al ingreso indicado.');
                }

                $doctorDiagnosis = $pdo->prepare(
                    'SELECT 1 FROM medicos_diagnosticos WHERE codigo_medico = ? AND codigo_diagnostico = ?'
                );
                $doctorDiagnosis->execute([$visitDoctor, $diagnosisCode]);
                if (!$doctorDiagnosis->fetchColumn()) {
                    throw new RuntimeException('El médico de la visita no está relacionado con ese tipo de diagnóstico. Configura primero la relación en el catálogo médico.');
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO diagnosticos_paciente (id_ingreso, codigo_diagnostico, fecha_diagnostico, id_visita)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$admissionId, $diagnosisCode, $diagnosisDate, $visitValue]);
            flash('success', 'Diagnóstico asociado al ingreso.');
            go('dashboard.php?page=atencion');
        }

        throw new RuntimeException('Acción no reconocida.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible registrar la atención. Verifica que la visita pertenezca al ingreso seleccionado.';
    }
}

$admissions = $pdo->query(
    'SELECT i.id_ingreso, p.nombre, p.apellidos
       FROM ingresos i JOIN pacientes p ON p.id_paciente = i.id_paciente
      ORDER BY p.apellidos, p.nombre'
)->fetchAll();
$doctors = $pdo->query('SELECT codigo_medico, nombre, apellidos FROM medicos ORDER BY apellidos, nombre')->fetchAll();
$diagnoses = $pdo->query('SELECT codigo_diagnostico, descripcion_cifrada FROM diagnosticos ORDER BY codigo_diagnostico')->fetchAll();
$visits = $pdo->query(
    'SELECT v.id_visita, v.id_ingreso, v.fecha_hora, m.codigo_medico,
            CONCAT(m.nombre, CHAR(32), m.apellidos) AS medico,
            p.nombre AS paciente_nombre, p.apellidos AS paciente_apellidos
       FROM visitas_medicas v
       JOIN medicos m ON m.codigo_medico = v.codigo_medico
       JOIN ingresos i ON i.id_ingreso = v.id_ingreso
       JOIN pacientes p ON p.id_paciente = i.id_paciente
      ORDER BY v.fecha_hora DESC LIMIT 100'
)->fetchAll();
$patientDiagnoses = $pdo->query(
    'SELECT dp.id_registro, dp.fecha_diagnostico, dp.codigo_diagnostico,
            d.descripcion_cifrada, dp.id_ingreso, p.nombre, p.apellidos
       FROM diagnosticos_paciente dp
       JOIN diagnosticos d ON d.codigo_diagnostico = dp.codigo_diagnostico
       JOIN ingresos i ON i.id_ingreso = dp.id_ingreso
       JOIN pacientes p ON p.id_paciente = i.id_paciente
      ORDER BY dp.fecha_diagnostico DESC LIMIT 100'
)->fetchAll();
?>

<div class="page-intro">
    <div>
        <h2>Visita médica</h2>
        <p class="muted">Registra cada visita médica y los diagnósticos asociados a una estancia hospitalaria.</p>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<div class="content-grid">
    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3>Registrar visita médica</h3>
                <p class="muted">Cada interacción conserva fecha y hora.</p>
            </div>
        </div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="visit">
            <label>Paciente / ingreso
                <select name="id_ingreso" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($admissions as $admission): ?>
                    <option value="<?= e($admission['id_ingreso']) ?>"><?= e($admission['nombre'] . ' ' . $admission['apellidos'] . ' · #' . $admission['id_ingreso']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Médico
                <select name="codigo_medico" required>
                    <option value="">Selecciona</option><?php foreach ($doctors as $doctor): ?>
                    <option value="<?= e($doctor['codigo_medico']) ?>"><?= e($doctor['nombre'] . ' ' . $doctor['apellidos']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fecha y hora<input type="datetime-local" name="fecha_hora" value="<?= date('Y-m-d\TH:i') ?>" required></label>
            <button class="button primary" type="submit" <?= (!$admissions || !$doctors) ? 'disabled' : '' ?>>Guardar visita</button>
        </form>
    </section>
    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3>Asignar diagnóstico</h3>
                <p class="muted">La visita es opcional, pero si se selecciona debe pertenecer al mismo ingreso.</p>
            </div>
        </div>
        <form method="post" class="form-stack" id="diagnosis-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="diagnosis">
            <label>Paciente / ingreso
                <select name="id_ingreso" id="diagnosis-admission" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($admissions as $admission): ?>
                    <option value="<?= e($admission['id_ingreso']) ?>"><?= e($admission['nombre'] . ' ' . $admission['apellidos'] . ' · #' . $admission['id_ingreso']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Diagnóstico
                <select name="codigo_diagnostico" required>
                    <option value="">Selecciona</option>
                    <?php foreach ($diagnoses as $diagnosis): ?>
                    <option value="<?= e($diagnosis['codigo_diagnostico']) ?>"><?= e($diagnosis['codigo_diagnostico'] . ' · ' . decrypt_sensitive($diagnosis['descripcion_cifrada'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fecha de diagnóstico
                <input type="date" name="fecha_diagnostico" value="<?= date('Y-m-d') ?>" required>
            </label>
            <label>Visita médica relacionada
                <select name="id_visita" id="diagnosis-visit">
                    <option value="">Sin visita asociada</option>
                    <?php foreach ($visits as $visit): ?>
                        <option data-admission="<?= e($visit['id_ingreso']) ?>" value="<?= e($visit['id_visita']) ?>"><?= e('#' . $visit['id_visita'] . ' · ' . $visit['medico'] . ' · ' . date('d/m/Y H:i', strtotime($visit['fecha_hora']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button primary" type="submit" <?= (!$admissions || !$diagnoses) ? 'disabled' : '' ?>>Guardar diagnóstico</button>
        </form>
    </section>
</div>
<div class="two-column">
    <section class="panel">
        <div class="panel-heading"><h3>Visitas médicas recientes</h3></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Paciente</th><th>Médico</th><th>Fecha y hora</th></tr></thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                        <tr>
                            <td><?= e($visit['paciente_nombre'] . ' ' . $visit['paciente_apellidos']) ?><small>Ingreso #<?= e($visit['id_ingreso']) ?></small></td>
                            <td><?= e($visit['medico']) ?></td><td><?= e(date('d/m/Y H:i', strtotime($visit['fecha_hora']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$visits): ?>
                        <tr>
                            <td colspan="3" class="empty-cell">Sin visitas registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><h3>Diagnósticos recientes</h3></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Paciente</th><th>Diagnóstico</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($patientDiagnoses as $item): ?>
                        <tr>
                            <td><?= e($item['nombre'] . ' ' . $item['apellidos']) ?></td>
                            <td><?= e($item['codigo_diagnostico'] . ' · ' . decrypt_sensitive($item['descripcion_cifrada'])) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($item['fecha_diagnostico']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$patientDiagnoses): ?>
                        <tr>
                            <td colspan="3" class="empty-cell">Sin diagnósticos registrados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
