<?php
// public/panel-secreto-2025.php - Panel de administración

// Aumentar el tiempo máximo de ejecución para depuración
set_time_limit(120); // 120 segundos (2 minutos)

require_once '../app/php/init_db.php'; // Asegurarse de que la BD esté inicializada
require_once '../app/php/auth.php';
require_once '../app/php/db.php'; // Asegúrate de incluir db.php para las funciones de la base de datos
require_once '../app/php/utils.php'; // Incluir funciones de utilidad
require_once '../app/php/admin_sections.php'; // Incluir funciones de gestión de secciones
// require_once '../app/php/security_utils.php'; // Las funciones de seguridad ahora están en auth.php
require_once '../app/php/admin_sections.php'; // Incluir la lógica de gestión de secciones
checkSessionActivity(); // Verificar la actividad de la sesión

// Si el usuario no está autenticado, mostrar el formulario de login
if (!isAuthenticated()) {
    $error_message = '';
    $login_error = false; // Variable para controlar el estado de error de login
    $username = $_POST['username'] ?? ''; // Obtener el nombre de usuario si está presente
    $ip_address = getUserIpAddr();

    // Si se solicita resetear la sesión 2FA, limpiar la bandera
    if (isset($_GET['action']) && $_GET['action'] === 'reset_2fa_session') {
        unset($_SESSION['2fa_required']);
        unset($_SESSION['2fa_user_id']);
        header("Location: /panel-secreto-2025.php"); // Redirigir a la página de login normal
        exit();
    }

    // Lógica para verificar el código 2FA
    if (isset($_SESSION['2fa_user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
        $user_id_2fa = $_SESSION['2fa_user_id'];
        $twofa_code_input = sanitizeInput($_POST['twofa_code']);

        if (verify2faCode($user_id_2fa, $twofa_code_input)) {
            // 2FA exitoso, establecer sesión completa
            $_SESSION['user_id'] = $user_id_2fa;
            $_SESSION['username'] = getUserUsername($user_id_2fa); // Obtener el username
            $_SESSION['last_activity'] = time();
            unset($_SESSION['2fa_user_id']); // Limpiar ID de usuario temporal 2FA
            unset($_SESSION['2fa_required']); // Limpiar bandera 2FA
            session_regenerate_id(true);
            logAccess('login_success_2fa', $_SESSION['username'], $ip_address, $user_id_2fa);
            header("Location: /panel-secreto-2025.php");
            exit();
        } else {
            logAccess('2fa_failure', $_SESSION['username'] ?? 'N/A', $ip_address, $user_id_2fa, "Código 2FA incorrecto o expirado");
            $error_message = "Código de verificación incorrecto o expirado.";
            $login_error = true;
        }
    }
    // Si se requiere 2FA, mostrar el formulario de 2FA
    else if (isset($_SESSION['2fa_required']) && $_SESSION['2fa_required'] === true) {
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verificación de 2 Pasos</title>
            <link rel="stylesheet" href="./css/styles.css">
            <link rel="stylesheet" href="./css/admin.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        </head>
        <body class="admin-login-body">
            <div class="stars"></div>
            <div class="login-container">
                <h2>Verificación de Dos Pasos</h2>
                <?php if ($error_message): ?>
                    <p class="error-message"><?php echo $error_message; ?></p>
                <?php endif; ?>
                <p>Se ha enviado un código de verificación a tu correo electrónico. Por favor, introdúcelo a continuación.</p>
                <form action="/panel-secreto-2025.php" method="POST">
                    <div class="form-group <?php echo $login_error ? 'error' : ''; ?>">
                        <label for="twofa_code">Código de Verificación:</label>
                        <input type="text" id="twofa_code" name="twofa_code" placeholder="Código 2FA" required autofocus>
                    </div>
                    <button type="submit" name="verify_2fa" class="btn">Verificar Código</button>
                </form>
                <p class="back-to-portfolio"><a href="/panel-secreto-2025.php?action=reset_2fa_session" class="text-link">Regresar al Login</a></p>
                <div id="countdown" style="margin-top: 15px; font-size: 0.9em; color: white;">El código expira en <span id="timer">30</span> segundos.</div>
            </div>
            <script>
                let countdownTime = 30;
                const timerElement = document.getElementById('timer');
                const countdownElement = document.getElementById('countdown');

                function startCountdown() {
                    const countdownInterval = setInterval(() => {
                        countdownTime--;
                        timerElement.textContent = countdownTime;

                        if (countdownTime <= 0) {
                            clearInterval(countdownInterval);
                            countdownElement.innerHTML = 'El código ha expirado. Por favor, regresa al login e inténtalo de nuevo.';
                            // Opcional: deshabilitar el campo de entrada y el botón de verificación
                            document.getElementById('twofa_code').disabled = true;
                            document.querySelector('button[name="verify_2fa"]').disabled = true;
                        }
                    }, 1000);
                }

                // Iniciar el contador cuando la página se carga
                document.addEventListener('DOMContentLoaded', startCountdown);
            </script>
            <script src="./js/main.js"></script>
        </body>
        </html>
        <?php
        exit();
    }
    // Lógica de login normal
    else if (isBlocked($username, $ip_address)) {
        $error_message = "Demasiados intentos de login fallidos. Por favor, inténtalo de nuevo en " . LOGIN_BLOCK_TIME / 60 . " minutos.";
        $login_error = true;
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("Admin Panel: Solicitud POST recibida para login.");
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];

        if (login($username, $password)) {
            // Si el login es exitoso, pero se requiere 2FA, no redirigir aún
            if (isset($_SESSION['2fa_required']) && $_SESSION['2fa_required'] === true) {
                $_SESSION['2fa_user_id'] = $_SESSION['user_id']; // Guardar ID de usuario temporalmente
                unset($_SESSION['user_id']); // Limpiar sesión principal hasta que 2FA sea exitoso
                header("Location: /panel-secreto-2025.php"); // Redirigir al formulario 2FA
                exit();
            } else {
                header("Location: /panel-secreto-2025.php"); // Redirigir al panel si el login es exitoso (sin 2FA)
                exit();
            }
        } else {
            $error_message = "Usuario o contraseña incorrectos.";
            $login_error = true;
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="./css/styles.css"> <!-- Usar los estilos generales -->
        <link rel="stylesheet" href="./css/admin.css"> <!-- Estilos específicos del admin -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </head>
    <body class="admin-login-body">
        <div class="stars"></div> <!-- Contenedor para las estrellas -->
        <div class="login-container">
            <h2>Login Admin</h2>
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <form action="/panel-secreto-2025.php" method="POST">
                <div class="form-group <?php echo $login_error ? 'error' : ''; ?>">
                    <label for="username">Usuario:</label>
                    <input type="text" id="username" name="username" placeholder="Usuario" required>
                </div>
                <div class="form-group <?php echo $login_error ? 'error' : ''; ?>">
                    <label for="password">Contraseña:</label>
                    <div class="password-input-container">
                        <input type="password" id="password" name="password" placeholder="Contraseña" required>
                        <span class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>
            <p class="back-to-portfolio"><a href="/" class="text-link">Volver al Portafolio</a></p>
            <p class="forgot-password"><a href="/recaptcha_verify.php?flow=forgot_password" class="text-link">¿Olvidaste tu contraseña?</a></p>
        </div>
        <script>
            function togglePasswordVisibility() {
                const passwordField = document.getElementById('password');
                const toggleButton = document.querySelector('.toggle-password i');
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    toggleButton.classList.remove('fa-eye');
                    toggleButton.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    toggleButton.classList.remove('fa-eye-slash');
                    toggleButton.classList.add('fa-eye');
                }
            }
        </script>
        <script src="./js/main.js"></script> <!-- Incluir main.js para las estrellas -->
    </body>
    </html>
    <?php
    exit(); // Detener la ejecución después de mostrar el formulario de login
}

// Si el usuario está autenticado, mostrar el contenido del panel de administración
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="./css/styles.css">
    <link rel="stylesheet" href="./css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="container admin-header-content">
            <div class="admin-header-top">
                <nav class="admin-nav">
                    <ul>
                        <li><a href="panel-secreto-2025.php?section=personal_info">Información Personal</a></li>
                        <li><a href="panel-secreto-2025.php?section=experience">Experiencia</a></li>
                        <li><a href="panel-secreto-2025.php?section=education">Educación</a></li>
                        <li><a href="panel-secreto-2025.php?section=skills">Habilidades</a></li>
                        <li><a href="panel-secreto-2025.php?section=projects">Proyectos</a></li>
                        <li><a href="panel-secreto-2025.php?section=certificates">Certificados</a></li>
                        <li><a href="panel-secreto-2025.php?section=documents">Documentos</a></li>
                        <li><a href="panel-secreto-2025.php?section=other_webs">Otras Webs</a></li>
                        <li><a href="panel-secreto-2025.php?section=upload_cv">Cargar CV</a></li>
                    </ul>
                </nav>
                <div class="admin-user-menu">
                    <button class="admin-user-btn">
                        <i class="fas fa-user-shield"></i> <!-- Icono de usuario administrador -->
                    </button>
                    <ul class="admin-dropdown-menu">
                        <li class="dropdown-submenu">
                            <a href="#">Seguridad <i class="fas fa-caret-right"></i></a>
                            <ul class="submenu">
                                <li><a href="panel-secreto-2025.php?section=change_password">Cambiar Contraseña</a></li>
                            </ul>
                        </li>
                        <li><a href="#">Temas</a></li>
                        <li><a href="panel-secreto-2025.php?section=edit_sections">Editar Secciones</a></li>
                        <li><a href="panel-secreto-2025.php?section=analytics">Análisis de Visualización</a></li>
                        <li><a href="panel-secreto-2025.php?action=logout">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
            <h1 class="admin-panel-title">Panel de Administración</h1>
        </div>
    </header>

    <main class="admin-main">
        <div class="container">
            <?php
            $section_slug = $_GET['section'] ?? 'dashboard'; // Sección por defecto
            // Los mensajes de bienvenida y selección de sección se eliminarán o se moverán si es necesario.
            // echo "<h2>Bienvenido, " . htmlspecialchars($_SESSION['username']) . "</h2>";
            // echo "<p>Selecciona una sección del menú para gestionar el contenido.</p>";

            switch ($section_slug) {
                case 'personal_info':
                    echo "<h3>Gestión de Información Personal</h3>";
                    $personal_info = getPersonalInfo();
                    $upload_message = '';
                    $contact_methods = getContactMethods(); // Obtener métodos de contacto

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['update_personal_info'])) {
                                $data = [
                                    'name' => sanitizeInput($_POST['name']),
                                    'profile_summary' => sanitizeInput($_POST['profile_summary'])
                                ];

                                // Manejar la subida de la imagen de perfil si existe
                                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                                    require_once '../app/php/upload.php';
                                    $uploadResult = handleProfileImageUpload('profile_image');
                                    if ($uploadResult['success']) {
                                        $data['profile_image_path'] = $uploadResult['file_path'];
                                        $upload_message = "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                                    } else {
                                        $upload_message = "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                                    }
                                }

                                if (updateTable('personal_info', $data, $personal_info['id'] ?? 1)) {
                                    echo "<p class='success-message'>Información personal actualizada exitosamente.</p>";
                                    $personal_info = getPersonalInfo(); // Recargar datos
                                } else {
                                    echo "<p class='error-message'>Error al actualizar la información personal.</p>";
                                }

                                // Actualizar métodos de contacto existentes
                                if (isset($_POST['contact_id'])) {
                                    foreach ($_POST['contact_id'] as $index => $contact_id) {
                                        $contact_data = [
                                            'type' => sanitizeInput($_POST['contact_type'][$index]),
                                            'value' => sanitizeInput($_POST['contact_value'][$index])
                                        ];
                                        updateTable('contact_methods', $contact_data, $contact_id);
                                    }
                                }

                                // Añadir nuevos métodos de contacto
                                if (isset($_POST['new_contact_type'])) {
                                    foreach ($_POST['new_contact_type'] as $index => $new_type) {
                                        $new_value = sanitizeInput($_POST['new_contact_value'][$index]);
                                        if (!empty($new_type) && !empty($new_value)) {
                                            insertIntoTable('contact_methods', ['type' => $new_type, 'value' => $new_value]);
                                        }
                                    }
                                }

                                // Eliminar métodos de contacto
                                if (isset($_POST['delete_contact_id'])) {
                                    foreach ($_POST['delete_contact_id'] as $delete_id) {
                                        deleteFromTable('contact_methods', (int)$delete_id);
                                    }
                                }

                                $contact_methods = getContactMethods(); // Recargar métodos de contacto
                            }
                        }
                    } elseif (isset($_POST['reorder_contact_methods'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF inválido.']);
                            exit();
                        }
                        $order = json_decode($_POST['contact_methods_order'], true);
                        if (reorderContactMethods($order)) {
                            echo json_encode(['success' => true, 'message' => 'Orden de métodos de contacto actualizado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar el orden de los métodos de contacto.']);
                        }
                        exit(); // Terminar la ejecución después de la respuesta AJAX
                    }
                    ?>
                    <?php if ($upload_message) echo $upload_message; ?>
                    <form action="panel-secreto-2025.php?section=personal_info" method="POST" enctype="multipart/form-data" class="admin-form personal-info-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="personal-info-header-actions">
                            <button type="button" class="btn btn-small">AGREGAR MÁS CONTENIDO</button>
                            <button type="button" class="btn btn-small">EDITAR</button>
                            <button type="submit" name="update_personal_info" class="btn btn-small">GUARDAR CAMBIOS</button>
                        </div>

                        <div class="form-group profile-image-group">
                            <label>FOTO DE PERFIL:</label>
                            <div class="profile-image-preview-container">
                                <?php if (!empty($personal_info['profile_image_path'])): ?>
                                    <img src="./assets/img/<?php echo htmlspecialchars($personal_info['profile_image_path']); ?>" alt="Foto de Perfil Actual" class="profile-image-preview" id="profile-image-preview">
                                <?php else: ?>
                                    <img src="./assets/img/default-profile.png" alt="Sin Foto" class="profile-image-preview" id="profile-image-preview">
                                <?php endif; ?>
                            </div>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" onchange="previewProfileImage(event)">
                            <label for="profile_image" class="btn btn-small">Elegir archivo</label>
                            <span id="profile-image-name"><?php echo !empty($personal_info['profile_image_path']) ? htmlspecialchars($personal_info['profile_image_path']) : 'No se ha seleccionado ningún archivo'; ?></span>
                        </div>

                        <div class="form-group">
                            <label for="name">NOMBRE COMPLETO:</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($personal_info['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="profile_summary">RESUMEN PROFESIONAL:</label>
                            <textarea id="profile_summary" name="profile_summary" rows="10" required><?php echo htmlspecialchars($personal_info['profile_summary'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>MÉTODOS DE CONTACTO:</label>
                            <div id="contact-methods-container" class="sortable-list">
                                <?php if (!empty($contact_methods)): ?>
                                    <?php foreach ($contact_methods as $contact): ?>
                                        <div class="contact-item" data-id="<?php echo $contact['id']; ?>">
                                            <input type="hidden" name="contact_id[]" value="<?php echo $contact['id']; ?>">
                                            <select name="contact_type[]">
                                                <option value="phone" <?php echo ($contact['type'] == 'phone') ? 'selected' : ''; ?>>Número Telefónico</option>
                                                <option value="email" <?php echo ($contact['type'] == 'email') ? 'selected' : ''; ?>>Correo Electrónico</option>
                                                <option value="linkedin" <?php echo ($contact['type'] == 'linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
                                                <option value="github" <?php echo ($contact['type'] == 'github') ? 'selected' : ''; ?>>GitHub</option>
                                                <option value="website" <?php echo ($contact['type'] == 'website') ? 'selected' : ''; ?>>Sitio Web</option>
                                            </select>
                                            <input type="text" name="contact_value[]" value="<?php echo htmlspecialchars($contact['value']); ?>" required>
                                            <button type="button" class="btn btn-small btn-delete" onclick="removeContactMethod(this, <?php echo $contact['id']; ?>)">Eliminar</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No hay métodos de contacto registrados.</p>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-small btn-add-contact" onclick="addContactMethod()">+ AGREGAR MÉTODO DE CONTACTO</button>
                            <button type="button" class="btn btn-small btn-edit-order" onclick="toggleContactOrderEdit()">Editar Orden</button>
                        </div>
                    </form>

                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        function previewProfileImage(event) {
                            const reader = new FileReader();
                            reader.onload = function() {
                                const output = document.getElementById('profile-image-preview');
                                output.src = reader.result;
                            };
                            reader.readAsDataURL(event.target.files[0]);
                            document.getElementById('profile-image-name').textContent = event.target.files[0].name;
                        }

                        let newContactIndex = 0;
                        function addContactMethod() {
                            const container = document.getElementById('contact-methods-container');
                            const newContactDiv = document.createElement('div');
                            newContactDiv.classList.add('contact-item');
                            newContactDiv.innerHTML = `
                                <select name="new_contact_type[]">
                                    <option value="phone">Número Telefónico</option>
                                    <option value="email">Correo Electrónico</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="github">GitHub</option>
                                    <option value="website">Sitio Web</option>
                                </select>
                                <input type="text" name="new_contact_value[]" placeholder="Valor del contacto" required>
                                <button type="button" class="btn btn-small btn-delete" onclick="removeNewContactMethod(this)">Eliminar</button>
                            `;
                            container.appendChild(newContactDiv);
                            newContactIndex++;
                        }

                        function removeContactMethod(button, id) {
                            if (confirm('¿Estás seguro de que quieres eliminar este método de contacto?')) {
                                const form = button.closest('form');
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'delete_contact_id[]';
                                input.value = id;
                                form.appendChild(input);
                                button.closest('.contact-item').remove();
                            }
                        }

                        function removeNewContactMethod(button) {
                            button.closest('.contact-item').remove();
                        }

                        let isContactOrderEditing = false;
                        function toggleContactOrderEdit() {
                            const container = $('#contact-methods-container');
                            const editButton = $('.btn-edit-order');

                            if (!isContactOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.contact-item', // Permite arrastrar desde cualquier parte del item
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=personal_info", {
                                            csrf_token: csrfToken,
                                            reorder_contact_methods: true,
                                            contact_methods_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                            // No recargar la página, solo actualizar visualmente si es necesario
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isContactOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isContactOrderEditing = false;
                                // Aquí podrías enviar el orden final si no lo hiciste en cada 'update'
                                // o simplemente confiar en que el 'update' ya lo envió.
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'experience':
                    echo "<h3>Gestión de Experiencia</h3>";
                    $experiences = getExperience();
                    $message = '';

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            $message = "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['add_experience'])) {
                                $data = [
                                    'title' => sanitizeInput($_POST['title']),
                                    'company' => sanitizeInput($_POST['company']),
                                    'start_date' => sanitizeInput($_POST['start_date']),
                                    'end_date' => sanitizeInput($_POST['end_date']),
                                    'location' => sanitizeInput($_POST['location']),
                                    'description' => sanitizeInput($_POST['description'])
                                ];
                                if (insertIntoTable('experience', $data)) {
                                    $message = "<p class='success-message'>Experiencia añadida exitosamente.</p>";
                                    $experiences = getExperience(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al añadir experiencia.</p>";
                                }
                            } elseif (isset($_POST['edit_experience'])) {
                                $id = (int)$_POST['id'];
                                $data = [
                                    'title' => sanitizeInput($_POST['title']),
                                    'company' => sanitizeInput($_POST['company']),
                                    'start_date' => sanitizeInput($_POST['start_date']),
                                    'end_date' => sanitizeInput($_POST['end_date']),
                                    'location' => sanitizeInput($_POST['location']),
                                    'description' => sanitizeInput($_POST['description'])
                                ];
                                if (updateTable('experience', $data, $id)) {
                                    $message = "<p class='success-message'>Experiencia actualizada exitosamente.</p>";
                                    $experiences = getExperience(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al actualizar experiencia.</p>";
                                }
                            } elseif (isset($_POST['delete_experience'])) {
                                $id = (int)$_POST['id'];
                                if (deleteFromTable('experience', $id)) {
                                    $message = "<p class='success-message'>Experiencia eliminada exitosamente.</p>";
                                    $experiences = getExperience(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al eliminar experiencia.</p>";
                                }
                            }
                        }
                    } elseif (isset($_POST['reorder_experiences'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF inválido.']);
                            exit();
                        }
                        $order = json_decode($_POST['experiences_order'], true);
                        if (reorderExperience($order)) {
                            echo json_encode(['success' => true, 'message' => 'Orden de experiencias actualizado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar el orden de las experiencias.']);
                        }
                        exit(); // Terminar la ejecución después de la respuesta AJAX
                    }
                    ?>
                    <?php if ($message): echo $message; endif; ?>

                    <h4>Añadir Nueva Experiencia</h4>
                    <form action="panel-secreto-2025.php?section=experience" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="exp_title">Título:</label>
                            <input type="text" id="exp_title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="exp_company">Empresa:</label>
                            <input type="text" id="exp_company" name="company" required>
                        </div>
                        <div class="form-group">
                            <label for="exp_start_date">Fecha de Inicio:</label>
                            <input type="text" id="exp_start_date" name="start_date" placeholder="Ej: Ene 2020" required>
                        </div>
                        <div class="form-group">
                            <label for="exp_end_date">Fecha de Fin (o "Presente"):</label>
                            <input type="text" id="exp_end_date" name="end_date" placeholder="Ej: Dic 2023 o Presente">
                        </div>
                        <div class="form-group">
                            <label for="exp_location">Ubicación:</label>
                            <input type="text" id="exp_location" name="location" placeholder="Ej: Maracaibo">
                        </div>
                        <div class="form-group">
                            <label for="exp_description">Descripción:</label>
                            <textarea id="exp_description" name="description" rows="5"></textarea>
                        </div>
                        <button type="submit" name="add_experience" class="btn">Añadir Experiencia</button>
                    </form>

                    <h4>Experiencias Existentes <button type="button" class="btn btn-small btn-edit-order" onclick="toggleExperienceOrderEdit()">Editar Orden</button></h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Título</th>
                                    <th>Empresa</th>
                                    <th>Fechas</th>
                                    <th>Ubicación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="experiences-sortable">
                                <?php if (!empty($experiences)): ?>
                                    <?php foreach ($experiences as $exp): ?>
                                        <tr data-id="<?php echo $exp['id']; ?>">
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i></td>
                                            <td><?php echo htmlspecialchars($exp['title']); ?></td>
                                            <td><?php echo htmlspecialchars($exp['company']); ?></td>
                                            <td><?php echo htmlspecialchars($exp['start_date'] . ' - ' . $exp['end_date']); ?></td>
                                            <td><?php echo htmlspecialchars($exp['location']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-small btn-edit" onclick="showEditExperienceForm(<?php echo $exp['id']; ?>)">Editar</button>
                                                <form action="panel-secreto-2025.php?section=experience" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                                                    <button type="submit" name="delete_experience" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta experiencia?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="edit-experience-form-<?php echo $exp['id']; ?>" style="display:none;">
                                            <td colspan="6">
                                                <div class="edit-form-container">
                                                    <h5>Editar Experiencia</h5>
                                                    <form action="panel-secreto-2025.php?section=experience" method="POST" class="admin-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="edit_exp_title_<?php echo $exp['id']; ?>">Título:</label>
                                                            <input type="text" id="edit_exp_title_<?php echo $exp['id']; ?>" name="title" value="<?php echo htmlspecialchars($exp['title']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_exp_company_<?php echo $exp['id']; ?>">Empresa:</label>
                                                            <input type="text" id="edit_exp_company_<?php echo $exp['id']; ?>" name="company" value="<?php echo htmlspecialchars($exp['company']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_exp_start_date_<?php echo $exp['id']; ?>">Fecha de Inicio:</label>
                                                            <input type="text" id="edit_exp_start_date_<?php echo $exp['id']; ?>" name="start_date" value="<?php echo htmlspecialchars($exp['start_date']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_exp_end_date_<?php echo $exp['id']; ?>">Fecha de Fin (o "Presente"):</label>
                                                            <input type="text" id="edit_exp_end_date_<?php echo $exp['id']; ?>" name="end_date" value="<?php echo htmlspecialchars($exp['end_date']); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_exp_location_<?php echo $exp['id']; ?>">Ubicación:</label>
                                                            <input type="text" id="edit_exp_location_<?php echo $exp['id']; ?>" name="location" value="<?php echo htmlspecialchars($exp['location']); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_exp_description_<?php echo $exp['id']; ?>">Descripción:</label>
                                                            <textarea id="edit_exp_description_<?php echo $exp['id']; ?>" name="description" rows="5"><?php echo htmlspecialchars($exp['description']); ?></textarea>
                                                        </div>
                                                        <button type="submit" name="edit_experience" class="btn btn-small">Guardar Cambios</button>
                                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideEditExperienceForm(<?php echo $exp['id']; ?>)">Cancelar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">No hay experiencias registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        function showEditExperienceForm(id) {
                            $(`#edit-experience-form-${id}`).show();
                        }

                        function hideEditExperienceForm(id) {
                            $(`#edit-experience-form-${id}`).hide();
                        }

                        let isExperienceOrderEditing = false;
                        function toggleExperienceOrderEdit() {
                            const container = $('#experiences-sortable');
                            const editButton = $('.btn-edit-order');

                            if (!isExperienceOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.order-handle',
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=experience", {
                                            csrf_token: csrfToken,
                                            reorder_experiences: true,
                                            experiences_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                            // No recargar la página, solo actualizar visualmente si es necesario
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isExperienceOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isExperienceOrderEditing = false;
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'education':
                    echo "<h3>Gestión de Educación</h3>";
                    $education_entries = getEducation();
                    $message = '';

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            $message = "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['add_education'])) {
                                $data = [
                                    'degree' => sanitizeInput($_POST['degree']),
                                    'institution' => sanitizeInput($_POST['institution']),
                                    'start_date' => sanitizeInput($_POST['start_date']),
                                    'end_date' => sanitizeInput($_POST['end_date']),
                                    'location' => sanitizeInput($_POST['location'])
                                ];
                                if (insertIntoTable('education', $data)) {
                                    $message = "<p class='success-message'>Entrada de educación añadida exitosamente.</p>";
                                    $education_entries = getEducation(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al añadir entrada de educación.</p>";
                                }
                            } elseif (isset($_POST['edit_education'])) {
                                $id = (int)$_POST['id'];
                                $data = [
                                    'degree' => sanitizeInput($_POST['degree']),
                                    'institution' => sanitizeInput($_POST['institution']),
                                    'start_date' => sanitizeInput($_POST['start_date']),
                                    'end_date' => sanitizeInput($_POST['end_date']),
                                    'location' => sanitizeInput($_POST['location'])
                                ];
                                if (updateTable('education', $data, $id)) {
                                    $message = "<p class='success-message'>Entrada de educación actualizada exitosamente.</p>";
                                    $education_entries = getEducation(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al actualizar entrada de educación.</p>";
                                }
                            } elseif (isset($_POST['delete_education'])) {
                                $id = (int)$_POST['id'];
                                if (deleteFromTable('education', $id)) {
                                    $message = "<p class='success-message'>Entrada de educación eliminada exitosamente.</p>";
                                    $education_entries = getEducation(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al eliminar entrada de educación.</p>";
                                }
                            }
                        }
                    }
                    ?>
                    <?php if ($message): echo $message; endif; ?>

                    <h4>Añadir Nueva Entrada de Educación</h4>
                    <form action="panel-secreto-2025.php?section=education" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="edu_degree">Título/Grado:</label>
                            <input type="text" id="edu_degree" name="degree" required>
                        </div>
                        <div class="form-group">
                            <label for="edu_institution">Institución:</label>
                            <input type="text" id="edu_institution" name="institution" required>
                        </div>
                        <div class="form-group">
                            <label for="edu_start_date">Fecha de Inicio:</label>
                            <input type="text" id="edu_start_date" name="start_date" placeholder="Ej: Sep 2021" required>
                        </div>
                        <div class="form-group">
                            <label for="edu_end_date">Fecha de Fin (o "Presente"):</label>
                            <input type="text" id="edu_end_date" name="end_date" placeholder="Ej: Jun 2025 o Presente">
                        </div>
                        <div class="form-group">
                            <label for="edu_location">Ubicación:</label>
                            <input type="text" id="edu_location" name="location" placeholder="Ej: Maracaibo">
                        </div>
                        <button type="submit" name="add_education" class="btn">Añadir Educación</button>
                    </form>

                    <h4>Entradas de Educación Existentes <button type="button" class="btn btn-small btn-edit-order" onclick="toggleEducationOrderEdit()">Editar Orden</button></h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Título/Grado</th>
                                    <th>Institución</th>
                                    <th>Fechas</th>
                                    <th>Ubicación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="education-sortable">
                                <?php if (!empty($education_entries)): ?>
                                    <?php foreach ($education_entries as $edu): ?>
                                        <tr data-id="<?php echo $edu['id']; ?>">
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i></td>
                                            <td><?php echo htmlspecialchars($edu['degree']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['institution']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['start_date'] . ' - ' . $edu['end_date']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['location']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-small btn-edit" onclick="showEditEducationForm(<?php echo $edu['id']; ?>)">Editar</button>
                                                <form action="panel-secreto-2025.php?section=education" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $edu['id']; ?>">
                                                    <button type="submit" name="delete_education" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta entrada de educación?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="edit-education-form-<?php echo $edu['id']; ?>" style="display:none;">
                                            <td colspan="6">
                                                <div class="edit-form-container">
                                                    <h5>Editar Educación</h5>
                                                    <form action="panel-secreto-2025.php?section=education" method="POST" class="admin-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $edu['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="edit_edu_degree_<?php echo $edu['id']; ?>">Título/Grado:</label>
                                                            <input type="text" id="edit_edu_degree_<?php echo $edu['id']; ?>" name="degree" value="<?php echo htmlspecialchars($edu['degree']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_edu_institution_<?php echo $edu['id']; ?>">Institución:</label>
                                                            <input type="text" id="edit_edu_institution_<?php echo $edu['id']; ?>" name="institution" value="<?php echo htmlspecialchars($edu['institution']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_edu_start_date_<?php echo $edu['id']; ?>">Fecha de Inicio:</label>
                                                            <input type="text" id="edit_edu_start_date_<?php echo $edu['id']; ?>" name="start_date" value="<?php echo htmlspecialchars($edu['start_date']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_edu_end_date_<?php echo $edu['id']; ?>">Fecha de Fin (o "Presente"):</label>
                                                            <input type="text" id="edit_edu_end_date_<?php echo $edu['id']; ?>" name="end_date" value="<?php echo htmlspecialchars($edu['end_date']); ?>">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_edu_location_<?php echo $edu['id']; ?>">Ubicación:</label>
                                                            <input type="text" id="edit_edu_location_<?php echo $edu['id']; ?>" name="location" value="<?php echo htmlspecialchars($edu['location']); ?>">
                                                        </div>
                                                        <button type="submit" name="edit_education" class="btn btn-small">Guardar Cambios</button>
                                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideEditEducationForm(<?php echo $edu['id']; ?>)">Cancelar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">No hay entradas de educación registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        function showEditEducationForm(id) {
                            $(`#edit-education-form-${id}`).show();
                        }

                        function hideEditEducationForm(id) {
                            $(`#edit-education-form-${id}`).hide();
                        }

                        let isEducationOrderEditing = false;
                        function toggleEducationOrderEdit() {
                            const container = $('#education-sortable');
                            const editButton = $('.btn-edit-order');

                            if (!isEducationOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.order-handle',
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=education", {
                                            csrf_token: csrfToken,
                                            reorder_education: true,
                                            education_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isEducationOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isEducationOrderEditing = false;
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'skills':
                    echo "<h3>Gestión de Habilidades</h3>";
                    $skills = getSkills();
                    $message = '';

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            $message = "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['add_skill'])) {
                                $data = [
                                    'category' => sanitizeInput($_POST['category']),
                                    'name' => sanitizeInput($_POST['name']),
                                    'level' => sanitizeInput($_POST['level'])
                                ];
                                if (insertIntoTable('skills', $data)) {
                                    $message = "<p class='success-message'>Habilidad añadida exitosamente.</p>";
                                    $skills = getSkills(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al añadir habilidad.</p>";
                                }
                            } elseif (isset($_POST['edit_skill'])) {
                                $id = (int)$_POST['id'];
                                $data = [
                                    'category' => sanitizeInput($_POST['category']),
                                    'name' => sanitizeInput($_POST['name']),
                                    'level' => sanitizeInput($_POST['level'])
                                ];
                                if (updateTable('skills', $data, $id)) {
                                    $message = "<p class='success-message'>Habilidad actualizada exitosamente.</p>";
                                    $skills = getSkills(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al actualizar habilidad.</p>";
                                }
                            } elseif (isset($_POST['delete_skill'])) {
                                $id = (int)$_POST['id'];
                                if (deleteFromTable('skills', $id)) {
                                    $message = "<p class='success-message'>Habilidad eliminada exitosamente.</p>";
                                    $skills = getSkills(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al eliminar habilidad.</p>";
                                }
                            }
                            // Aquí es donde se insertará el bloque de reordenamiento
                        }
                    }
                    ?>
                    <?php if ($message): echo $message; endif; ?>

                    <h4>Añadir Nueva Habilidad</h4>
                    <form action="panel-secreto-2025.php?section=skills" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="skill_category">Categoría:</label>
                            <input type="text" id="skill_category" name="category" placeholder="Ej: Lenguajes de Programación" required>
                        </div>
                        <div class="form-group">
                            <label for="skill_name">Nombre de la Habilidad:</label>
                            <input type="text" id="skill_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="skill_level">Nivel:</label>
                            <input type="text" id="skill_level" name="level" placeholder="Ej: Experiencia, Intermedio">
                        </div>
                        <button type="submit" name="add_skill" class="btn">Añadir Habilidad</button>
                    </form>

                    <h4>Habilidades Existentes <button type="button" class="btn btn-small btn-edit-order" onclick="toggleSkillOrderEdit()">Editar Orden</button></h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Categoría</th>
                                    <th>Nombre</th>
                                    <th>Nivel</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="skills-sortable">
                                <?php if (!empty($skills)): ?>
                                    <?php foreach ($skills as $skill): ?>
                                        <tr data-id="<?php echo $skill['id']; ?>">
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i></td>
                                            <td><?php echo htmlspecialchars($skill['category']); ?></td>
                                            <td><?php echo htmlspecialchars($skill['name']); ?></td>
                                            <td><?php echo htmlspecialchars($skill['level']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-small btn-edit" onclick="showEditSkillForm(<?php echo $skill['id']; ?>)">Editar</button>
                                                <form action="panel-secreto-2025.php?section=skills" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $skill['id']; ?>">
                                                    <button type="submit" name="delete_skill" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta habilidad?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="edit-skill-form-<?php echo $skill['id']; ?>" style="display:none;">
                                            <td colspan="5">
                                                <div class="edit-form-container">
                                                    <h5>Editar Habilidad</h5>
                                                    <form action="panel-secreto-2025.php?section=skills" method="POST" class="admin-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $skill['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="edit_skill_category_<?php echo $skill['id']; ?>">Categoría:</label>
                                                            <input type="text" id="edit_skill_category_<?php echo $skill['id']; ?>" name="category" value="<?php echo htmlspecialchars($skill['category']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_skill_name_<?php echo $skill['id']; ?>">Nombre de la Habilidad:</label>
                                                            <input type="text" id="edit_skill_name_<?php echo $skill['id']; ?>" name="name" value="<?php echo htmlspecialchars($skill['name']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_skill_level_<?php echo $skill['id']; ?>">Nivel:</label>
                                                            <input type="text" id="edit_skill_level_<?php echo $skill['id']; ?>" name="level" value="<?php echo htmlspecialchars($skill['level']); ?>">
                                                        </div>
                                                        <button type="submit" name="edit_skill" class="btn btn-small">Guardar Cambios</button>
                                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideEditSkillForm(<?php echo $skill['id']; ?>)">Cancelar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No hay habilidades registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        function showEditSkillForm(id) {
                            $(`#edit-skill-form-${id}`).show();
                        }

                        function hideEditSkillForm(id) {
                            $(`#edit-skill-form-${id}`).hide();
                        }

                        let isSkillOrderEditing = false;
                        function toggleSkillOrderEdit() {
                            const container = $('#skills-sortable');
                            const editButton = $('.btn-edit-order');

                            if (!isSkillOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.order-handle',
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=skills", {
                                            csrf_token: csrfToken,
                                            reorder_skills: true,
                                            skills_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isSkillOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isSkillOrderEditing = false;
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'projects':
                    echo "<h3>Gestión de Proyectos</h3>";
                    $projects = getProjects();
                    $message = '';

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            $message = "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['add_project'])) {
                                $data = [
                                    'title' => sanitizeInput($_POST['title']),
                                    'start_date' => sanitizeInput($_POST['start_date']),
                                    'end_date' => sanitizeInput($_POST['end_date']),
                                    'description' => sanitizeInput($_POST['description']),
                                    'url' => sanitizeInput($_POST['url'])
                                ];

                                // Manejar la subida de la imagen de proyecto si existe
                                $project_image_path = null;
                                if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
                                    require_once '../app/php/upload.php';
                                    $uploadResult = handleFileUpload('project_image', 'UPLOAD_DIR_PROJECTS', 'ALLOWED_IMAGE_TYPES', 'project_image');
                                    if ($uploadResult['success']) {
                                        $project_image_path = $uploadResult['file_path'];
                                        $message .= "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                                    } else {
                                        $message .= "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                                    }
                                }
                                if ($project_image_path) {
                                    $data['image_path'] = $project_image_path;
                                }

                                if (insertIntoTable('projects', $data)) {
                                    $message .= "<p class='success-message'>Proyecto añadido exitosamente.</p>";
                                    $projects = getProjects(); // Recargar datos
                                } else {
                                    $message .= "<p class='error-message'>Error al añadir proyecto.</p>";
                                }
                            } elseif (isset($_POST['edit_project'])) {
                                $id = (int)$_POST['id'];
                                $data = [
                                    'title' => sanitizeInput($_POST['title']),
                                    'start_date' => sanitizeInput($_POST['start_date']),
                                    'end_date' => sanitizeInput($_POST['end_date']),
                                    'description' => sanitizeInput($_POST['description']),
                                    'url' => sanitizeInput($_POST['url'])
                                ];

                                // Manejar la subida de la imagen de proyecto si existe
                                $project_image_path = null;
                                if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] === UPLOAD_ERR_OK) {
                                    require_once '../app/php/upload.php';
                                    $uploadResult = handleFileUpload('project_image', 'UPLOAD_DIR_PROJECTS', 'ALLOWED_IMAGE_TYPES', 'project_image');
                                    if ($uploadResult['success']) {
                                        $project_image_path = $uploadResult['file_path'];
                                        $message .= "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                                    } else {
                                        $message .= "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                                    }
                                }
                                if ($project_image_path) {
                                    $data['image_path'] = $project_image_path;
                                }

                                if (updateTable('projects', $data, $id)) {
                                    $message .= "<p class='success-message'>Proyecto actualizado exitosamente.</p>";
                                    $projects = getProjects(); // Recargar datos
                                } else {
                                    $message .= "<p class='error-message'>Error al actualizar proyecto.</p>";
                                }
                            } elseif (isset($_POST['delete_project'])) {
                                $id = (int)$_POST['id'];
                                // Obtener la ruta de la imagen para eliminarla físicamente
                                $projectToDelete = fetchByIdFromTable('projects', $id);
                                if ($projectToDelete && !empty($projectToDelete['image_path'])) {
                                    $filePath = UPLOAD_DIR_PROJECTS . $projectToDelete['image_path'];
                                    if (file_exists($filePath)) {
                                        unlink($filePath);
                                    }
                                }

                                if (deleteFromTable('projects', $id)) {
                                    $message = "<p class='success-message'>Proyecto eliminado exitosamente.</p>";
                                    $projects = getProjects(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al eliminar proyecto.</p>";
                                }
                            }
                        }
                    } elseif (isset($_POST['reorder_projects'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF inválido.']);
                            exit();
                        }
                        $order = json_decode($_POST['projects_order'], true);
                        if (reorderProjects($order)) {
                            echo json_encode(['success' => true, 'message' => 'Orden de proyectos actualizado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar el orden de los proyectos.']);
                        }
                        exit(); // Terminar la ejecución después de la respuesta AJAX
                    }
                    ?>
                    <?php if ($message): echo $message; endif; ?>

                    <h4>Añadir Nuevo Proyecto</h4>
                    <form action="panel-secreto-2025.php?section=projects" method="POST" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="project_title">Título:</label>
                            <input type="text" id="project_title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="project_start_date">Fecha de Inicio:</label>
                            <input type="text" id="project_start_date" name="start_date" placeholder="Ej: Sep 2025" required>
                        </div>
                        <div class="form-group">
                            <label for="project_end_date">Fecha de Fin (o "Presente"):</label>
                            <input type="text" id="project_end_date" name="end_date" placeholder="Ej: Presente">
                        </div>
                        <div class="form-group">
                            <label for="project_description">Descripción:</label>
                            <textarea id="project_description" name="description" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="project_url">URL (opcional):</label>
                            <input type="url" id="project_url" name="url" placeholder="Ej: https://ejemplo.com">
                        </div>
                        <div class="form-group">
                            <label for="project_image">Imagen del Proyecto (opcional):</label>
                            <input type="file" id="project_image" name="project_image" accept="image/*">
                        </div>
                        <button type="submit" name="add_project" class="btn">Añadir Proyecto</button>
                    </form>

                    <h4>Proyectos Existentes <button type="button" class="btn btn-small btn-edit-order" onclick="toggleProjectOrderEdit()">Editar Orden</button></h4>
                    <div class="project-cards-container sortable-list" id="projects-sortable">
                        <?php if (!empty($projects)): ?>
                            <?php foreach ($projects as $project): ?>
                                <div class="project-card" data-id="<?php echo $project['id']; ?>">
                                    <div class="order-handle"><i class="fas fa-grip-vertical"></i></div>
                                    <?php if (!empty($project['image_path'])): ?>
                                        <img src="./assets/proyectos/<?php echo htmlspecialchars($project['image_path']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>" class="project-thumbnail" onclick="openModalImage('./assets/proyectos/<?php echo htmlspecialchars($project['image_path']); ?>')">
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($project['title']); ?></h5>
                                    <p class="project-dates"><?php echo htmlspecialchars($project['start_date'] . ' - ' . $project['end_date']); ?></p>
                                    <p class="project-description"><?php echo htmlspecialchars($project['description']); ?></p>
                                    <?php if (!empty($project['url'])): ?>
                                        <a href="<?php echo htmlspecialchars($project['url']); ?>" target="_blank" rel="noopener" class="project-link">Ver Proyecto</a>
                                    <?php endif; ?>
                                    <div class="project-actions">
                                        <button type="button" class="btn btn-small btn-edit" onclick="showEditProjectForm(<?php echo $project['id']; ?>)">Editar</button>
                                        <form action="panel-secreto-2025.php?section=projects" method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                            <button type="submit" name="delete_project" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar este proyecto?');">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                                <div id="edit-project-form-<?php echo $project['id']; ?>" class="edit-form-container" style="display:none;">
                                    <h5>Editar Proyecto</h5>
                                    <form action="panel-secreto-2025.php?section=projects" method="POST" enctype="multipart/form-data" class="admin-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                        <div class="form-group">
                                            <label for="edit_project_title_<?php echo $project['id']; ?>">Título:</label>
                                            <input type="text" id="edit_project_title_<?php echo $project['id']; ?>" name="title" value="<?php echo htmlspecialchars($project['title']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_project_start_date_<?php echo $project['id']; ?>">Fecha de Inicio:</label>
                                            <input type="text" id="edit_project_start_date_<?php echo $project['id']; ?>" name="start_date" value="<?php echo htmlspecialchars($project['start_date']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_project_end_date_<?php echo $project['id']; ?>">Fecha de Fin (o "Presente"):</label>
                                            <input type="text" id="edit_project_end_date_<?php echo $project['id']; ?>" name="end_date" value="<?php echo htmlspecialchars($project['end_date']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_project_description_<?php echo $project['id']; ?>">Descripción:</label>
                                            <textarea id="edit_project_description_<?php echo $project['id']; ?>" name="description" rows="5"><?php echo htmlspecialchars($project['description']); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_project_url_<?php echo $project['id']; ?>">URL (opcional):</label>
                                            <input type="url" id="edit_project_url_<?php echo $project['id']; ?>" name="url" value="<?php echo htmlspecialchars($project['url']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_project_image_<?php echo $project['id']; ?>">Nueva Imagen del Proyecto (opcional):</label>
                                            <input type="file" id="edit_project_image_<?php echo $project['id']; ?>" name="project_image" accept="image/*">
                                            <?php if (!empty($project['image_path'])): ?>
                                                <p>Imagen actual: <a href="./assets/proyectos/<?php echo htmlspecialchars($project['image_path']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($project['image_path']); ?></a></p>
                                            <?php endif; ?>
                                        </div>
                                        <button type="submit" name="edit_project" class="btn btn-small">Guardar Cambios</button>
                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideEditProjectForm(<?php echo $project['id']; ?>)">Cancelar</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No hay proyectos registrados.</p>
                        <?php endif; ?>
                    </div>

                    <script>
                        function showEditProjectForm(id) {
                            $(`#edit-project-form-${id}`).show();
                        }

                        function hideEditProjectForm(id) {
                            $(`#edit-project-form-${id}`).hide();
                        }

                        let isProjectOrderEditing = false;
                        function toggleProjectOrderEdit() {
                            const container = $('#projects-sortable');
                            const editButton = $('.btn-edit-order');

                            if (!isProjectOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.order-handle',
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=projects", {
                                            csrf_token: csrfToken,
                                            reorder_projects: true,
                                            projects_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isProjectOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isProjectOrderEditing = false;
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'certificates':
                    echo "<h3>Gestión de Certificados</h3>";
                    $certificates = getFiles('certificate'); // Asumiendo que 'certificate' es el tipo de archivo para certificados

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['add_certificate'])) {
                                require_once '../app/php/upload.php';
                                $uploadResult = handleFileUpload('certificate_file', 'UPLOAD_DIR_CERTIFICATES', 'ALLOWED_IMAGE_TYPES', 'certificate', sanitizeInput($_POST['name'])); // Pasar el nombre
                                if ($uploadResult['success']) {
                                    echo "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                                    $certificates = getFiles('certificate'); // Recargar datos
                                } else {
                                    echo "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                                }
                            } elseif (isset($_POST['edit_certificate'])) {
                                $id = (int)$_POST['id'];
                                $data = [
                                    'file_name' => sanitizeInput($_POST['name']),
                                    'description' => sanitizeInput($_POST['description']) // Añadir descripción
                                ];
                                if (updateTable('files', $data, $id)) {
                                    echo "<p class='success-message'>Certificado actualizado exitosamente.</p>";
                                    $certificates = getFiles('certificate'); // Recargar datos
                                } else {
                                    echo "<p class='error-message'>Error al actualizar certificado.</p>";
                                }
                            } elseif (isset($_POST['delete_certificate'])) {
                                $id = (int)$_POST['id'];
                                $fileToDelete = fetchByIdFromTable('files', $id);
                                if ($fileToDelete) {
                                    $filePath = UPLOAD_DIR_CERTIFICATES . $fileToDelete['file_path'];
                                    if (file_exists($filePath) && unlink($filePath)) {
                                        if (deleteFromTable('files', $id)) {
                                            echo "<p class='success-message'>Certificado eliminado exitosamente.</p>";
                                            $certificates = getFiles('certificate'); // Recargar datos
                                        } else {
                                            echo "<p class='error-message'>Error al eliminar el registro del certificado de la base de datos.</p>";
                                        }
                                    } else if (!file_exists($filePath)) {
                                        // Si el archivo no existe, pero el registro sí, eliminar solo el registro de la DB
                                        if (deleteFromTable('files', $id)) {
                                            echo "<p class='success-message'>Certificado eliminado exitosamente (archivo no encontrado, solo se eliminó el registro).</p>";
                                            $certificates = getFiles('certificate'); // Recargar datos
                                        } else {
                                            echo "<p class='error-message'>Error al eliminar el registro del certificado de la base de datos (archivo no encontrado).</p>";
                                        }
                                    }
                                    else {
                                        echo "<p class='error-message'>Error al eliminar el archivo físico del certificado.</p>";
                                    }
                                } else {
                                    echo "<p class='error-message'>Certificado no encontrado.</p>";
                                }
                            }
                        }
                    } elseif (isset($_POST['reorder_certificates'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF inválido.']);
                            exit();
                        }
                        $order = json_decode($_POST['certificates_order'], true);
                        if (reorderFiles($order)) { // Usar la función genérica reorderFiles
                            echo json_encode(['success' => true, 'message' => 'Orden de certificados actualizado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar el orden de los certificados.']);
                        }
                        exit(); // Terminar la ejecución después de la respuesta AJAX
                    }
                    ?>
                    <h4>Añadir Nuevo Certificado</h4>
                    <form action="panel-secreto-2025.php?section=certificates" method="POST" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="certificate_file">Archivo de Certificado (PDF/Imagen):</label>
                            <input type="file" id="certificate_file" name="certificate_file" accept=".pdf,image/*" required>
                        </div>
                        <div class="form-group">
                            <label for="certificate_name">Nombre del Certificado:</label>
                            <input type="text" id="certificate_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="certificate_description">Descripción (opcional):</label>
                            <textarea id="certificate_description" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" name="add_certificate" class="btn">Subir Certificado</button>
                    </form>

                    <h4>Certificados Existentes <button type="button" class="btn btn-small btn-edit-order" onclick="toggleCertificateOrderEdit()">Editar Orden</button></h4>
                    <div class="certificate-cards-container sortable-list" id="certificates-sortable">
                        <?php if (!empty($certificates)): ?>
                            <?php foreach ($certificates as $cert): ?>
                                <div class="certificate-card" data-id="<?php echo $cert['id']; ?>">
                                    <div class="order-handle"><i class="fas fa-grip-vertical"></i></div>
                                    <?php
                                        $fileExtension = pathinfo($cert['file_path'], PATHINFO_EXTENSION);
                                        $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                                    ?>
                                    <?php if ($isImage): ?>
                                        <img src="./assets/certificados/<?php echo htmlspecialchars($cert['file_path']); ?>" alt="<?php echo htmlspecialchars($cert['file_name']); ?>" class="certificate-thumbnail" onclick="openModalImage('./assets/certificados/<?php echo htmlspecialchars($cert['file_path']); ?>')">
                                    <?php else: ?>
                                        <div class="certificate-icon-preview">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($cert['file_name']); ?></h5>
                                    <?php if (!empty($cert['description'])): ?>
                                        <p class="certificate-description"><?php echo htmlspecialchars($cert['description']); ?></p>
                                    <?php endif; ?>
                                    <p class="certificate-uploaded-date">Subido el: <?php echo htmlspecialchars($cert['uploaded_at']); ?></p>
                                    <div class="certificate-actions">
                                        <a href="./assets/certificados/<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" rel="noopener" class="btn btn-small btn-view">Ver Archivo</a>
                                        <button type="button" class="btn btn-small btn-edit" onclick="showEditCertificateForm(<?php echo $cert['id']; ?>)">Editar</button>
                                        <form action="panel-secreto-2025.php?section=certificates" method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <input type="hidden" name="id" value="<?php echo $cert['id']; ?>">
                                            <button type="submit" name="delete_certificate" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar este certificado?');">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                                <div id="edit-certificate-form-<?php echo $cert['id']; ?>" class="edit-form-container" style="display:none;">
                                    <h5>Editar Certificado</h5>
                                    <form action="panel-secreto-2025.php?section=certificates" method="POST" class="admin-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="id" value="<?php echo $cert['id']; ?>">
                                        <div class="form-group">
                                            <label for="edit_certificate_name_<?php echo $cert['id']; ?>">Nombre del Certificado:</label>
                                            <input type="text" id="edit_certificate_name_<?php echo $cert['id']; ?>" name="name" value="<?php echo htmlspecialchars($cert['file_name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_certificate_description_<?php echo $cert['id']; ?>">Descripción (opcional):</label>
                                            <textarea id="edit_certificate_description_<?php echo $cert['id']; ?>" name="description" rows="3"><?php echo htmlspecialchars($cert['description'] ?? ''); ?></textarea>
                                        </div>
                                        <button type="submit" name="edit_certificate" class="btn btn-small">Guardar Cambios</button>
                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideEditCertificateForm(<?php echo $cert['id']; ?>)">Cancelar</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No hay certificados registrados.</p>
                        <?php endif; ?>
                    </div>

                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        // Funciones para mostrar/ocultar el formulario de edición
                        function showEditCertificateForm(id) {
                            $(`#edit-certificate-form-${id}`).show();
                        }

                        function hideEditCertificateForm(id) {
                            $(`#edit-certificate-form-${id}`).hide();
                        }

                        let isCertificateOrderEditing = false;
                        function toggleCertificateOrderEdit() {
                            const container = $('#certificates-sortable');
                            const editButton = $('.btn-edit-order');

                            if (!isCertificateOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.order-handle',
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=certificates", {
                                            csrf_token: csrfToken,
                                            reorder_certificates: true,
                                            certificates_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isCertificateOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isCertificateOrderEditing = false;
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'documents':
                    echo "<h3>Gestión de Documentos</h3>";
                    $documents = getFiles('document'); // Asumiendo que 'document' es el tipo de archivo para documentos

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['add_document'])) {
                                require_once '../app/php/upload.php';
                                $uploadResult = handleFileUpload('document_file', 'UPLOAD_DIR_DOCUMENTS', 'ALLOWED_DOCUMENT_TYPES', 'document', sanitizeInput($_POST['name'])); // Pasar el nombre
                                if ($uploadResult['success']) {
                                    echo "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                                    $documents = getFiles('document'); // Recargar datos
                                } else {
                                    echo "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                                }
                            } elseif (isset($_POST['edit_document'])) {
                                $id = (int)$_POST['id'];
                                $data = [
                                    'file_name' => sanitizeInput($_POST['name']),
                                    'description' => sanitizeInput($_POST['description']) // Añadir descripción
                                ];
                                if (updateTable('files', $data, $id)) {
                                    echo "<p class='success-message'>Documento actualizado exitosamente.</p>";
                                    $documents = getFiles('document'); // Recargar datos
                                } else {
                                    echo "<p class='error-message'>Error al actualizar documento.</p>";
                                }
                            } elseif (isset($_POST['delete_document'])) {
                                $id = (int)$_POST['id'];
                                $fileToDelete = fetchByIdFromTable('files', $id);
                                if ($fileToDelete) {
                                    $filePath = UPLOAD_DIR_DOCUMENTS . $fileToDelete['file_path'];
                                    if (file_exists($filePath) && unlink($filePath)) {
                                        if (deleteFromTable('files', $id)) {
                                            echo "<p class='success-message'>Documento eliminado exitosamente.</p>";
                                            $documents = getFiles('document'); // Recargar datos
                                        } else {
                                            echo "<p class='error-message'>Error al eliminar el registro del documento de la base de datos.</p>";
                                        }
                                    } else if (!file_exists($filePath)) {
                                        // Si el archivo no existe, pero el registro sí, eliminar solo el registro de la DB
                                        if (deleteFromTable('files', $id)) {
                                            echo "<p class='success-message'>Documento eliminado exitosamente (archivo no encontrado, solo se eliminó el registro).</p>";
                                            $documents = getFiles('document'); // Recargar datos
                                        } else {
                                            echo "<p class='error-message'>Error al eliminar el registro del documento de la base de datos (archivo no encontrado).</p>";
                                        }
                                    }
                                    else {
                                        echo "<p class='error-message'>Error al eliminar el archivo físico del documento.</p>";
                                    }
                                } else {
                                    echo "<p class='error-message'>Documento no encontrado.</p>";
                                }
                            }
                        }
                    } elseif (isset($_POST['reorder_documents'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF inválido.']);
                            exit();
                        }
                        $order = json_decode($_POST['documents_order'], true);
                        if (reorderFiles($order)) { // Usar la función genérica reorderFiles
                            echo json_encode(['success' => true, 'message' => 'Orden de documentos actualizado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar el orden de los documentos.']);
                        }
                        exit(); // Terminar la ejecución después de la respuesta AJAX
                    }
                    ?>
                    <h4>Añadir Nuevo Documento</h4>
                    <form action="panel-secreto-2025.php?section=documents" method="POST" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="document_file">Archivo de Documento (PDF/DOCX):</label>
                            <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx" required>
                        </div>
                        <div class="form-group">
                            <label for="document_name">Nombre del Documento:</label>
                            <input type="text" id="document_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="document_description">Descripción (opcional):</label>
                            <textarea id="document_description" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" name="add_document" class="btn">Subir Documento</button>
                    </form>

                    <h4>Documentos Existentes <button type="button" class="btn btn-small btn-edit-order" onclick="toggleDocumentOrderEdit()">Editar Orden</button></h4>
                    <div class="document-cards-container sortable-list" id="documents-sortable">
                        <?php if (!empty($documents)): ?>
                            <?php foreach ($documents as $doc): ?>
                                <div class="document-card" data-id="<?php echo $doc['id']; ?>">
                                    <div class="order-handle"><i class="fas fa-grip-vertical"></i></div>
                                    <?php
                                        $fileExtension = pathinfo($doc['file_path'], PATHINFO_EXTENSION);
                                        $isPdf = (strtolower($fileExtension) == 'pdf');
                                        $isDocx = (strtolower($fileExtension) == 'docx' || strtolower($fileExtension) == 'doc');
                                    ?>
                                    <?php if ($isPdf): ?>
                                        <div class="document-icon-preview">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                    <?php elseif ($isDocx): ?>
                                        <div class="document-icon-preview">
                                            <i class="fas fa-file-word"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="document-icon-preview">
                                            <i class="fas fa-file"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5><?php echo htmlspecialchars($doc['file_name']); ?></h5>
                                    <?php if (!empty($doc['description'])): ?>
                                        <p class="document-description"><?php echo htmlspecialchars($doc['description']); ?></p>
                                    <?php endif; ?>
                                    <p class="document-uploaded-date">Subido el: <?php echo htmlspecialchars($doc['uploaded_at']); ?></p>
                                    <div class="document-actions">
                                        <a href="./assets/documentos/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" rel="noopener" class="btn btn-small btn-view">Ver Archivo</a>
                                        <button type="button" class="btn btn-small btn-edit" onclick="showEditDocumentForm(<?php echo $doc['id']; ?>)">Editar</button>
                                        <form action="panel-secreto-2025.php?section=documents" method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                            <button type="submit" name="delete_document" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar este documento?');">Eliminar</button>
                                        </form>
                                    </div>
                                </div>
                                <div id="edit-document-form-<?php echo $doc['id']; ?>" class="edit-form-container" style="display:none;">
                                    <h5>Editar Documento</h5>
                                    <form action="panel-secreto-2025.php?section=documents" method="POST" class="admin-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                        <div class="form-group">
                                            <label for="edit_document_name_<?php echo $doc['id']; ?>">Nombre del Documento:</label>
                                            <input type="text" id="edit_document_name_<?php echo $doc['id']; ?>" name="name" value="<?php echo htmlspecialchars($doc['file_name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="edit_document_description_<?php echo $doc['id']; ?>">Descripción (opcional):</label>
                                            <textarea id="edit_document_description_<?php echo $doc['id']; ?>" name="description" rows="3"><?php echo htmlspecialchars($doc['description'] ?? ''); ?></textarea>
                                        </div>
                                        <button type="submit" name="edit_document" class="btn btn-small">Guardar Cambios</button>
                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideEditDocumentForm(<?php echo $doc['id']; ?>)">Cancelar</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No hay documentos registrados.</p>
                        <?php endif; ?>
                    </div>

                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        // Funciones para mostrar/ocultar el formulario de edición
                        function showEditDocumentForm(id) {
                            $(`#edit-document-form-${id}`).show();
                        }

                        function hideEditDocumentForm(id) {
                            $(`#edit-document-form-${id}`).hide();
                        }

                        let isDocumentOrderEditing = false;
                        function toggleDocumentOrderEdit() {
                            const container = $('#documents-sortable');
                            const editButton = $('.btn-edit-order');

                            if (!isDocumentOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.order-handle',
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=documents", {
                                            csrf_token: csrfToken,
                                            reorder_documents: true,
                                            documents_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isDocumentOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isDocumentOrderEditing = false;
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'other_webs':
                    echo "<h3>Gestión de Otras Webs</h3>";
                    $other_webs = fetchAllFromTable('other_webs'); // Asumiendo que tienes una tabla 'other_webs'

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_other_web'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $data = [
                                'title' => sanitizeInput($_POST['title']),
                                'description' => sanitizeInput($_POST['description']),
                                'url' => sanitizeInput($_POST['url'])
                            ];
                            if (insertIntoTable('other_webs', $data)) {
                                echo "<p class='success-message'>Web añadida exitosamente.</p>";
                                $other_webs = fetchAllFromTable('other_webs'); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al añadir web.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_other_web'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            $data = [
                                'title' => sanitizeInput($_POST['title']),
                                'description' => sanitizeInput($_POST['description']),
                                'url' => sanitizeInput($_POST['url'])
                            ];
                            if (updateTable('other_webs', $data, $id)) {
                                echo "<p class='success-message'>Web actualizada exitosamente.</p>";
                                $other_webs = fetchAllFromTable('other_webs'); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar web.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_other_web'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            if (deleteFromTable('other_webs', $id)) {
                                echo "<p class='success-message'>Web eliminada exitosamente.</p>";
                                $other_webs = getOtherWebs(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al eliminar web.</p>";
                            }
                        }
                    } elseif (isset($_POST['reorder_other_webs'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo json_encode(['success' => false, 'message' => 'Error de seguridad: Token CSRF inválido.']);
                            exit();
                        }
                        $order = json_decode($_POST['other_webs_order'], true);
                        if (reorderOtherWebs($order)) {
                            echo json_encode(['success' => true, 'message' => 'Orden de otras webs actualizado exitosamente.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar el orden de otras webs.']);
                        }
                        exit(); // Terminar la ejecución después de la respuesta AJAX
                    }
                    ?>
                    <h4>Añadir Nueva Web</h4>
                    <form action="panel-secreto-2025.php?section=other_webs" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="other_web_title">Título:</label>
                            <input type="text" id="other_web_title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="other_web_description">Descripción:</label>
                            <textarea id="other_web_description" name="description" rows="5"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="other_web_url">URL:</label>
                            <input type="url" id="other_web_url" name="url" required>
                        </div>
                        <button type="submit" name="add_other_web" class="btn">Añadir Web</button>
                    </form>

                    <h4>Otras Webs Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Título</th>
                                    <th>Descripción</th>
                                    <th>URL</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="other-webs-sortable">
                                <?php if (!empty($other_webs)): ?>
                                    <?php foreach ($other_webs as $web): ?>
                                        <tr data-id="<?php echo $web['id']; ?>">
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i></td>
                                            <td><?php echo htmlspecialchars($web['title']); ?></td>
                                            <td><?php echo htmlspecialchars($web['description']); ?></td>
                                            <td><a href="<?php echo htmlspecialchars($web['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($web['url']); ?></a></td>
                                            <td>
                                                <button type="button" class="btn btn-small btn-edit" onclick="showEditOtherWebForm(<?php echo $web['id']; ?>)">Editar</button>
                                                <form action="panel-secreto-2025.php?section=other_webs" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $web['id']; ?>">
                                                    <button type="submit" name="delete_other_web" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta web?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="edit-other-web-form-<?php echo $web['id']; ?>" style="display:none;">
                                            <td colspan="5">
                                                <div class="edit-form-container">
                                                    <h5>Editar Web</h5>
                                                    <form action="panel-secreto-2025.php?section=other_webs" method="POST" class="admin-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $web['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="edit_other_web_title_<?php echo $web['id']; ?>">Título:</label>
                                                            <input type="text" id="edit_other_web_title_<?php echo $web['id']; ?>" name="title" value="<?php echo htmlspecialchars($web['title']); ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_other_web_description_<?php echo $web['id']; ?>">Descripción:</label>
                                                            <textarea id="edit_other_web_description_<?php echo $web['id']; ?>" name="description" rows="5"><?php echo htmlspecialchars($web['description']); ?></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="edit_other_web_url_<?php echo $web['id']; ?>">URL:</label>
                                                            <input type="url" id="edit_other_web_url_<?php echo $web['id']; ?>" name="url" value="<?php echo htmlspecialchars($web['url']); ?>" required>
                                                        </div>
                                                        <button type="submit" name="edit_other_web" class="btn btn-small">Guardar Cambios</button>
                                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideEditOtherWebForm(<?php echo $web['id']; ?>)">Cancelar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No hay otras webs registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        function showEditOtherWebForm(id) {
                            $(`#edit-other-web-form-${id}`).show();
                        }

                        function hideEditOtherWebForm(id) {
                            $(`#edit-other-web-form-${id}`).hide();
                        }

                        let isOtherWebOrderEditing = false;
                        function toggleOtherWebOrderEdit() {
                            const container = $('#other-webs-sortable');
                            const editButton = $('.btn-edit-order');

                            if (!isOtherWebOrderEditing) {
                                container.sortable({
                                    axis: 'y',
                                    handle: '.order-handle',
                                    update: function(event, ui) {
                                        const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                        const csrfToken = $('input[name="csrf_token"]').val();
                                        $.post("panel-secreto-2025.php?section=other_webs", {
                                            csrf_token: csrfToken,
                                            reorder_other_webs: true,
                                            other_webs_order: JSON.stringify(order)
                                        }, function(response) {
                                            console.log(response);
                                        });
                                    }
                                });
                                container.sortable('enable');
                                editButton.text('Guardar Orden');
                                isOtherWebOrderEditing = true;
                            } else {
                                container.sortable('disable');
                                editButton.text('Editar Orden');
                                isOtherWebOrderEditing = false;
                            }
                        }
                    </script>
                    <?php
                    break;
                case 'upload_cv':
                    echo "<h3>Cargar CV (DOCX)</h3>";
                    require_once '../app/php/upload.php'; // Incluir el manejador de subidas

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_cv_file'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $uploadResult = handleCVUpload('cv_file');
                            if ($uploadResult['success']) {
                                echo "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                            } else {
                                echo "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                            }
                        }
                    }
                    ?>
                    <form action="panel-secreto-2025.php?section=upload_cv" method="POST" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="cv_file">Seleccionar archivo CV (DOCX):</label>
                            <input type="file" id="cv_file" name="cv_file" accept=".docx" required>
                        </div>
                        <button type="submit" name="upload_cv_file" class="btn">Cargar y Procesar CV</button>
                    </form>
                    <?php
                    break;
                default:
                    echo "<h3>Dashboard</h3>";
                    echo "<p>Aquí podrás ver un resumen de tu portafolio y acceder a las herramientas de gestión.</p>";
                    echo "<p>Usuario por defecto: <strong>" . ADMIN_USERNAME . "</strong></p>";
                    echo "<p>Contraseña por defecto: <strong>adminpass</strong> (¡Cámbiala inmediatamente!)</p>";
                    echo "<p>Para cambiar la contraseña, puedes editar el archivo <code>app/php/config.php</code> y actualizar la constante <code>ADMIN_PASSWORD</code> con un nuevo hash de contraseña.</p>";
                    break;
                case 'edit_sections':
                    echo "<h3>Gestión de Secciones</h3>";
                    $sections = getSections();
                    $message = '';

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            $message = "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            if (isset($_POST['add_section'])) {
                                $name = sanitizeInput($_POST['section_name']);
                                $content_type = sanitizeInput($_POST['content_type']);
                                if (addSection($name, $content_type)) {
                                    $message = "<p class='success-message'>Sección '" . htmlspecialchars($name) . "' añadida exitosamente.</p>";
                                    $sections = getSections(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al añadir la sección.</p>";
                                }
                            } elseif (isset($_POST['rename_section'])) {
                                $id = (int)$_POST['section_id'];
                                $new_name = sanitizeInput($_POST['new_section_name']);
                                if (renameSection($id, $new_name)) {
                                    $message = "<p class='success-message'>Sección renombrada exitosamente.</p>";
                                    $sections = getSections(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al renombrar la sección.</p>";
                                }
                            } elseif (isset($_POST['delete_section'])) {
                                $id = (int)$_POST['section_id'];
                                if (deleteSection($id)) {
                                    $message = "<p class='success-message'>Sección eliminada exitosamente.</p>";
                                    $sections = getSections(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al eliminar la sección.</p>";
                                }
                            } elseif (isset($_POST['reorder_sections'])) {
                                $order = json_decode($_POST['sections_order'], true);
                                if (reorderSections($order)) {
                                    $message = "<p class='success-message'>Orden de secciones actualizado exitosamente.</p>";
                                    $sections = getSections(); // Recargar datos
                                } else {
                                    $message = "<p class='error-message'>Error al actualizar el orden de las secciones.</p>";
                                }
                            }
                        }
                    }
                    ?>
                    <?php if ($message): echo $message; endif; ?>

                    <h4>Añadir Nueva Sección</h4>
                    <form action="panel-secreto-2025.php?section=edit_sections" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="section_name">Nombre de la Sección:</label>
                            <input type="text" id="section_name" name="section_name" required>
                        </div>
                        <div class="form-group">
                            <label for="content_type">Tipo de Contenido:</label>
                            <select id="content_type" name="content_type" required>
                                <?php foreach (ALLOWED_CONTENT_TYPES as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars(ucfirst($type)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_section" class="btn">Añadir Sección</button>
                    </form>

                    <h4>Secciones Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Nombre</th>
                                    <th>Tipo de Contenido</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="sections-sortable">
                                <?php if (!empty($sections)): ?>
                                    <?php $num_sections = count($sections); ?>
                                    <?php foreach ($sections as $sec): ?>
                                        <tr data-id="<?php echo $sec['id']; ?>">
                                            <td><?php echo htmlspecialchars($sec['order_index']); ?></td>
                                            <td><?php echo htmlspecialchars($sec['name']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($sec['content_type'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-small btn-edit" onclick="showRenameForm(<?php echo $sec['id']; ?>, '<?php echo htmlspecialchars($sec['name']); ?>')">Renombrar</button>
                                                <form action="panel-secreto-2025.php?section=edit_sections" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="section_id" value="<?php echo $sec['id']; ?>">
                                                    <button type="submit" name="delete_section" class="btn btn-small btn-delete" <?php echo ($num_sections <= 4) ? 'disabled' : ''; ?> onclick="return confirm('¿Estás seguro de que quieres eliminar la sección <?php echo htmlspecialchars($sec['name']); ?>?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <tr id="rename-form-<?php echo $sec['id']; ?>" style="display:none;">
                                            <td colspan="4">
                                                <div class="edit-form-container">
                                                    <h5>Renombrar Sección</h5>
                                                    <form action="panel-secreto-2025.php?section=edit_sections" method="POST" class="admin-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="section_id" value="<?php echo $sec['id']; ?>">
                                                        <div class="form-group">
                                                            <label for="new_section_name_<?php echo $sec['id']; ?>">Nuevo Nombre:</label>
                                                            <input type="text" id="new_section_name_<?php echo $sec['id']; ?>" name="new_section_name" value="<?php echo htmlspecialchars($sec['name']); ?>" required>
                                                        </div>
                                                        <button type="submit" name="rename_section" class="btn btn-small">Guardar</button>
                                                        <button type="button" class="btn btn-small btn-secondary" onclick="hideRenameForm(<?php echo $sec['id']; ?>)">Cancelar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">No hay secciones registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
                    <script>
                        $(function() {
                            $("#sections-sortable").sortable({
                                update: function(event, ui) {
                                    const order = $(this).sortable('toArray', { attribute: 'data-id' });
                                    const csrfToken = $('input[name="csrf_token"]').val(); // Obtener el token CSRF del campo oculto
                                    // Enviar el nuevo orden al servidor
                                    $.post("panel-secreto-2025.php?section=edit_sections", {
                                        csrf_token: csrfToken,
                                        reorder_sections: true,
                                        sections_order: JSON.stringify(order)
                                    }, function(response) {
                                        // Manejar la respuesta si es necesario
                                        console.log(response);
                                        location.reload(); // Recargar la página para reflejar el nuevo orden
                                    });
                                }
                            });
                            $("#sections-sortable").disableSelection();
                        });

                        function showRenameForm(id, currentName) {
                            $(`#rename-form-${id}`).show();
                            $(`#new_section_name_${id}`).val(currentName);
                        }

                        function hideRenameForm(id) {
                            $(`#rename-form-${id}`).hide();
                        }
                    </script>
                    <?php
                    break;
                case 'analytics':
                    echo "<h3>Análisis de Visualización</h3>";
                    // Placeholder for analytics UI
                    echo "<p>Aquí se mostrarán las gráficas y datos de las visitas a tu portafolio.</p>";
                    break;
                case 'change_password':
                    echo "<h3>Cambiar Contraseña</h3>";
                    $user_id = $_SESSION['user_id'];
                    $user_email = getUserEmail($user_id); // Asume que tienes una función para obtener el email del usuario

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $currentPassword = $_POST['current_password'];
                            $newPassword = $_POST['new_password'];
                            $confirmNewPassword = $_POST['confirm_new_password'];

                            if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
                                echo "<p class='error-message'>Todos los campos de contraseña son obligatorios.</p>";
                            } elseif ($newPassword !== $confirmNewPassword) {
                                echo "<p class='error-message'>La nueva contraseña y la confirmación no coinciden.</p>";
                            } elseif (!isStrongPassword($newPassword)) {
                                echo "<p class='error-message'>La nueva contraseña no es lo suficientemente fuerte. Debe tener al menos 12 caracteres, incluyendo mayúsculas, minúsculas, números y símbolos.</p>";
                            } else {
                                // Verificar la contraseña actual
                                $db = getDatabaseConnection();
                                $stmt = $db->prepare("SELECT password FROM admin_users WHERE id = :id");
                                $stmt->execute(['id' => $user_id]);
                                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                                if ($user && password_verify($currentPassword, $user['password'])) {
                                    if (updateUserPassword($user_id, $newPassword)) {
                                        echo "<p class='success-message'>Contraseña actualizada exitosamente.</p>";
                                    } else {
                                        echo "<p class='error-message'>Error al actualizar la contraseña.</p>";
                                    }
                                } else {
                                    echo "<p class='error-message'>La contraseña actual es incorrecta.</p>";
                                }
                            }
                        }
                    }
                    ?>
                    <form action="panel-secreto-2025.php?section=change_password" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="current_password">Contraseña Actual:</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nueva Contraseña:</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password">Confirmar Nueva Contraseña:</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn">Cambiar Contraseña</button>
                    </form>
                    <?php
                    break;
                case 'forgot_password':
                    echo "<h3>Recuperar Contraseña</h3>";
                    $message = '';
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            $message = "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $email = sanitizeInput($_POST['email']);
                            $token = generatePasswordResetToken($email);
                            if ($token && sendPasswordResetEmail($email, $token)) {
                                $message = "<p class='success-message'>Se ha enviado un enlace de recuperación a tu correo electrónico.</p>";
                            } else {
                                $message = "<p class='error-message'>Error al enviar el correo de recuperación. Asegúrate de que el correo electrónico sea correcto y que la configuración SMTP sea válida.</p>";
                            }
                        }
                    }
                    ?>
                    <?php if ($message): echo $message; endif; ?>
                    <form action="panel-secreto-2025.php?section=forgot_password" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="email">Correo Electrónico:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <button type="submit" name="request_reset" class="btn">Solicitar Restablecimiento</button>
                    </form>
                    <?php
                    break;
                case 'reset_password':
                    echo "<h3>Restablecer Contraseña</h3>";
                    $message = '';
                    $token = sanitizeInput($_GET['token'] ?? '');
                    $user_id = verifyPasswordResetToken($token);

                    if (!$user_id) {
                        $message = "<p class='error-message'>Token de restablecimiento inválido o expirado.</p>";
                    } else {
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
                            if (!verifyCsrfToken($_POST['csrf_token'])) {
                                $message = "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                            } else {
                                $newPassword = $_POST['new_password'];
                                $confirmNewPassword = $_POST['confirm_new_password'];

                                if (empty($newPassword) || empty($confirmNewPassword)) {
                                    $message = "<p class='error-message'>Todos los campos de contraseña son obligatorios.</p>";
                                } elseif ($newPassword !== $confirmNewPassword) {
                                    $message = "<p class='error-message'>La nueva contraseña y la confirmación no coinciden.</p>";
                                } elseif (strlen($newPassword) < 8) {
                                    } elseif (!isStrongPassword($newPassword)) {
                                        $message = "<p class='error-message'>La nueva contraseña no es lo suficientemente fuerte. Debe tener al menos 12 caracteres, incluyendo mayúsculas, minúsculas, números y símbolos.</p>";
                                } else {
                                    if (updateUserPassword($user_id, $newPassword)) {
                                        $message = "<p class='success-message'>Contraseña restablecida exitosamente. Ya puedes iniciar sesión.</p>";
                                        // Limpiar el token después de usarlo
                                        $db = getDatabaseConnection();
                                        $stmt = $db->prepare("UPDATE admin_users SET reset_token = NULL, reset_token_expires_at = NULL WHERE id = :id");
                                        $stmt->execute(['id' => $user_id]);
                                    } else {
                                        $message = "<p class='error-message'>Error al restablecer la contraseña.</p>";
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    <?php if ($message): echo $message; endif; ?>
                    <?php if ($user_id): ?>
                        <form action="panel-secreto-2025.php?section=reset_password&token=<?php echo htmlspecialchars($token); ?>" method="POST" class="admin-form">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <div class="form-group">
                                <label for="new_password">Nueva Contraseña:</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_new_password">Confirmar Nueva Contraseña:</label>
                                <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                            </div>
                            <button type="submit" name="reset_password" class="btn">Restablecer Contraseña</button>
                        </form>
                    <?php endif; ?>
                    <?php
                    break;
                case 'security_settings':
                    echo "<h3>Configuración de Seguridad</h3>";
                    $user_id = $_SESSION['user_id'];
                    $current_verification_email = getUserVerificationEmail($user_id);
                    $user_security_questions_raw = getUserSecurityQuestions($user_id);
                    $user_security_questions = [];
                    foreach ($user_security_questions_raw as $q) {
                        $user_security_questions[] = ['question' => $q['question_text'], 'answer' => '']; // No mostrar la respuesta
                    }

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security_email'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $new_email = sanitizeInput($_POST['verification_email']);
                            $db = getDatabaseConnection();
                            $stmt = $db->prepare("UPDATE admin_users SET verification_email = :email WHERE id = :id");
                            if ($stmt->execute(['email' => $new_email, 'id' => $user_id])) {
                                echo "<p class='success-message'>Correo de verificación actualizado exitosamente.</p>";
                                $current_verification_email = $new_email; // Actualizar para mostrar el nuevo email
                            } else {
                                echo "<p class='error-message'>Error al actualizar el correo de verificación.</p>";
                            }
                        }
                    }

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_security_questions'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $questions_to_save = [];
                            // Obtener las preguntas existentes para el usuario
                            $existing_questions = getUserSecurityQuestions($user_id);
                            $num_existing_questions = count($existing_questions);

                            for ($i = 0; $i < 2; $i++) { // Asumiendo 2 preguntas de seguridad
                                $question_key = 'security_question_' . ($i + 1);
                                $answer_key = 'security_answer_' . ($i + 1);

                                if (!empty($question_text) && !empty($answer_text)) {
                                    $questions_to_save[] = ['question' => $question_text, 'answer' => $answer_text];
                                } else if (!empty($question_text) && empty($answer_text) && isset($existing_questions[$i])) {
                                    // Si la pregunta se envió pero la respuesta está vacía, y ya existía una pregunta,
                                    // mantener la respuesta existente. Esto es para permitir cambiar solo la pregunta.
                                    // NOTA: Esto requiere una lógica más compleja para actualizar solo la pregunta sin cambiar la respuesta.
                                    // Por simplicidad, si la respuesta está vacía, se considerará que no se quiere cambiar.
                                    // Para una seguridad estricta, se debería requerir la respuesta actual para cambiar la pregunta.
                                    // Por ahora, si la respuesta está vacía, no se actualizará esa pregunta/respuesta.
                                    // Para este caso, el usuario debe reingresar la respuesta si quiere cambiar la pregunta.
                                    // O se podría buscar la respuesta hash existente y reusarla si la pregunta es la misma.
                                    // Para este flujo, si la respuesta está vacía, no se guarda.
                                }
                            }

                            if (saveSecurityQuestions($user_id, $questions_to_save)) {
                                echo "<p class='success-message'>Preguntas de seguridad guardadas exitosamente.</p>";
                                $user_security_questions = getUserSecurityQuestions($user_id); // Recargar preguntas
                            } else {
                                echo "<p class='error-message'>Error al guardar las preguntas de seguridad.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Configurar Correo de Verificación</h4>
                    <form action="panel-secreto-2025.php?section=security_settings" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="verification_email">Correo Electrónico para Verificaciones:</label>
                            <input type="email" id="verification_email" name="verification_email" value="<?php echo htmlspecialchars($current_verification_email ?? ''); ?>" required>
                        </div>
                        <button type="submit" name="update_security_email" class="btn">Actualizar Correo</button>
                    </form>

                    <h4 style="margin-top: 30px;">Configurar Preguntas de Seguridad</h4>
                    <form action="panel-secreto-2025.php?section=security_settings" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <?php for ($i = 1; $i <= 2; $i++): ?>
                            <div class="form-group">
                                <label for="security_question_<?php echo $i; ?>">Pregunta de Seguridad <?php echo $i; ?>:</label>
                                <input type="text" id="security_question_<?php echo $i; ?>" name="security_question_<?php echo $i; ?>" value="<?php echo htmlspecialchars($user_security_questions[$i-1]['question'] ?? ''); ?>" placeholder="Ej: ¿Cuál es el nombre de tu primera mascota?" required>
                            </div>
                            <div class="form-group">
                                <label for="security_answer_<?php echo $i; ?>">Respuesta <?php echo $i; ?>:</label>
                                <input type="password" id="security_answer_<?php echo $i; ?>" name="security_answer_<?php echo $i; ?>" placeholder="Tu respuesta secreta" required>
                            </div>
                        <?php endfor; ?>
                        <button type="submit" name="save_security_questions" class="btn">Guardar Preguntas de Seguridad</button>
                    </form>
                    <?php
                    break;
            }
            ?>
        </div>
    </main>

    <!-- Modal para la imagen expandida (única declaración) -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModalImage()">&times;</span>
        <img class="modal-content" id="img01">
    </div>

    <script>
        // Lógica del modal de imagen (única declaración)
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("img01");

        function openModalImage(src) {
            modal.style.display = "block";
            modalImg.src = src;
        }

        function closeModalImage() {
            modal.style.display = "none";
        }
    </script>

    <footer class="admin-footer">
        <div class="container">
            <p>&copy; 2025 Panel de Administración - Randy Rodríguez Vidovic</p>
        </div>
    </footer>
</body>
</html>
