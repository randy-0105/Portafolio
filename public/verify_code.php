<?php
require_once '../app/php/auth.php';
require_once '../app/php/db.php';
require_once '../app/php/config.php';

$message = '';
$error = false;

// Verificar que el usuario ha pasado por el paso anterior
if (!isset($_SESSION['forgot_password_user_id'])) {
    header("Location: forgot_username.php");
    exit();
}

$user_id = $_SESSION['forgot_password_user_id'];
$verification_email = getUserVerificationEmail($user_id);

if (!$verification_email) {
    $message = "No se ha configurado un correo electrónico de verificación para este usuario. Por favor, contacta al administrador.";
    $error = true;
}

// Lógica para enviar el código de verificación
if (isset($_GET['action']) && $_GET['action'] === 'send_code' && !$error) {
    $twofa_code = generate2faCode($user_id);
    if ($twofa_code && send2faCodeEmail($verification_email, $twofa_code)) {
        $message = "Se ha enviado un código de verificación a tu correo electrónico.";
        $_SESSION['code_sent'] = true; // Marcar que el código ha sido enviado
    } else {
        $message = "Error al enviar el código de verificación. Por favor, inténtalo de nuevo.";
        $error = true;
    }
}

// Lógica para verificar el código
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $message = "Error de seguridad: Token CSRF inválido.";
        $error = true;
    } else {
        $code_input = sanitizeInput($_POST['twofa_code']);
        if (verify2faCode($user_id, $code_input)) {
            // Código verificado, redirigir a la página de restablecimiento de contraseña
            $_SESSION['password_reset_allowed'] = true; // Permitir el restablecimiento de contraseña
            header("Location: reset_password_flow.php");
            exit();
        } else {
            $message = "Código de verificación incorrecto o expirado.";
            $error = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Verificación</title>
    <link rel="stylesheet" href="./css/styles.css">
    <link rel="stylesheet" href="./css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-login-body">
    <div class="stars"></div>
    <div class="login-container">
        <h2>Verificación de Código</h2>
        <?php if ($message && !isset($_SESSION['code_sent'])): ?>
            <p class="<?php echo $error ? 'error-message' : 'success-message'; ?>"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if (!$verification_email): ?>
            <p class="error-message">No se puede enviar el código de verificación. Contacta al administrador.</p>
            <p class="back-to-portfolio"><a href="forgot_username.php" class="text-link">Volver</a></p>
        <?php else: ?>
            <?php if (!isset($_SESSION['code_sent'])): ?>
                <p>Haz clic en el botón para enviar un código de verificación a tu correo electrónico (<?php echo htmlspecialchars(anonymizeEmail($verification_email)); ?>).</p>
                <p><a href="verify_code.php?action=send_code" class="btn">Enviar Código</a></p>
            <?php else: ?>
                <p style="margin-top: 20px;">Se ha enviado un código de verificación a tu correo electrónico. Por favor, introdúcelo a continuación.</p>
                <form action="verify_code.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <div class="form-group">
                        <label for="twofa_code">Código de Verificación:</label>
                        <input type="text" id="twofa_code" name="twofa_code" placeholder="Código de 6 dígitos" required autofocus>
                    </div>
                    <button type="submit" name="verify_code" class="btn">Verificar Código</button>
                </form>
                <p class="back-to-portfolio"><a href="verify_code.php?action=send_code" class="text-link">Reenviar Código</a></p>
            <?php endif; ?>
            <p class="back-to-portfolio"><a href="forgot_username.php" class="text-link">Volver</a></p>
        <?php endif; ?>
    </div>
    <script src="./js/main.js"></script>
</body>
</html>