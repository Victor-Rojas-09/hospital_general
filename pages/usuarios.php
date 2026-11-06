<?php
require_role(['administrador']);
$pdo = db();
$error = '';

if (is_post()) {
    verify_csrf();
    $action = post_value('action');
    try {
        if ($action === 'create') {
            $username = post_value('usuario');
            $name = post_value('nombre');
            $password = (string) ($_POST['password'] ?? '');
            $roleId = (int) post_value('id_rol');
            if (!preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $username) || $name === '' || strlen($password) < 8 || $roleId < 1) {
                throw new RuntimeException('Usuario: 3 a 40 caracteres (letras, números, punto, guion). Contraseña: mínimo 8 caracteres.');
            }
            $stmt = $pdo->prepare('INSERT INTO usuarios (id_rol, usuario, nombre, password_hash) VALUES (?, ?, ?, ?)');
            $stmt->execute([$roleId, $username, $name, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Usuario creado correctamente.');
            go('dashboard.php?page=usuarios');
        }

        if ($action === 'toggle') {
            $userId = (int) post_value('id_usuario');
            if ($userId === (int) current_user()['id']) {
                throw new RuntimeException('No puedes desactivar tu propio usuario.');
            }
            $stmt = $pdo->prepare('UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id_usuario = ?');
            $stmt->execute([$userId]);
            flash('success', 'Estado del usuario actualizado.');
            go('dashboard.php?page=usuarios');
        }

        if ($action === 'password') {
            $oldPassword = (string) ($_POST['old_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            if (strlen($newPassword) < 8) {
                throw new RuntimeException('La nueva contraseña debe tener al menos 8 caracteres.');
            }
            $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id_usuario = ?');
            $stmt->execute([current_user()['id']]);
            $hash = (string) $stmt->fetchColumn();
            if (!password_verify($oldPassword, $hash)) {
                throw new RuntimeException('La contraseña actual no es correcta.');
            }
            $update = $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?');
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), current_user()['id']]);
            flash('success', 'Tu contraseña fue actualizada.');
            go('dashboard.php?page=usuarios');
        }

        throw new RuntimeException('Acción no reconocida.');
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'No fue posible guardar el usuario. Revisa que el nombre de usuario no esté repetido.';
    }
}

$roles = $pdo->query('SELECT id_rol, nombre FROM roles ORDER BY id_rol')->fetchAll();
$users = $pdo->query(
    'SELECT u.id_usuario, u.usuario, u.nombre, u.activo, u.creado_en, r.nombre AS rol
       FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
      ORDER BY u.id_usuario'
)->fetchAll();
?>

<div class="page-intro">
    <div>
        <h2>Control de Usuarios</h2>
        <p class="muted">Crea cuentas y asigna roles para limitar el acceso a los módulos.</p>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= e($error) ?></div>
<?php endif; ?>

<div class="content-grid">
    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3>Crear usuario</h3>
                <p class="muted">La contraseña se almacena mediante password_hash.</p>
            </div>
        </div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <label>Nombre de usuario
                <input name="usuario" maxlength="40" required>
            </label>
            <label>Nombre completo
                <input name="nombre" maxlength="100" required>
            </label>
            <label>Contraseña inicial
                <input type="password" name="password" minlength="8" required>
            </label>
            <label>Rol
                <select name="id_rol" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role['id_rol']) ?>"><?= e(ucfirst($role['nombre'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button primary" type="submit">Crear usuario</button>
        </form>
    </section>

    <section class="panel form-panel">
        <div class="panel-heading">
            <div>
                <h3>Cambiar mi contraseña</h3>
                <p class="muted">Cambio de contraseña para Root User.</p>
            </div>
        </div>
        <form method="post" class="form-stack">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <label>Contraseña actual
                <input type="password" name="old_password" required>
            </label>
            <label>Nueva contraseña
                <input type="password" name="new_password" minlength="8" required>
            </label>
            <button class="button secondary" type="submit">Actualizar contraseña</button>
        </form>
    </section>
</div>

<section class="panel">
    <div class="panel-heading">
        <h3>Cuentas registradas</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Creado</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $item): ?>
                    <tr>
                        <td>
                            <strong><?= e($item['nombre']) ?></strong>
                            <small><?= e($item['usuario']) ?></small>
                        </td>
                        <td><?= e(ucfirst($item['rol'])) ?></td>
                        <td><?= e(date('d/m/Y', strtotime($item['creado_en']))) ?></td>
                        <td>
                            <span class="tag <?= $item['activo'] ? 'success' : 'muted-tag' ?>"><?= $item['activo'] ? 'Activo' : 'Inactivo' ?></span>
                        </td>
                        <td>
                            <?php if ((int) $item['id_usuario'] !== (int) current_user()['id']): ?>
                                <form method="post" class="inline-form" data-confirm="¿Cambiar el estado de esta cuenta?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id_usuario" value="<?= e($item['id_usuario']) ?>">
                                    <button class="button secondary small" type="submit"><?= $item['activo'] ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Sesión actual</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>