<?php
require_role(['administrador', 'recepcion', 'medico']);
$pdo = db();
$canEdit = has_role(['administrador', 'recepcion']);
$error = '';

if (is_post()) {
    verify_csrf();
    if (!$canEdit) {
        http_response_code(403);
        exit('No tienes permiso para modificar pacientes.');
    }

    $id = post_value('id_paciente');
    $nss = post_value('nss');
    $dni = post_value('dni');
    $name = post_value('nombre');
    $surname = post_value('apellidos');
    $birth = post_value('fecha_nacimiento');

    if ($nss === '' || $dni === '' || $name === '' || $surname === '' || !valid_date($birth) || $birth > date('Y-m-d')) {
        $error = 'Completa todos los campos y verifica la fecha de nacimiento.';
    } else {
        try {
            $values = [
                'nss_cifrado' => encrypt_sensitive($nss),
                'nss_hash' => sensitive_index('NSS', $nss),
                'dni_cifrado' => encrypt_sensitive($dni),
                'dni_hash' => sensitive_index('DNI', $dni),
                'nombre' => $name,
                'apellidos' => $surname,
                'fecha_nacimiento' => $birth,
            ];

            if ($id !== '') {
                $values['id_paciente'] = (int) $id;
                $stmt = $pdo->prepare(
                    'UPDATE pacientes SET nss_cifrado=:nss_cifrado, nss_hash=:nss_hash,
                     dni_cifrado=:dni_cifrado, dni_hash=:dni_hash, nombre=:nombre,
                     apellidos=:apellidos, fecha_nacimiento=:fecha_nacimiento
                     WHERE id_paciente=:id_paciente'
                );
                $stmt->execute($values);
                flash('success', 'Paciente actualizado correctamente.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO pacientes (nss_cifrado, nss_hash, dni_cifrado, dni_hash,
                     nombre, apellidos, fecha_nacimiento)
                     VALUES (:nss_cifrado, :nss_hash, :dni_cifrado, :dni_hash,
                     :nombre, :apellidos, :fecha_nacimiento)'
                );
                $stmt->execute($values);
                flash('success', 'Paciente registrado correctamente.');
            }
            go('dashboard.php?page=pacientes');
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $error = $exception->getCode() === '23000'
                ? 'El NSS o el DNI ya están registrados.'
                : 'No fue posible guardar el paciente. Verifica los datos.';
        }
    }
}

$editPatient = null;
if ($canEdit && get_value('editar') !== '') {
    $stmt = $pdo->prepare('SELECT * FROM pacientes WHERE id_paciente = ?');
    $stmt->execute([(int) get_value('editar')]);
    $editPatient = $stmt->fetch() ?: null;
}
$search = get_value('q');
$patientStatus = "CASE
    WHEN EXISTS (SELECT 1 FROM ingresos i WHERE i.id_paciente = p.id_paciente) THEN 'activo'
    WHEN EXISTS (SELECT 1 FROM historico_ingresos hi WHERE hi.id_paciente = p.id_paciente) THEN 'historico'
    ELSE 'sin_ingreso'
END AS estado_hospitalario";

if ($search !== '') {
    $stmt = $pdo->prepare(
        'SELECT p.*, ' . $patientStatus . '
           FROM pacientes p
          WHERE p.nombre LIKE :name OR p.apellidos LIKE :surname
             OR p.nss_hash = :nss_hash OR p.dni_hash = :dni_hash
          ORDER BY p.apellidos, p.nombre'
    );
    $stmt->execute([
        'name' => '%' . $search . '%',
        'surname' => '%' . $search . '%',
        'nss_hash' => sensitive_index('NSS', $search),
        'dni_hash' => sensitive_index('DNI', $search),
    ]);
    $patients = $stmt->fetchAll();
} else {
    $patients = $pdo->query(
        'SELECT p.*, ' . $patientStatus . ' FROM pacientes p ORDER BY p.apellidos, p.nombre'
    )->fetchAll();
}
?>
<div class="page-intro">
    <div>
        <h2>Pacientes registrados</h2>
        <p class="muted">Información personal y consulta de todos los pacientes del hospital.</p>
    </div>
    <span class="count-pill"><?= count($patients) ?> registros</span>
</div>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<div class="content-grid <?= $canEdit ? '' : 'single' ?>">
    <?php if ($canEdit): ?>
    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3><?= $editPatient ? 'Editar paciente' : 'Registrar paciente' ?></h3>
                <p class="muted">NSS y DNI se almacenan cifrados.</p>
            </div>
        </div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="id_paciente" value="<?= e($editPatient['id_paciente'] ?? '') ?>">

            <label>Número de Seguridad Social
                <input name="nss" maxlength="40" required value="<?= e($editPatient ? decrypt_sensitive($editPatient['nss_cifrado']) : '') ?>">
            </label>

            <label>DNI
                <input name="dni" maxlength="30" required value="<?= e($editPatient ? decrypt_sensitive($editPatient['dni_cifrado']) : '') ?>">
            </label>

            <div class="form-row">
                <label>Nombre
                    <input name="nombre" maxlength="80" required value="<?= e($editPatient['nombre'] ?? '') ?>">
                </label>
                <label>Apellidos
                    <input name="apellidos" maxlength="120" required value="<?= e($editPatient['apellidos'] ?? '') ?>">
                </label>
            </div>

            <label>Fecha de nacimiento
                <input type="date" name="fecha_nacimiento" required value="<?= e($editPatient['fecha_nacimiento'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
            </label>

            <div class="form-actions">
                <button class="button primary" type="submit"><?= $editPatient ? 'Guardar cambios' : 'Registrar paciente' ?></button>
                <?php if ($editPatient): ?>
                    <a class="button secondary" href="dashboard.php?page=pacientes">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h3>Listado de pacientes</h3>
                <p class="muted">Busca por nombre, apellidos, NSS o DNI exacto. El estado indica si tiene un ingreso vigente o antecedentes archivados.</p>
            </div>
        </div>

        <form method="get" class="search-form">
            <input type="hidden" name="page" value="pacientes">
            <input type="search" name="q" placeholder="Buscar paciente..." value="<?= e($search) ?>">
            <button class="button secondary" type="submit">Buscar</button>
            <?php if ($search !== ''): ?>
                <a class="text-link" href="dashboard.php?page=pacientes">Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Estado</th>
                        <th>Fecha nacimiento</th>
                        <?php if (has_role(['administrador', 'recepcion'])): ?>
                            <th>NSS</th>
                            <th>DNI</th>
                        <?php endif; ?>
                        <?php if ($canEdit): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td>
                                <strong><?= e($patient['nombre'] . ' ' . $patient['apellidos']) ?></strong>
                                <small>ID <?= e($patient['id_paciente']) ?></small>
                            </td>
                            <td>
                                <?php if ($patient['estado_hospitalario'] === 'activo'): ?>
                                    <span class="tag success">Activo</span>
                                <?php elseif ($patient['estado_hospitalario'] === 'historico'): ?>
                                    <span class="tag warning">Histórico</span>
                                <?php else: ?>
                                    <span class="tag muted-tag">Sin ingreso</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e(date('d/m/Y', strtotime($patient['fecha_nacimiento']))) ?></td>
                            <?php if (has_role(['administrador', 'recepcion'])): ?>
                                <td><?= e(decrypt_sensitive($patient['nss_cifrado'])) ?></td>
                                <td><?= e(decrypt_sensitive($patient['dni_cifrado'])) ?></td>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                                <td>
                                    <a class="text-link" href="dashboard.php?page=pacientes&editar=<?= e($patient['id_paciente']) ?>">Editar</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$patients): ?>
                        <tr>
                            <td colspan="<?= 3 + (has_role(['administrador', 'recepcion']) ? 2 : 0) + ($canEdit ? 1 : 0) ?>" class="empty-cell">No se encontraron pacientes.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>