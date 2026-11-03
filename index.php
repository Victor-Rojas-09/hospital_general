<?php
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    go('dashboard.php');
}

$error = '';
if (is_post()) {
    verify_csrf();
    $username = post_value('usuario');
    $password = (string) ($_POST['password'] ?? '');

    try {
        $stmt = db()->prepare(
            'SELECT u.id_usuario, u.usuario, u.nombre, u.password_hash, r.nombre AS role
               FROM usuarios u
               JOIN roles r ON r.id_rol = u.id_rol
              WHERE u.usuario = :usuario AND u.activo = 1'
        );
        $stmt->execute(['usuario' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $user['id_usuario'],
                'username' => $user['usuario'],
                'name' => $user['nombre'],
                'role' => $user['role'],
            ];
            go('dashboard.php');
        }
        $error = 'Usuario o contraseña incorrectos.';
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $error = 'No fue posible conectar con la base de datos. Verifica XAMPP y la configuración.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar | Hospital General</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <main class="login-layout">
        <section class="login-intro">
            <div class="login-symbol">+</div>
            <p class="eyebrow">SISTEMA DE INFORMACIÓN</p>
            <h1>Hospital<br>General</h1>
            <p>Gestión de pacientes, ingresos, camas y atención médica.</p>
            <div class="login-note">Aplicación académica de demostración. Utiliza únicamente datos ficticios.</div>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <p class="eyebrow">BIENVENIDO</p>
                <h2>Iniciar sesión</h2>
                <p class="muted">Ingresa tus credenciales para continuar.</p>
                <?php if ($error !== ''): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
                <form method="post" action="index.php" class="form-stack">
                    <?= csrf_field() ?>
                    <label>Usuario<input type="text" name="usuario" autocomplete="username" required autofocus></label>
                    <label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label>
                    <button class="button primary full" type="submit">Ingresar</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
