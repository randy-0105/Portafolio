<?php
require_once '../app/php/auth.php';
require_once '../app/php/db.php';
require_once '../app/php/config.php';
require_once '../app/php/utils.php'; // Incluir funciones de utilidad

$message = '';
$error = false;

// Verificar que el usuario ha pasado por el flujo de verificación
if (!isset($_SESSION['forgot_password_user_id']) || !isset($_SESSION['password_reset_allowed'])) {
    header("Location: forgot_username.php");
    exit();
}

$user_id = $_SESSION['forgot_password_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $message = "Error de seguridad: Token CSRF inválido.";
        $error = true;
    } else {
        $newPassword = $_POST['new_password'];
        $confirmNewPassword = $_POST['confirm_new_password'];

        if (empty($newPassword) || empty($confirmNewPassword)) {
            $message = "Todos los campos de contraseña son obligatorios.";
            $error = true;
        } elseif ($newPassword !== $confirmNewPassword) {
            $message = "La nueva contraseña y la confirmación no coinciden.";
            $error = true;
        } elseif (!isStrongPassword($newPassword)) {
            $message = "La nueva contraseña no es lo suficientemente fuerte. Debe tener al menos 12 caracteres, incluyendo mayúsculas, minúsculas, números y símbolos.";
            $error = true;
        } else {
            if (updateUserPassword($user_id, $newPassword)) {
                $message = "Contraseña restablecida exitosamente. Ya puedes iniciar sesión con tu nueva contraseña.";
                // Limpiar las variables de sesión de recuperación
                unset($_SESSION['forgot_password_user_id']);
                unset($_SESSION['password_reset_allowed']);
                unset($_SESSION['code_sent']);
                // Redirigir al login después de un breve retraso o mostrar un enlace
                header("Location: panel-secreto-2025.php?message=" . urlencode($message));
                exit();
            } else {
                $message = "Error al restablecer la contraseña.";
                $error = true;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <link rel="stylesheet" href="./css/styles.css">
    <link rel="stylesheet" href="./css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-login-body">
    <div class="stars"></div>
    <div class="login-container">
        <h2>Restablecer Contraseña</h2>
        <?php if ($message): ?>
            <p class="<?php echo $error ? 'error-message' : 'success-message'; ?>"><?php echo $message; ?></p>
        <?php endif; ?>
        <p>Por favor, introduce tu nueva contraseña.</p>
        <form action="reset_password_flow.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="form-group">
                <label for="new_password">Nueva Contraseña:</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_new_password">Confirmar Nueva Contraseña:</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" required>
            </div>
            <button type="submit" name="reset_password" class="btn">Guardar Cambios</button>
        </form>
        <p class="back-to-portfolio"><a href="panel-secreto-2025.php" class="text-link">Volver al Login</a></p>
    </div>
    <script src="./js/main.js"></script>
</body>
</html>