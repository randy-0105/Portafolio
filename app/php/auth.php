<?php
// app/php/auth.php - Lógica de autenticación y seguridad

ob_start(); // Iniciar el búfer de salida

require_once 'config.php';
require_once 'db.php';
require_once 'utils.php'; // Incluir funciones de utilidad

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php'; // Asegúrate de que la ruta a autoload.php sea correcta

if (session_status() == PHP_SESSION_NONE) {
    session_start();
    error_log("Auth: session_start() llamado. Session ID: " . session_id());
} else {
    error_log("Auth: session_start() ignorado, la sesión ya está activa. Session ID: " . session_id());
}

// Función para generar un token CSRF
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para verificar un token CSRF
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para verificar si el usuario está logueado
function isAuthenticated() {
    $is_auth = isset($_SESSION['user_id']);
    error_log("Auth: isAuthenticated() llamado. User ID en sesión: " . ($_SESSION['user_id'] ?? 'N/A') . ". Autenticado: " . ($is_auth ? 'Sí' : 'No'));
    return $is_auth;
}

// Función para registrar accesos
function logAccess($action, $username = null, $ip_address = null, $user_id = null, $details = null) {
    $db = getDatabaseConnection();
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO access_logs (user_id, username, ip_address, action, user_agent, details) VALUES (:user_id, :username, :ip_address, :action, :user_agent, :details)");
        $stmt->execute([
            'user_id' => $user_id,
            'username' => $username,
            'ip_address' => $ip_address ?? getUserIpAddr(),
            'action' => $action,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'details' => $details
        ]);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error al registrar acceso: " . $e->getMessage());
    }
}

// Función para iniciar sesión
function login($username, $password) {
    $db = getDatabaseConnection();

    // Verificar si el usuario o la IP están bloqueados
    if (isBlocked($username, getUserIpAddr())) {
        error_log("Intento de login bloqueado para el usuario: " . $username . " o IP: " . getUserIpAddr());
        return false;
    }

    $stmt = $db->prepare("SELECT id, username, password FROM admin_users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Login exitoso
        clearLoginAttempts($username, getUserIpAddr()); // Limpiar intentos fallidos
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['last_activity'] = time();
        $_SESSION['2fa_required'] = true; // Indicar que se requiere 2FA
        session_regenerate_id(true); // Regenerar ID de sesión para prevenir fijación de sesión

        error_log("Auth: Login exitoso para el usuario: " . $username . ". Estableciendo 2FA requerido.");
        // Generar y enviar código 2FA
        $twofa_code = generate2faCode($user['id']);
        error_log("DEBUG 2FA: user_id=" . $user['id'] . ", twofa_code=" . ($twofa_code ?? 'NULL') . ", user_email=" . ($user_email ?? 'NULL'));
        $user_email = getUserEmail($user['id']); // Obtener el email del usuario
        if ($user_email && $twofa_code) {
            $email_sent = send2faCodeEmail($user_email, $twofa_code);
            if ($email_sent) {
                error_log("Auth: Código 2FA enviado a " . $user_email . " para el usuario: " . $username);
            } else {
                error_log("Auth: FALLO al enviar el código 2FA a " . $user_email . " para el usuario: " . $username);
            }
        } else {
            error_log("Auth: Error al generar o enviar el código 2FA para el usuario: " . $username . ". user_email: " . ($user_email ? 'Sí' : 'No') . ", twofa_code: " . ($twofa_code ? 'Sí' : 'No'));
            // Considerar qué hacer si el 2FA no se puede enviar (ej. forzar logout o mostrar error)
            // Por ahora, permitiremos el login pero con un log de error.
        }
        logAccess('login_success', $username, getUserIpAddr(), $user['id']);
        sendLoginNotification($username, getUserIpAddr(), 'success');
        error_log("Auth: Login exitoso para el usuario: " . $username . ". User ID en sesión: " . $_SESSION['user_id']);
        return true;
    } else {
        // Login fallido
        error_log("Auth: Fallo de login para el usuario: " . $username . ". Contraseña incorrecta o usuario no encontrado.");
        recordFailedLoginAttempt($username, getUserIpAddr());
        logAccess('login_failure', $username, getUserIpAddr());
        sendLoginNotification($username, getUserIpAddr(), 'failure');
        error_log("Auth: Fallo de login para el usuario: " . $username);
        return false;
    }
}

// Función para cerrar sesión
function logout() {
    if (isAuthenticated()) {
        logAccess('logout', $_SESSION['username'], getUserIpAddr(), $_SESSION['user_id']);
    }
    $_SESSION = array(); // Vaciar todas las variables de sesión
    session_destroy(); // Destruir la sesión
    header("Location: /panel-secreto-2025.php"); // Redirigir a la página de login
    exit();
}

// Función para enviar notificaciones de login
function sendLoginNotification($username, $ip_address, $status) {
    if (!ENABLE_LOGIN_NOTIFICATIONS) {
        return;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_SERVER;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress(SMTP_FROM_EMAIL); // Enviar la notificación al mismo correo del remitente

        $subject = ($status === 'success') ? 'Acceso Exitoso al Panel de Administración' : 'Intento de Acceso Fallido al Panel de Administración';
        $body = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #e0e0e0; }
        .header { background-color: #007bff; padding: 25px 30px; color: #ffffff; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .content { padding: 30px; color: #333333; line-height: 1.6; }
        .content p { margin-bottom: 15px; font-size: 16px; }
        .content strong { color: #007bff; }
        .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .details-table th, .details-table td { border: 1px solid #e0e0e0; padding: 10px; text-align: left; }
        .details-table th { background-color: #f0f0f0; font-weight: 600; }
        .footer { background-color: #f8f9fa; padding: 20px 30px; text-align: center; font-size: 13px; color: #6c757d; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid #e0e0e0; }
        .footer p { margin: 0; }
        .warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <h1>Notificación de Acceso al Panel de Administración</h1>
        </div>
        <div class='content'>
            <p>Hola,</p>
            <p>Se ha detectado un intento de acceso a tu panel de administración del Portafolio. Aquí están los detalles:</p>
            <table class='details-table'>
                <tr><th>Estado:</th><td><strong>" . (($status === 'success') ? 'Exitoso' : 'Fallido') . "</strong></td></tr>
                <tr><th>Usuario:</th><td>" . htmlspecialchars($username) . "</td></tr>
                <tr><th>Dirección IP:</th><td>" . htmlspecialchars($ip_address) . "</td></tr>
                <tr><th>Fecha/Hora:</th><td>" . date('Y-m-d H:i:s') . "</td></tr>
                <tr><th>User Agent:</th><td>" . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "</td></tr>
            </table>
            <p class='warning'>Si no reconoces esta actividad o no fuiste tú quien intentó iniciar sesión, te recomendamos encarecidamente que revises la seguridad de tu cuenta y cambies tu contraseña inmediatamente.</p>
            <p>Saludos cordiales,</p>
            <p><strong>Administrador del Portafolio</strong></p>
        </div>
        <div class='footer'>
            <p>&copy; " . date('Y') . " Portafolio. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>";
        $mail->AltBody = "Se ha detectado un intento de acceso al panel de administración:\n\n";
        $mail->AltBody .= "Estado: " . (($status === 'success') ? 'Exitoso' : 'Fallido') . "\n";
        $mail->AltBody .= "Usuario: " . htmlspecialchars($username) . "\n";
        $mail->AltBody .= "Dirección IP: " . htmlspecialchars($ip_address) . "\n";
        $mail->AltBody .= "Fecha/Hora: " . date('Y-m-d H:i:s') . "\n";
        $mail->AltBody .= "User Agent: " . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n\n";
        $mail->AltBody .= "Si no reconoces esta actividad, por favor, revisa la seguridad de tu cuenta.";

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        error_log("Notificación de login (" . $status . ") enviada a: " . SMTP_FROM_EMAIL);
    } catch (Exception $e) {
        error_log("Error al enviar la notificación de login a " . SMTP_FROM_EMAIL . ": " . $mail->ErrorInfo);
    }
}

// Función para verificar la actividad de la sesión
function checkSessionActivity() {
    if (isAuthenticated() && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        logout(); // Cerrar sesión si ha pasado mucho tiempo de inactividad
    }
    $_SESSION['last_activity'] = time(); // Actualizar la última actividad
}

// Función para sanitizar la entrada de usuario (prevención de XSS)
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function anonymizeEmail($email) {
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        list($localPart, $domain) = explode('@', $email);
        $length = strlen($localPart);
        if ($length <= 2) {
            // Si la parte local es muy corta, mostrar solo el primer carácter y asteriscos
            return substr($localPart, 0, 1) . str_repeat('*', $length - 1) . '@' . $domain;
        } else {
            // Mostrar el primer carácter, asteriscos y el último carácter
            return substr($localPart, 0, 1) . str_repeat('*', $length - 2) . substr($localPart, -1) . '@' . $domain;
        }
    }
    return $email; // Devolver el email original si no es válido
}

// Función para insertar el usuario administrador por defecto si no existe
function createDefaultAdminUser() {
    $db = getDatabaseConnection();
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM admin_users WHERE username = :username");
        $stmt->execute(['username' => ADMIN_USERNAME]);
        if ($stmt->fetchColumn() > 0) {
            error_log("DEBUG createDefaultAdminUser: Usuario administrador ya existe. Saliendo.");
            return; // El usuario ya existe, salir
        }

        error_log("DEBUG createDefaultAdminUser: Antes de beginTransaction. inTransaction: " . ($db->inTransaction() ? 'true' : 'false'));
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            error_log("DEBUG createDefaultAdminUser: beginTransaction llamado. inTransaction: " . ($db->inTransaction() ? 'true' : 'false'));
        } else {
            error_log("DEBUG createDefaultAdminUser: Transacción ya activa, no se llama beginTransaction. inTransaction: " . ($db->inTransaction() ? 'true' : 'false'));
        }

        $stmt = $db->prepare("INSERT INTO admin_users (username, password, email, verification_email) VALUES (:username, :password, :email, :verification_email)");
        $stmt->execute([
            'username' => ADMIN_USERNAME,
            'password' => ADMIN_PASSWORD,
            'email' => SMTP_FROM_EMAIL,
            'verification_email' => SMTP_FROM_EMAIL // Usar el correo del remitente SMTP como correo de verificación por defecto
        ]);
        $admin_user_id = $db->lastInsertId();

        // Insertar preguntas de seguridad por defecto en la nueva tabla
        $default_questions = [
            ['question' => '¿Cuál es el nombre de tu primera mascota?', 'answer' => 'mascota'],
            ['question' => '¿Cuál es el nombre de soltera de tu madre?', 'answer' => 'soltera']
        ];
        error_log("DEBUG createDefaultAdminUser: Antes de llamar saveSecurityQuestions. inTransaction: " . ($db->inTransaction() ? 'true' : 'false'));
        saveSecurityQuestions($admin_user_id, $default_questions);

        error_log("DEBUG createDefaultAdminUser: Después de llamar saveSecurityQuestions. inTransaction: " . ($db->inTransaction() ? 'true' : 'false'));
        error_log("Usuario administrador por defecto creado: " . ADMIN_USERNAME . " con email: " . SMTP_FROM_EMAIL . " y preguntas de seguridad por defecto.");
        $db->commit();
        error_log("DEBUG createDefaultAdminUser: commit llamado. inTransaction: " . ($db->inTransaction() ? 'true' : 'false'));
        return; // Éxito, salir de la función
    } catch (PDOException $e) {
        error_log("DEBUG createDefaultAdminUser: Excepción capturada. Mensaje: " . $e->getMessage());
        if ($db->inTransaction()) {
            error_log("DEBUG createDefaultAdminUser: Rollback llamado.");
            $db->rollBack();
        } else {
            error_log("DEBUG createDefaultAdminUser: No hay transacción activa para rollback.");
        }
        error_log("Error al crear usuario administrador por defecto: " . $e->getMessage());
        throw $e; // Relanzar la excepción
    }
}
// Función para actualizar la contraseña de un usuario
function updateUserPassword($userId, $newPassword) {
    $db = getDatabaseConnection();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    error_log("DEBUG: Intentando actualizar contraseña para user_id: " . $userId);
    $stmt = $db->prepare("UPDATE admin_users SET password = :password WHERE id = :id");
    $result = $stmt->execute(['password' => $hashedPassword, 'id' => $userId]);
    if ($result) {
        error_log("DEBUG: Contraseña actualizada exitosamente para user_id: " . $userId);
    } else {
        error_log("DEBUG: Fallo al ejecutar la actualización de contraseña para user_id: " . $userId . ". ErrorInfo: " . json_encode($stmt->errorInfo()));
    }
    return $result;
}


// Función para obtener el nombre de usuario por ID
function getUserUsername($userId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT username FROM admin_users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user['username'] ?? null;
}

// Función para obtener el ID de usuario por nombre de usuario
function getUserIdByUsername($username) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user['id'] ?? null;
}

// Función para obtener el correo electrónico de verificación de un usuario
function getUserVerificationEmail($userId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT verification_email FROM admin_users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['verification_email'] ?? null;
}
// Funciones para preguntas de seguridad
function saveSecurityQuestions($userId, $questions_to_save) {
    $db = getDatabaseConnection();
    // Primero, eliminar las preguntas existentes para el usuario
    $stmt = $db->prepare("DELETE FROM user_security_questions WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);

    // Luego, insertar las nuevas preguntas
    $stmt = $db->prepare("INSERT INTO user_security_questions (user_id, question_text, answer_hash) VALUES (:user_id, :question_text, :answer_hash)");
    foreach ($questions_to_save as $q_a) {
        $hashedAnswer = password_hash(sanitizeInput(strtolower($q_a['answer'])), PASSWORD_DEFAULT);
        $stmt->execute([
            'user_id' => $userId,
            'question_text' => sanitizeInput($q_a['question']),
            'answer_hash' => $hashedAnswer
        ]);
    }
    return true;
}

// Función para obtener las preguntas de seguridad de un usuario
function getUserSecurityQuestions($userId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT question_text FROM user_security_questions WHERE user_id = :user_id ORDER BY id ASC");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener las preguntas de seguridad y sus respuestas (hasheadas) de un usuario
function getUserSecurityQuestionsAndAnswers($userId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT question_text, answer_hash FROM user_security_questions WHERE user_id = :user_id ORDER BY id ASC");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para verificar las respuestas de seguridad
function verifySecurityAnswers($userId, $answer1, $answer2) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT question_text, answer_hash FROM user_security_questions WHERE user_id = :user_id ORDER BY id ASC");
    $stmt->execute(['user_id' => $userId]);
    $user_questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($user_questions) < 2) {
        return false; // No hay suficientes preguntas de seguridad configuradas
    }

    // Verificar la primera pregunta/respuesta
    $is_answer1_correct = false;
    if (isset($user_questions[0]) && password_verify(strtolower($answer1), $user_questions[0]['answer_hash'])) {
        $is_answer1_correct = true;
    }

    // Verificar la segunda pregunta/respuesta
    $is_answer2_correct = false;
    if (isset($user_questions[1]) && password_verify(strtolower($answer2), $user_questions[1]['answer_hash'])) {
        $is_answer2_correct = true;
    }

    return $is_answer1_correct && $is_answer2_correct;
}




// Funciones para recuperación de contraseña (flujo antiguo, se mantendrá por ahora)
function generatePasswordResetToken($userId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT id FROM admin_users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token válido por 1 hora

        $stmt = $db->prepare("UPDATE admin_users SET reset_token = :token, reset_token_expires_at = :expires WHERE id = :id");
        $stmt->execute(['token' => $token, 'expires' => $expires, 'id' => $user['id']]);
        return $token;
    }
    return false;
}

function sendPasswordResetEmail($email, $token) {
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_SERVER;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Restablecimiento de Contraseña para tu Portafolio';
        $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . "/panel-secreto-2025.php?section=reset_password&token=" . $token;
        $mail->Body    = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #e0e0e0; }
        .header { background-color: #007bff; padding: 25px 30px; color: #ffffff; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .content { padding: 30px; color: #333333; line-height: 1.6; }
        .content p { margin-bottom: 15px; font-size: 16px; }
        .content strong { color: #007bff; }
        .button-container { text-align: center; margin: 25px 0; }
        .button { background-color: #28a745; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold; display: inline-block; }
        .footer { background-color: #f8f9fa; padding: 20px 30px; text-align: center; font-size: 13px; color: #6c757d; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid #e0e0e0; }
        .footer p { margin: 0; }
        .warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <h1>Restablecimiento de Contraseña</h1>
        </div>
        <div class='content'>
            <p>Hola,</p>
            <p>Has solicitado restablecer tu contraseña para tu cuenta de administrador del Portafolio. Para proceder, haz clic en el siguiente botón:</p>
            <div class='button-container'>
                <a href='{$resetLink}' class='button'>Restablecer Contraseña</a>
            </div>
            <p>Este enlace es válido por <strong>1 hora</strong>. Si no lo utilizas dentro de este período, deberás solicitar un nuevo restablecimiento.</p>
            <p class='warning'>Si no solicitaste este cambio, por favor ignora este correo. Tu contraseña actual permanecerá segura.</p>
            <p>Saludos cordiales,</p>
            <p><strong>Administrador del Portafolio</strong></p>
        </div>
        <div class='footer'>
            <p>&copy; " . date('Y') . " Portafolio. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>";
        $mail->AltBody = "Hola,\n\nHas solicitado restablecer tu contraseña para tu cuenta de administrador del Portafolio. Copia y pega el siguiente enlace en tu navegador para continuar:\n\n{$resetLink}\n\nEste enlace expirará en 1 hora.\n\nSi no solicitaste esto, por favor ignora este correo.\n\nSaludos,\nAdministrador del Portafolio";

        $mail->send();
        error_log("Correo de recuperación enviado a: " . $email);
        return true;
    } catch (Exception $e) {
        error_log("Error al enviar el correo de recuperación a " . $email . ": " . $mail->ErrorInfo);
        return false;
    }
}

function verifyPasswordResetToken($token) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT id, reset_token_expires_at FROM admin_users WHERE reset_token = :token");
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && strtotime($user['reset_token_expires_at']) > time()) {
        return $user['id'];
    }
    return false;
}

// Funciones para 2FA
function generate2faCode($userId) {
    $db = getDatabaseConnection();
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT); // Código de 6 dígitos
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes')); // Código válido por 10 minutos

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE admin_users SET twofa_code = :code, twofa_code_expires_at = :expires WHERE id = :id");
        $stmt->execute(['code' => $code, 'expires' => $expires, 'id' => $userId]);
        $db->commit();
        return $code;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error al generar código 2FA: " . $e->getMessage());
        return false;
    }
}

function send2faCodeEmail($email, $code) {
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_SERVER;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->Timeout = SMTP_TIMEOUT; // Establecer el tiempo de espera de SMTP

        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Código de Verificación de 2 Pasos para tu Portafolio';
        $mail->Body    = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); overflow: hidden; border: 1px solid #e0e0e0; }
        .header { background-color: #007bff; padding: 25px 30px; color: #ffffff; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .content { padding: 30px; color: #333333; line-height: 1.6; }
        .content p { margin-bottom: 15px; font-size: 16px; }
        .content strong { color: #007bff; }
        .code-box { background-color: #e9ecef; color: #333333; padding: 15px 20px; border-radius: 5px; font-size: 24px; font-weight: bold; text-align: center; margin: 25px 0; letter-spacing: 3px; }
        .footer { background-color: #f8f9fa; padding: 20px 30px; text-align: center; font-size: 13px; color: #6c757d; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; border-top: 1px solid #e0e0e0; }
        .footer p { margin: 0; }
        .warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='header'>
            <h1>Código de Verificación de 2 Pasos</h1>
        </div>
        <div class='content'>
            <p>Hola,</p>
            <p>Tu código de verificación de 2 pasos para acceder a tu Portafolio es:</p>
            <div class='code-box'>
                <strong>{$code}</strong>
            </div>
            <p>Este código expirará en <strong>10 minutos</strong>. Por favor, introdúcelo en la página de verificación para completar tu acceso.</p>
            <p class='warning'>Si no solicitaste este código, por favor ignora este correo. Tu cuenta permanece segura.</p>
            <p>Saludos cordiales,</p>
            <p><strong>Administrador del Portafolio</strong></p>
        </div>
        <div class='footer'>
            <p>&copy; " . date('Y') . " Portafolio. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>";
        $mail->AltBody = "Hola,\n\nTu código de verificación de 2 pasos para acceder a tu Portafolio es: {$code}\n\nEste código expirará en 10 minutos.\n\nSi no solicitaste esto, por favor ignora este correo.\n\nSaludos,\nAdministrador del Portafolio";

        error_log("DEBUG 2FA Email: Intentando enviar correo a " . $email);
        $mail->send();
        error_log("DEBUG 2FA Email: Código 2FA enviado exitosamente a: " . $email);
        return true;
    } catch (Exception $e) {
        error_log("DEBUG 2FA Email: Error al enviar el código 2FA a " . $email . ": " . $mail->ErrorInfo);
        return false;
    }
}

function verify2faCode($userId, $code) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT twofa_code_expires_at FROM admin_users WHERE id = :id AND twofa_code = :code");
    $stmt->execute(['id' => $userId, 'code' => $code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && strtotime($user['twofa_code_expires_at']) > time()) {
        // Limpiar el código 2FA después de un uso exitoso
        $stmt = $db->prepare("UPDATE admin_users SET twofa_code = NULL, twofa_code_expires_at = NULL WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        return true;
    }
    return false;
}

// Función para obtener la dirección IP del usuario
function getUserIpAddr(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }else{
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Funciones para Rate Limiting
function recordFailedLoginAttempt($username, $ip_address) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (:username, :ip_address, :attempt_time)");
    $stmt->execute([
        'username' => $username,
        'ip_address' => $ip_address,
        'attempt_time' => time()
    ]);
}

function getFailedLoginAttempts($username, $ip_address) {
    $db = getDatabaseConnection();
    $time_limit = time() - LOGIN_BLOCK_TIME;
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE (username = :username OR ip_address = :ip_address) AND attempt_time > :time_limit");
    $stmt->execute([
        'username' => $username,
        'ip_address' => $ip_address,
        'time_limit' => $time_limit
    ]);
    return $stmt->fetchColumn();
}

function isBlocked($username, $ip_address) {
    return getFailedLoginAttempts($username, $ip_address) >= MAX_LOGIN_ATTEMPTS;
}

function clearLoginAttempts($username, $ip_address) {
    $db = getDatabaseConnection();
    // No es necesario iniciar una transacción aquí si solo se ejecuta una sentencia DELETE.
    // PDO ya maneja esto como una transacción implícita o autocommit.
    // Si hubiera múltiples operaciones, entonces sí sería necesaria una transacción explícita.
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE (username = :username AND ip_address = :ip_address) OR attempt_time < :time_limit");
        $stmt->execute([
            'username' => $username,
            'ip_address' => $ip_address,
            'time_limit' => time() - LOGIN_BLOCK_TIME * 2 // Eliminar intentos más antiguos que el doble del tiempo de bloqueo
        ]);
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error al limpiar intentos de login: " . $e->getMessage());
    }
}

// DEBUG: Imprimir el correo electrónico del administrador para verificar
require_once 'db.php'; // Asegurarse de que db.php esté incluido
error_log("DEBUG: Correo electrónico del administrador en config: " . SMTP_FROM_EMAIL);

// Manejo de acciones de autenticación
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    logout();
}

ob_end_flush(); // Enviar el búfer de salida

require_once 'utils.php'; // Incluir funciones de utilidad
?>
