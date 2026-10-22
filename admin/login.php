<?php
require_once __DIR__ . '/../includes/functions.php';
if (estaLogueado()) { redirigir(ADMIN_URL.'/index.php'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $stmt = pdo()->prepare('SELECT * FROM usuarios_admin WHERE usuario = ?');
    $stmt->execute([$usuario]);
    $u = $stmt->fetch();
    if ($u && password_verify($contrasena, $u['contrasena'])) {
        $_SESSION['admin_id'] = $u['id_usuario'];
        $_SESSION['admin_nombre'] = $u['nombre'];
        redirigir(ADMIN_URL.'/index.php');
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login - <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="login-body">
<div class="login-card">
    <h1>💍 <?= APP_NAME ?></h1>
    <p>Panel Administrativo</p>
    <?php if($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
        <label>Usuario</label>
        <input type="text" name="usuario" required>
        <label>Contraseña</label>
        <input type="password" name="contrasena" required>
        <button type="submit">Ingresar</button>
    </form>
</div>
</body>
</html>