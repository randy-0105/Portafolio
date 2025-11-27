<?php
require_once '../app/php/auth.php';
require_once '../app/php/db.php';

$message = '';
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_username'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $message = "Error de seguridad: Token CSRF inválido.";
        $error = true;
    } else {
        $username_input = sanitizeInput($_POST['username']);
        $user_id = getUserIdByUsername($username_input);

        if ($user_id) {
            $_SESSION['forgot_password_user_id'] = $user_id;
            header("Location: security_questions.php");
            exit();
        } else {
            $message = "Nombre de usuario no encontrado.";
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
    <title>Recuperar Contraseña - Usuario</title>
    <link rel="stylesheet" href="./css/styles.css">
    <link rel="stylesheet" href="./css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-login-body">
    <div class="stars"></div>
    <div class="login-container">
        <h2>Recuperar Contraseña</h2>
        <?php if ($message): ?>
            <p class="message <?php echo $error ? 'error-message' : 'success-message'; ?>"><?php echo $message; ?></p>
        <?php endif; ?>
        <p>Por favor, introduce tu nombre de usuario para continuar con la recuperación de contraseña.</p>
        <form action="/forgot_username.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <div class="form-group">
                <label for="username">Nombre de Usuario:</label>
                <input type="text" id="username" name="username" placeholder="Tu nombre de usuario" required autofocus>
            </div>
            <button type="submit" name="request_username" class="btn">Continuar</button>
        </form>
        <p class="back-to-portfolio"><a href="/panel-secreto-2025.php" class="text-link">Volver al Login</a></p>
    </div>
    <script src="./js/main.js"></script>
</body>
</html>