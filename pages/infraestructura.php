<?php
require_role(['administrador', 'recepcion']);
$pdo = db();
$error = '';

if (is_post()) {
    verify_csrf();
    $number = filter_var(post_value('numero_planta'), FILTER_VALIDATE_INT);
    $name = post_value('nombre');
    $capacity = filter_var(post_value('capacidad_camas'), FILTER_VALIDATE_INT);

    if ($number === false || $number < 0 || $name === '' || $capacity === false || $capacity < 1 || $capacity > 500) {
        $error = 'Ingresa un número de planta válido, un nombre y una capacidad entre 1 y 500 camas.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO plantas (numero_planta, nombre, capacidad_camas) VALUES (?, ?, ?)');
            $stmt->execute([$number, $name, $capacity]);
            $bedInsert = $pdo->prepare('INSERT INTO camas (numero_planta, numero_cama) VALUES (?, ?)');
            for ($bed = 1; $bed <= $capacity; $bed++) {
                $bedInsert->execute([$number, $bed]);
            }
            $pdo->commit();
            flash('success', 'Planta creada y camas generadas correctamente.');
            go('dashboard.php?page=infraestructura');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($exception->getMessage());
            $error = 'No fue posible crear la planta. Comprueba que el número no esté registrado.';
        }
    }
}

$plants = $pdo->query(
    'SELECT p.numero_planta, p.nombre, p.capacidad_camas,
            COUNT(c.numero_cama) AS camas_registradas,
            COUNT(o.numero_cama) AS ocupadas
       FROM plantas p
       LEFT JOIN camas c ON c.numero_planta = p.numero_planta
       LEFT JOIN ocupaciones_cama o ON o.numero_planta = c.numero_planta AND o.numero_cama = c.numero_cama
      GROUP BY p.numero_planta, p.nombre, p.capacidad_camas
      ORDER BY p.numero_planta'
)->fetchAll();
?>

<div class="page-intro">
    <div>
        <h2>Infraestructura hospitalaria</h2>
        <p class="muted">Registra las plantas y genera automáticamente sus camas numeradas.</p>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<div class="content-grid">
    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3>Registrar planta</h3>
                <p class="muted">Se crearán camas desde el número 1 hasta la capacidad indicada.</p>
            </div>
        </div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <label>Número de planta
                <input type="number" name="numero_planta" min="0" max="999" required>
            </label>
            <label>Nombre de la planta
                <input type="text" name="nombre" maxlength="80" placeholder="Ej. Medicina interna" required>
            </label>
            <label>Capacidad de camas
                <input type="number" name="capacidad_camas" min="1" max="500" required>
            </label>
            <button class="button primary" type="submit">Crear planta y camas</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-heading">
            <div>
                <h3>Plantas registradas</h3>
                <p class="muted">La ocupación se actualiza con los ingresos y movimientos.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Planta</th>
                        <th>Camas</th>
                        <th>Ocupadas</th>
                        <th>Disponibles</th>
                        <th>Ocupación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($plants as $plant):
                        $available = (int) $plant['camas_registradas'] - (int) $plant['ocupadas'];
                        $percent = (int) $plant['camas_registradas'] > 0 ? round(((int) $plant['ocupadas'] / (int) $plant['camas_registradas']) * 100) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong><?= e($plant['numero_planta']) ?> · <?= e($plant['nombre']) ?></strong>
                                <small>Capacidad declarada: <?= e($plant['capacidad_camas']) ?></small>
                            </td>
                            <td><?= e($plant['camas_registradas']) ?></td>
                            <td><?= e($plant['ocupadas']) ?></td>
                            <td><?= e($available) ?></td>
                            <td>
                                <span class="tag <?= $percent >= 90 ? 'warning' : 'success' ?>"><?= $percent ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$plants): ?>
                        <tr>
                            <td colspan="5" class="empty-cell">No hay plantas registradas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>