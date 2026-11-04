<?php
require_role(['administrador']);
$pdo = db();
$error = '';

if (is_post()) {
    verify_csrf();
    $action = post_value('action');
    try {
        if ($action === 'doctor') {
            $code = post_value('codigo_medico');
            $name = post_value('nombre');
            $surname = post_value('apellidos');
            $specialty = post_value('especialidad');
            if ($code === '' || $name === '' || $surname === '') {
                throw new RuntimeException('Código, nombre y apellidos del médico son obligatorios.');
            }
            $stmt = $pdo->prepare('INSERT INTO medicos (codigo_medico, nombre, apellidos, especialidad) VALUES (?, ?, ?, ?)');
            $stmt->execute([$code, $name, $surname, $specialty !== '' ? $specialty : null]);
            flash('success', 'Médico registrado.');
            go('dashboard.php?page=catalogos');
        }

        if ($action === 'diagnosis') {
            $code = post_value('codigo_diagnostico');
            $description = post_value('descripcion');
            if ($code === '' || $description === '') {
                throw new RuntimeException('Código y descripción del diagnóstico son obligatorios.');
            }
            $stmt = $pdo->prepare('INSERT INTO diagnosticos (codigo_diagnostico, descripcion_cifrada) VALUES (?, ?)');
            $stmt->execute([$code, encrypt_sensitive($description)]);
            flash('success', 'Diagnóstico registrado.');
            go('dashboard.php?page=catalogos');
        }

        if ($action === 'link') {
            $doctorCode = post_value('codigo_medico');
            $diagnosisCode = post_value('codigo_diagnostico');
            if ($doctorCode === '' || $diagnosisCode === '') {
                throw new RuntimeException('Selecciona un médico y un diagnóstico.');
            }
            $stmt = $pdo->prepare('INSERT INTO medicos_diagnosticos (codigo_medico, codigo_diagnostico) VALUES (?, ?)');
            $stmt->execute([$doctorCode, $diagnosisCode]);
            flash('success', 'Relación médico-diagnóstico creada.');
            go('dashboard.php?page=catalogos');
        }

        throw new RuntimeException('Acción no reconocida.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible guardar. Revisa códigos duplicados y relaciones existentes.';
    }
}

$doctors = $pdo->query('SELECT * FROM medicos ORDER BY apellidos, nombre')->fetchAll();
$diagnoses = $pdo->query('SELECT * FROM diagnosticos ORDER BY codigo_diagnostico')->fetchAll();
$links = $pdo->query(
    'SELECT md.codigo_medico, md.codigo_diagnostico,
            CONCAT(m.nombre, CHAR(32), m.apellidos) AS medico,
            d.descripcion_cifrada
       FROM medicos_diagnosticos md
       JOIN medicos m ON m.codigo_medico = md.codigo_medico
       JOIN diagnosticos d ON d.codigo_diagnostico = md.codigo_diagnostico
      ORDER BY m.apellidos, d.codigo_diagnostico'
)->fetchAll();
?>
<div class="page-intro"><div><h2>Catálogos clínicos</h2><p class="muted">Administra médicos, diagnósticos y la relación de muchos a muchos entre ambos.</p></div></div>
<?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="three-column">
    <section class="panel form-panel"><div class="panel-heading"><div><h3>Nuevo médico</h3><p class="muted">Datos de identificación del profesional.</p></div></div>
        <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="doctor"><label>Código<input name="codigo_medico" maxlength="20" required></label><label>Nombre<input name="nombre" maxlength="80" required></label><label>Apellidos<input name="apellidos" maxlength="120" required></label><label>Especialidad<input name="especialidad" maxlength="100" placeholder="Ej. Medicina interna"></label><button class="button primary" type="submit">Guardar médico</button></form>
    </section>
    <section class="panel form-panel"><div class="panel-heading"><div><h3>Nuevo diagnóstico</h3><p class="muted">La descripción se guarda cifrada.</p></div></div>
        <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="diagnosis"><label>Código<input name="codigo_diagnostico" maxlength="20" required></label><label>Descripción<textarea name="descripcion" rows="3" maxlength="500" required></textarea></label><button class="button primary" type="submit">Guardar diagnóstico</button></form>
    </section>
    <section class="panel form-panel"><div class="panel-heading"><div><h3>Relacionar médico y diagnóstico</h3><p class="muted">Relación entre el médico y el diagnóstico dado.</p></div></div>
        <form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="link"><label>Médico<select name="codigo_medico" required><option value="">Selecciona</option><?php foreach ($doctors as $doctor): ?><option value="<?= e($doctor['codigo_medico']) ?>"><?= e($doctor['nombre'] . ' ' . $doctor['apellidos']) ?></option><?php endforeach; ?></select></label><label>Diagnóstico<select name="codigo_diagnostico" required><option value="">Selecciona</option><?php foreach ($diagnoses as $diagnosis): ?><option value="<?= e($diagnosis['codigo_diagnostico']) ?>"><?= e($diagnosis['codigo_diagnostico'] . ' · ' . decrypt_sensitive($diagnosis['descripcion_cifrada'])) ?></option><?php endforeach; ?></select></label><button class="button secondary" type="submit" <?= (!$doctors || !$diagnoses) ? 'disabled' : '' ?>>Crear relación</button></form>
    </section>
</div>
<div class="two-column">
    <section class="panel"><div class="panel-heading"><h3>Médicos (<?= count($doctors) ?>)</h3></div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Nombre</th><th>Especialidad</th></tr></thead><tbody><?php foreach ($doctors as $doctor): ?><tr><td><?= e($doctor['codigo_medico']) ?></td><td><?= e($doctor['nombre'] . ' ' . $doctor['apellidos']) ?></td><td><?= e($doctor['especialidad'] ?: '—') ?></td></tr><?php endforeach; ?><?php if (!$doctors): ?><tr><td colspan="3" class="empty-cell">Sin médicos.</td></tr><?php endif; ?></tbody></table></div></section>
    <section class="panel"><div class="panel-heading"><h3>Diagnósticos (<?= count($diagnoses) ?>)</h3></div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Descripción</th></tr></thead><tbody><?php foreach ($diagnoses as $diagnosis): ?><tr><td><?= e($diagnosis['codigo_diagnostico']) ?></td><td><?= e(decrypt_sensitive($diagnosis['descripcion_cifrada'])) ?></td></tr><?php endforeach; ?><?php if (!$diagnoses): ?><tr><td colspan="2" class="empty-cell">Sin diagnósticos.</td></tr><?php endif; ?></tbody></table></div></section>
</div>
<section class="panel"><div class="panel-heading"><h3>Relaciones médico-diagnóstico (<?= count($links) ?>)</h3></div><div class="table-wrap"><table><thead><tr><th>Médico</th><th>Diagnóstico</th></tr></thead><tbody><?php foreach ($links as $link): ?><tr><td><?= e($link['medico']) ?></td><td><?= e($link['codigo_diagnostico'] . ' · ' . decrypt_sensitive($link['descripcion_cifrada'])) ?></td></tr><?php endforeach; ?><?php if (!$links): ?><tr><td colspan="2" class="empty-cell">Todavía no hay relaciones registradas.</td></tr><?php endif; ?></tbody></table></div></section>
