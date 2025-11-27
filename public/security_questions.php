<?php
require_once '../app/php/auth.php';
require_once '../app/php/db.php';
require_once '../app/php/config.php';

$message = '';
$error = false;

// Verificar que el usuario ha pasado por el paso de forgot_username
if (!isset($_SESSION['forgot_password_user_id'])) {
    header("Location: forgot_username.php");
    exit();
}

$user_id = $_SESSION['forgot_password_user_id'];
$user_security_questions_raw = getUserSecurityQuestionsAndAnswers($user_id);

$security_questions = [];
foreach ($user_security_questions_raw as $index => $q_data) {
    $security_questions[] = ['id' => $index + 1, 'question' => $q_data['question_text']];
}

if (empty($security_questions)) {
    // Si no hay preguntas de seguridad configuradas, redirigir a la verificación por correo
    header("Location: verify_code.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answers'])) {
    if (!verifyCsrfToken($_POST['csrf_token'])) {
        $message = "Error de seguridad: Token CSRF inválido.";
        $error = true;
    } else {
        $answer1 = $_POST['answer_1'] ?? '';
        $answer2 = $_POST['answer_2'] ?? '';

        if (verifySecurityAnswers($user_id, $answer1, $answer2)) {
            // Respuestas correctas, proceder a enviar código de verificación
            header("Location: verify_code.php");
            exit();
        } else {
            $message = "Respuestas incorrectas. Por favor, inténtalo de nuevo.";
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
    <title>Recuperar Contraseña - Preguntas de Seguridad</title>
    <link rel="stylesheet" href="./css/styles.css">
    <link rel="stylesheet" href="./css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-login-body">
    <div class="stars"></div>
    <div class="login-container">
        <h2>Preguntas de Seguridad</h2>
        <?php if ($message): ?>
            <p class="<?php echo $error ? 'error-message' : 'success-message'; ?>"><?php echo $message; ?></p>
        <?php endif; ?>
        <p>Por favor, responde las siguientes preguntas de seguridad.</p>
        <form action="security_questions.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <?php foreach ($security_questions as $q): ?>
                <div class="form-group">
                    <label for="answer_<?php echo $q['id']; ?>"><?php echo htmlspecialchars($q['question']); ?>:</label>
                    <input type="text" id="answer_<?php echo $q['id']; ?>" name="answer_<?php echo $q['id']; ?>" required>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="submit_answers" class="btn">Verificar Respuestas</button>
        </form>
        <p class="back-to-portfolio"><a href="forgot_username.php" class="text-link">Volver</a></p>
    </div>
    <script src="./js/main.js"></script>
</body>
</html>