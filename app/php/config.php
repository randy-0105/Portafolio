
<?php
// app/php/config.php

session_start();

// Habilitar la visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_PATH', __DIR__ . '/../sqlite/database.sqlite3');
define('ADMIN_USERNAME', 'admin'); // Nombre de usuario por defecto para el admin
define('ADMIN_PASSWORD', password_hash('adminpass', PASSWORD_DEFAULT)); // Contraseña por defecto para el admin (¡CAMBIAR EN PRODUCCIÓN!)

// Configuración de seguridad
define('CSRF_TOKEN_SECRET', 'tu_secreto_csrf_aqui'); // Cambiar por una cadena aleatoria larga
define('SESSION_LIFETIME', 3600); // Duración de la sesión en segundos (1 hora)

// Configuración de Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5); // Número máximo de intentos de login fallidos
define('LOGIN_BLOCK_TIME', 300); // Tiempo de bloqueo en segundos (5 minutos)

// Configuración de Notificaciones
define('ENABLE_LOGIN_NOTIFICATIONS', true); // Habilitar/deshabilitar notificaciones de login por correo

// Configuración de reCAPTCHA
define('RECAPTCHA_SITE_KEY', '6Ld8xNwrAAAAACaHriO7sZ_4-aNEZ4da5o0z0ZhT');
define('RECAPTCHA_SECRET_KEY', '6Ld8xNwrAAAAAPLwF7uE_r0HRODtpnEGvJAIv9Qf');


// Configuración SMTP para recuperación de contraseña
define('SMTP_SERVER', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'randy.vidovic13@gmail.com');
define('SMTP_PASSWORD', 'ixji vjvm ydly tzdp');
define('SMTP_FROM_EMAIL', 'randy.vidovic13@gmail.com');
define('SMTP_FROM_NAME', 'Administrador del Portafolio');
define('SMTP_TIMEOUT', 30); // Aumentar el tiempo de espera para SMTP a 30 segundos

// Rutas de subida de archivos
define('UPLOAD_DIR_CERTIFICATES', __DIR__ . '/../../public/assets/certificados/');
define('UPLOAD_DIR_DOCUMENTS', __DIR__ . '/../../public/assets/documentos/');
define('UPLOAD_DIR_PROJECTS', __DIR__ . '/../../public/assets/proyectos/');
define('UPLOAD_DIR_PROFILE_PIC', __DIR__ . '/../../public/assets/img/');

// Tipos de archivos permitidos
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
define('ALLOWED_CV_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Tamaño máximo de archivo (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

?>
