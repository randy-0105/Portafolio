<?php
// app/php/admin_panel.php - Panel de administración

require_once 'auth.php';
require_once 'db.php'; // Asegúrate de incluir db.php para las funciones de la base de datos
checkSessionActivity(); // Verificar la actividad de la sesión

// Si el usuario no está autenticado, mostrar el formulario de login
if (!isAuthenticated()) {
    $error_message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("Admin Panel: Solicitud POST recibida para login.");
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password']; // La contraseña no se sanitiza con htmlspecialchars antes de password_verify

        if (login($username, $password)) {
            header("Location: /admin.php"); // Redirigir al panel si el login es exitoso
            exit();
        } else {
            $error_message = "Usuario o contraseña incorrectos.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link rel="stylesheet" href="../../public/css/styles.css"> <!-- Usar los estilos generales -->
        <link rel="stylesheet" href="../../public/css/admin.css"> <!-- Estilos específicos del admin -->
    </head>
    <body class="admin-login-body">
        <div class="login-container">
            <h2>Acceso al Panel de Administración</h2>
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <form action="/admin.php" method="POST">
                <div class="form-group">
                    <label for="username">Usuario:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Iniciar Sesión</button>
            </form>
        </div>
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
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="container">
            <h1>Panel de Administración</h1>
            <nav class="admin-nav">
                <ul>
                    <li><a href="?section=personal_info">Info Personal</a></li>
                    <li><a href="?section=experience">Experiencia</a></li>
                    <li><a href="?section=education">Educación</a></li>
                    <li><a href="?section=skills">Habilidades</a></li>
                    <li><a href="?section=languages">Idiomas</a></li>
                    <li><a href="?section=projects">Proyectos</a></li>
                    <li><a href="?section=certificates">Certificados</a></li>
                    <li><a href="?section=documents">Documentos</a></li>
                    <li><a href="?section=other_webs">Otras Webs</a></li>
                    <li><a href="?section=upload_cv">Cargar CV</a></li>
                    <li><a href="?action=logout" class="logout-btn">Cerrar Sesión</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="admin-main">
        <div class="container">
            <?php
            $section = $_GET['section'] ?? 'dashboard'; // Sección por defecto
            echo "<h2>Bienvenido, " . htmlspecialchars($_SESSION['username']) . "</h2>";
            echo "<p>Selecciona una sección del menú para gestionar el contenido.</p>";

            switch ($section) {
                case 'personal_info':
                    echo "<h3>Gestión de Información Personal</h3>";
                    $personal_info = getPersonalInfo();
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal_info'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $data = [
                                'name' => sanitizeInput($_POST['name']),
                                'phone' => sanitizeInput($_POST['phone']),
                                'email' => sanitizeInput($_POST['email']),
                                'profile_summary' => sanitizeInput($_POST['profile_summary'])
                            ];
                            if (updateTable('personal_info', $data, $personal_info['id'] ?? 1)) {
                                echo "<p class='success-message'>Información personal actualizada exitosamente.</p>";
                                $personal_info = getPersonalInfo(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar la información personal.</p>";
                            }
                        }
                    }
                    ?>
                    <form action="admin_panel.php?section=personal_info" method="POST" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="name">Nombre Completo:</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($personal_info['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Teléfono:</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($personal_info['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($personal_info['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="profile_summary">Resumen del Perfil:</label>
                            <textarea id="profile_summary" name="profile_summary" rows="10" required><?php echo htmlspecialchars($personal_info['profile_summary'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="update_personal_info" class="btn">Actualizar Información Personal</button>
                    </form>
                    <?php
                    break;
                case 'experience':
                    echo "<h3>Gestión de Experiencia</h3>";
                    $experiences = getExperience();

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_experience'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $data = [
                                'title' => sanitizeInput($_POST['title']),
                                'company' => sanitizeInput($_POST['company']),
                                'start_date' => sanitizeInput($_POST['start_date']),
                                'end_date' => sanitizeInput($_POST['end_date']),
                                'location' => sanitizeInput($_POST['location']),
                                'description' => sanitizeInput($_POST['description'])
                            ];
                            if (insertIntoTable('experience', $data)) {
                                echo "<p class='success-message'>Experiencia añadida exitosamente.</p>";
                                $experiences = getExperience(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al añadir experiencia.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_experience'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
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
                                echo "<p class='success-message'>Experiencia actualizada exitosamente.</p>";
                                $experiences = getExperience(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar experiencia.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_experience'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            if (deleteFromTable('experience', $id)) {
                                echo "<p class='success-message'>Experiencia eliminada exitosamente.</p>";
                                $experiences = getExperience(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al eliminar experiencia.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Añadir Nueva Experiencia</h4>
                    <form action="admin_panel.php?section=experience" method="POST" class="admin-form">
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

                    <form action="admin_panel.php?section=experience" method="POST" id="reorder-experience-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="experience_order" id="experience-order-input">
                        <button type="submit" name="reorder_experience" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <h4>Experiencias Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Empresa</th>
                                    <th>Fechas</th>
                                    <th>Ubicación</th>
                                    <th>Descripción</th>
                                    <th>Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="experience-sortable">
                                <?php if (!empty($experiences)): ?>
                                    <?php foreach ($experiences as $exp): ?>
                                        <tr data-id="<?php echo $exp['id']; ?>">
                                            <td><?php echo htmlspecialchars($exp['title']); ?></td>
                                            <td><?php echo htmlspecialchars($exp['company']); ?></td>
                                            <td><?php echo htmlspecialchars($exp['start_date'] . ' - ' . $exp['end_date']); ?></td>
                                            <td><?php echo htmlspecialchars($exp['location']); ?></td>
                                            <td><?php echo htmlspecialchars($exp['description']); ?></td>
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i> <?php echo $exp['order_index'] ?? ''; ?></td>
                                            <td>
                                                <a href="?section=experience&action=edit&id=<?php echo $exp['id']; ?>" class="btn btn-small btn-edit">Editar</a>
                                                <form action="admin_panel.php?section=experience" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                                                    <button type="submit" name="delete_experience" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta experiencia?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && (int)$_GET['id'] === $exp['id']): ?>
                                            <tr>
                                                <td colspan="7">
                                                    <div class="edit-form-container">
                                                        <h5>Editar Experiencia</h5>
                                                        <form action="admin_panel.php?section=experience" method="POST" class="admin-form">
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
                                                            <a href="admin_panel.php?section=experience" class="btn btn-small btn-secondary">Cancelar</a>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">No hay experiencias registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="admin_panel.php?section=experience" method="POST" id="reorder-experience-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="experience_order" id="experience-order-input">
                        <button type="submit" name="reorder_experience" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var el = document.getElementById('experience-sortable');
                            if (el) {
                                var sortable = Sortable.create(el, {
                                    animation: 150,
                                    handle: '.order-handle',
                                    onEnd: function (evt) {
                                        var order = [];
                                        el.children.forEach(function(row) {
                                            order.push(row.dataset.id);
                                        });
                                        document.getElementById('experience-order-input').value = JSON.stringify(order);
                                        document.getElementById('reorder-experience-form').submit();
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    break;
                case 'education':
                    echo "<h3>Gestión de Educación</h3>";
                    $education_entries = getEducation();

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_education'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $data = [
                                'degree' => sanitizeInput($_POST['degree']),
                                'institution' => sanitizeInput($_POST['institution']),
                                'start_date' => sanitizeInput($_POST['start_date']),
                                'end_date' => sanitizeInput($_POST['end_date']),
                                'location' => sanitizeInput($_POST['location'])
                            ];
                            if (insertIntoTable('education', $data)) {
                                echo "<p class='success-message'>Entrada de educación añadida exitosamente.</p>";
                                $education_entries = getEducation(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al añadir entrada de educación.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_education'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            $data = [
                                'degree' => sanitizeInput($_POST['degree']),
                                'institution' => sanitizeInput($_POST['institution']),
                                'start_date' => sanitizeInput($_POST['start_date']),
                                'end_date' => sanitizeInput($_POST['end_date']),
                                'location' => sanitizeInput($_POST['location'])
                            ];
                            if (updateTable('education', $data, $id)) {
                                echo "<p class='success-message'>Entrada de educación actualizada exitosamente.</p>";
                                $education_entries = getEducation(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar entrada de educación.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_education'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            if (deleteFromTable('education', $id)) {
                                echo "<p class='success-message'>Entrada de educación eliminada exitosamente.</p>";
                                $education_entries = getEducation(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al eliminar entrada de educación.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_education'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $education_ids_in_order = json_decode($_POST['education_order'], true);
                            if (reorderEducation($education_ids_in_order)) {
                                echo "<p class='success-message'>Orden de educación actualizado exitosamente.</p>";
                                $education_entries = getEducation(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar el orden de educación.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Añadir Nueva Entrada de Educación</h4>
                    <form action="admin_panel.php?section=education" method="POST" class="admin-form">
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

                    <h4>Entradas de Educación Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Título/Grado</th>
                                    <th>Institución</th>
                                    <th>Fechas</th>
                                    <th>Ubicación</th>
                                    <th>Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="education-sortable">
                                <?php if (!empty($education_entries)): ?>
                                    <?php foreach ($education_entries as $edu): ?>
                                        <tr data-id="<?php echo $edu['id']; ?>">
                                            <td><?php echo htmlspecialchars($edu['degree']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['institution']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['start_date'] . ' - ' . $edu['end_date']); ?></td>
                                            <td><?php echo htmlspecialchars($edu['location']); ?></td>
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i> <?php echo $edu['order_index']; ?></td>
                                            <td>
                                                <a href="?section=education&action=edit&id=<?php echo $edu['id']; ?>" class="btn btn-small btn-edit">Editar</a>
                                                <form action="admin_panel.php?section=education" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $edu['id']; ?>">
                                                    <button type="submit" name="delete_education" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta entrada de educación?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && (int)$_GET['id'] === $edu['id']): ?>
                                            <tr>
                                                <td colspan="6">
                                                    <div class="edit-form-container">
                                                        <h5>Editar Educación</h5>
                                                        <form action="admin_panel.php?section=education" method="POST" class="admin-form">
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
                                                            <a href="admin_panel.php?section=education" class="btn btn-small btn-secondary">Cancelar</a>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">No hay entradas de educación registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="admin_panel.php?section=education" method="POST" id="reorder-education-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="education_order" id="education-order-input">
                        <button type="submit" name="reorder_education" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var el = document.getElementById('education-sortable');
                            if (el) {
                                var sortable = Sortable.create(el, {
                                    animation: 150,
                                    handle: '.order-handle',
                                    onEnd: function (evt) {
                                        var order = [];
                                        el.children.forEach(function(row) {
                                            order.push(row.dataset.id);
                                        });
                                        document.getElementById('education-order-input').value = JSON.stringify(order);
                                        document.getElementById('reorder-education-form').submit();
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    break;
                case 'skills':
                    echo "<h3>Gestión de Habilidades</h3>";
                    $skills = getSkills();

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_skill'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $data = [
                                'category' => sanitizeInput($_POST['category']),
                                'name' => sanitizeInput($_POST['name']),
                                'level' => sanitizeInput($_POST['level'])
                            ];
                            if (insertIntoTable('skills', $data)) {
                                echo "<p class='success-message'>Habilidad añadida exitosamente.</p>";
                                $skills = getSkills(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al añadir habilidad.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_skill'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            $data = [
                                'category' => sanitizeInput($_POST['category']),
                                'name' => sanitizeInput($_POST['name']),
                                'level' => sanitizeInput($_POST['level'])
                            ];
                            if (updateTable('skills', $data, $id)) {
                                echo "<p class='success-message'>Habilidad actualizada exitosamente.</p>";
                                $skills = getSkills(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar habilidad.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_skill'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            if (deleteFromTable('skills', $id)) {
                                echo "<p class='success-message'>Habilidad eliminada exitosamente.</p>";
                                $skills = getSkills(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al eliminar habilidad.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_skills'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $skill_ids_in_order = json_decode($_POST['skill_order'], true);
                            if (reorderSkills($skill_ids_in_order)) {
                                echo "<p class='success-message'>Orden de habilidades actualizado exitosamente.</p>";
                                $skills = getSkills(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar el orden de habilidades.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Añadir Nueva Habilidad</h4>
                    <form action="admin_panel.php?section=skills" method="POST" class="admin-form">
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

                    <h4>Habilidades Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Nombre</th>
                                    <th>Nivel</th>
                                    <th>Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="skills-sortable">
                                <?php if (!empty($skills)): ?>
                                    <?php foreach ($skills as $skill): ?>
                                        <tr data-id="<?php echo $skill['id']; ?>">
                                            <td><?php echo htmlspecialchars($skill['category']); ?></td>
                                            <td><?php echo htmlspecialchars($skill['name']); ?></td>
                                            <td><?php echo htmlspecialchars($skill['level']); ?></td>
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i> <?php echo $skill['order_index']; ?></td>
                                            <td>
                                                <a href="?section=skills&action=edit&id=<?php echo $skill['id']; ?>" class="btn btn-small btn-edit">Editar</a>
                                                <form action="admin_panel.php?section=skills" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $skill['id']; ?>">
                                                    <button type="submit" name="delete_skill" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta habilidad?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && (int)$_GET['id'] === $skill['id']): ?>
                                            <tr>
                                                <td colspan="5">
                                                    <div class="edit-form-container">
                                                        <h5>Editar Habilidad</h5>
                                                        <form action="admin_panel.php?section=skills" method="POST" class="admin-form">
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
                                                            <a href="admin_panel.php?section=skills" class="btn btn-small btn-secondary">Cancelar</a>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No hay habilidades registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="admin_panel.php?section=skills" method="POST" id="reorder-skills-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="skill_order" id="skill-order-input">
                        <button type="submit" name="reorder_skills" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var el = document.getElementById('skills-sortable');
                            if (el) {
                                var sortable = Sortable.create(el, {
                                    animation: 150,
                                    handle: '.order-handle',
                                    onEnd: function (evt) {
                                        var order = [];
                                        el.children.forEach(function(row) {
                                            order.push(row.dataset.id);
                                        });
                                        document.getElementById('skill-order-input').value = JSON.stringify(order);
                                        document.getElementById('reorder-skills-form').submit();
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    break;
                case 'projects':
                    echo "<h3>Gestión de Proyectos</h3>";
                    $projects = getProjects();

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $data = [
                                'title' => sanitizeInput($_POST['title']),
                                'start_date' => sanitizeInput($_POST['start_date']),
                                'end_date' => sanitizeInput($_POST['end_date']),
                                'description' => sanitizeInput($_POST['description']),
                                'url' => sanitizeInput($_POST['url'])
                            ];
                            if (insertIntoTable('projects', $data)) {
                                echo "<p class='success-message'>Proyecto añadido exitosamente.</p>";
                                $projects = getProjects(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al añadir proyecto.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_project'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            $data = [
                                'title' => sanitizeInput($_POST['title']),
                                'start_date' => sanitizeInput($_POST['start_date']),
                                'end_date' => sanitizeInput($_POST['end_date']),
                                'description' => sanitizeInput($_POST['description']),
                                'url' => sanitizeInput($_POST['url'])
                            ];
                            if (updateTable('projects', $data, $id)) {
                                echo "<p class='success-message'>Proyecto actualizado exitosamente.</p>";
                                $projects = getProjects(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar proyecto.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $id = (int)$_POST['id'];
                            if (deleteFromTable('projects', $id)) {
                                echo "<p class='success-message'>Proyecto eliminado exitosamente.</p>";
                                $projects = getProjects(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al eliminar proyecto.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Añadir Nuevo Proyecto</h4>
                    <form action="admin_panel.php?section=projects" method="POST" class="admin-form">
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
                        <button type="submit" name="add_project" class="btn">Añadir Proyecto</button>
                    </form>

                    <h4>Proyectos Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Fechas</th>
                                    <th>Descripción</th>
                                    <th>URL</th>
                                    <th>Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="projects-sortable">
                                <?php if (!empty($projects)): ?>
                                    <?php foreach ($projects as $project): ?>
                                        <tr data-id="<?php echo $project['id']; ?>">
                                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                                            <td><?php echo htmlspecialchars($project['start_date'] . ' - ' . $project['end_date']); ?></td>
                                            <td><?php echo htmlspecialchars($project['description']); ?></td>
                                            <td><a href="<?php echo htmlspecialchars($project['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($project['url']); ?></a></td>
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i> <?php echo $project['order_index'] ?? ''; ?></td>
                                            <td>
                                                <a href="?section=projects&action=edit&id=<?php echo $project['id']; ?>" class="btn btn-small btn-edit">Editar</a>
                                                <form action="admin_panel.php?section=projects" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                                    <button type="submit" name="delete_project" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar este proyecto?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && (int)$_GET['id'] === $project['id']): ?>
                                            <tr>
                                                <td colspan="6">
                                                    <div class="edit-form-container">
                                                        <h5>Editar Proyecto</h5>
                                                        <form action="admin_panel.php?section=projects" method="POST" class="admin-form">
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
                                                            <button type="submit" name="edit_project" class="btn btn-small">Guardar Cambios</button>
                                                            <a href="admin_panel.php?section=projects" class="btn btn-small btn-secondary">Cancelar</a>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No hay proyectos registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="admin_panel.php?section=projects" method="POST" id="reorder-projects-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="project_order" id="project-order-input">
                        <button type="submit" name="reorder_projects" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var el = document.getElementById('projects-sortable');
                            if (el) {
                                var sortable = Sortable.create(el, {
                                    animation: 150,
                                    handle: '.order-handle',
                                    onEnd: function (evt) {
                                        var order = [];
                                        el.children.forEach(function(row) {
                                            order.push(row.dataset.id);
                                        });
                                        document.getElementById('project-order-input').value = JSON.stringify(order);
                                        document.getElementById('reorder-projects-form').submit();
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    break;
                case 'certificates':
                    echo "<h3>Gestión de Certificados</h3>";
                    $certificates = getFiles('certificate'); // Asumiendo que 'certificate' es el tipo de archivo para certificados

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_certificate'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            require_once 'upload.php';
                            $uploadResult = handleFileUpload('certificate_file', 'UPLOAD_DIR_CERTIFICATES', 'ALLOWED_IMAGE_TYPES', 'certificate');
                            if ($uploadResult['success']) {
                                echo "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                                $certificates = getFiles('certificate'); // Recargar datos
                            } else {
                                echo "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_certificate'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
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
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_certificates'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $certificate_ids_in_order = json_decode($_POST['certificate_order'], true);
                            if (reorderFiles($certificate_ids_in_order)) { // Usar reorderFiles para certificados
                                echo "<p class='success-message'>Orden de certificados actualizado exitosamente.</p>";
                                $certificates = getFiles('certificate'); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar el orden de certificados.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Añadir Nuevo Certificado</h4>
                    <form action="admin_panel.php?section=certificates" method="POST" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="certificate_file">Archivo de Certificado (PDF/Imagen):</label>
                            <input type="file" id="certificate_file" name="certificate_file" accept=".pdf,image/*" required>
                        </div>
                        <div class="form-group">
                            <label for="certificate_name">Nombre del Certificado:</label>
                            <input type="text" id="certificate_name" name="name" required>
                        </div>
                        <button type="submit" name="add_certificate" class="btn">Subir Certificado</button>
                    </form>

                    <h4>Certificados Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Archivo</th>
                                    <th>Fecha de Subida</th>
                                    <th>Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="certificates-sortable">
                                <?php if (!empty($certificates)): ?>
                                    <?php foreach ($certificates as $cert): ?>
                                        <tr data-id="<?php echo $cert['id']; ?>">
                                            <td><?php echo htmlspecialchars($cert['file_name']); ?></td>
                                            <td><a href="../../public/assets/certificados/<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" rel="noopener">Ver Archivo</a></td>
                                            <td><?php echo htmlspecialchars($cert['uploaded_at']); ?></td>
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i> <?php echo $cert['order_index'] ?? ''; ?></td>
                                            <td>
                                                <form action="admin_panel.php?section=certificates" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $cert['id']; ?>">
                                                    <button type="submit" name="delete_certificate" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar este certificado?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No hay certificados registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="admin_panel.php?section=certificates" method="POST" id="reorder-certificates-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="certificate_order" id="certificate-order-input">
                        <button type="submit" name="reorder_certificates" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var el = document.getElementById('certificates-sortable');
                            if (el) {
                                var sortable = Sortable.create(el, {
                                    animation: 150,
                                    handle: '.order-handle',
                                    onEnd: function (evt) {
                                        var order = [];
                                        el.children.forEach(function(row) {
                                            order.push(row.dataset.id);
                                        });
                                        document.getElementById('certificate-order-input').value = JSON.stringify(order);
                                        document.getElementById('reorder-certificates-form').submit();
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    break;
                case 'documents':
                    echo "<h3>Gestión de Documentos</h3>";
                    $documents = getFiles('document'); // Asumiendo que 'document' es el tipo de archivo para documentos

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            require_once 'upload.php';
                            $uploadResult = handleFileUpload('document_file', 'UPLOAD_DIR_DOCUMENTS', 'ALLOWED_DOCUMENT_TYPES', 'document');
                            if ($uploadResult['success']) {
                                echo "<p class='success-message'>" . $uploadResult['message'] . "</p>";
                                $documents = getFiles('document'); // Recargar datos
                            } else {
                                echo "<p class='error-message'>" . $uploadResult['message'] . "</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
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
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_documents'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $document_ids_in_order = json_decode($_POST['document_order'], true);
                            if (reorderFiles($document_ids_in_order)) { // Usar reorderFiles para documentos
                                echo "<p class='success-message'>Orden de documentos actualizado exitosamente.</p>";
                                $documents = getFiles('document'); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar el orden de documentos.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Añadir Nuevo Documento</h4>
                    <form action="admin_panel.php?section=documents" method="POST" enctype="multipart/form-data" class="admin-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <div class="form-group">
                            <label for="document_file">Archivo de Documento (PDF/DOCX):</label>
                            <input type="file" id="document_file" name="document_file" accept=".pdf,.doc,.docx" required>
                        </div>
                        <div class="form-group">
                            <label for="document_name">Nombre del Documento:</label>
                            <input type="text" id="document_name" name="name" required>
                        </div>
                        <button type="submit" name="add_document" class="btn">Subir Documento</button>
                    </form>

                    <h4>Documentos Existentes</h4>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Archivo</th>
                                    <th>Fecha de Subida</th>
                                    <th>Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="documents-sortable">
                                <?php if (!empty($documents)): ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr data-id="<?php echo $doc['id']; ?>">
                                            <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                            <td><a href="../../public/assets/documentos/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" rel="noopener">Ver Archivo</a></td>
                                            <td><?php echo htmlspecialchars($doc['uploaded_at']); ?></td>
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i> <?php echo $doc['order_index'] ?? ''; ?></td>
                                            <td>
                                                <form action="admin_panel.php?section=documents" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                                                    <button type="submit" name="delete_document" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar este documento?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No hay documentos registrados.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="admin_panel.php?section=documents" method="POST" id="reorder-documents-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="document_order" id="document-order-input">
                        <button type="submit" name="reorder_documents" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var el = document.getElementById('documents-sortable');
                            if (el) {
                                var sortable = Sortable.create(el, {
                                    animation: 150,
                                    handle: '.order-handle',
                                    onEnd: function (evt) {
                                        var order = [];
                                        el.children.forEach(function(row) {
                                            order.push(row.dataset.id);
                                        });
                                        document.getElementById('document-order-input').value = JSON.stringify(order);
                                        document.getElementById('reorder-documents-form').submit();
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    break;
                case 'other_webs':
                    echo "<h3>Gestión de Otras Webs</h3>";
                    $other_webs = getOtherWebs(); // Usar la función específica que ordena por order_index

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
                                $other_webs = fetchAllFromTable('other_webs'); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al eliminar web.</p>";
                            }
                        }
                    }
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_other_webs'])) {
                        if (!verifyCsrfToken($_POST['csrf_token'])) {
                            echo "<p class='error-message'>Error de seguridad: Token CSRF inválido.</p>";
                        } else {
                            $other_web_ids_in_order = json_decode($_POST['other_web_order'], true);
                            if (reorderOtherWebs($other_web_ids_in_order)) {
                                echo "<p class='success-message'>Orden de otras webs actualizado exitosamente.</p>";
                                $other_webs = getOtherWebs(); // Recargar datos
                            } else {
                                echo "<p class='error-message'>Error al actualizar el orden de otras webs.</p>";
                            }
                        }
                    }
                    ?>
                    <h4>Añadir Nueva Web</h4>
                    <form action="admin_panel.php?section=other_webs" method="POST" class="admin-form">
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
                                    <th>Título</th>
                                    <th>Descripción</th>
                                    <th>URL</th>
                                    <th>Orden</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="other-webs-sortable">
                                <?php if (!empty($other_webs)): ?>
                                    <?php foreach ($other_webs as $web): ?>
                                        <tr data-id="<?php echo $web['id']; ?>">
                                            <td><?php echo htmlspecialchars($web['title']); ?></td>
                                            <td><?php echo htmlspecialchars($web['description']); ?></td>
                                            <td><a href="<?php echo htmlspecialchars($web['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($web['url']); ?></a></td>
                                            <td class="order-handle"><i class="fas fa-grip-vertical"></i> <?php echo $web['order_index'] ?? ''; ?></td>
                                            <td>
                                                <a href="?section=other_webs&action=edit&id=<?php echo $web['id']; ?>" class="btn btn-small btn-edit">Editar</a>
                                                <form action="admin_panel.php?section=other_webs" method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                    <input type="hidden" name="id" value="<?php echo $web['id']; ?>">
                                                    <button type="submit" name="delete_other_web" class="btn btn-small btn-delete" onclick="return confirm('¿Estás seguro de que quieres eliminar esta web?');">Eliminar</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php if (isset($_GET['action']) && $_GET['action'] === 'edit' && (int)$_GET['id'] === $web['id']): ?>
                                            <tr>
                                                <td colspan="5">
                                                    <div class="edit-form-container">
                                                        <h5>Editar Web</h5>
                                                        <form action="admin_panel.php?section=other_webs" method="POST" class="admin-form">
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
                                                            <a href="admin_panel.php?section=other_webs" class="btn btn-small btn-secondary">Cancelar</a>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">No hay otras webs registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <form action="admin_panel.php?section=other_webs" method="POST" id="reorder-other-webs-form" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="other_web_order" id="other-web-order-input">
                        <button type="submit" name="reorder_other_webs" class="btn">Guardar Nuevo Orden</button>
                    </form>

                    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var el = document.getElementById('other-webs-sortable');
                            if (el) {
                                var sortable = Sortable.create(el, {
                                    animation: 150,
                                    handle: '.order-handle',
                                    onEnd: function (evt) {
                                        var order = [];
                                        el.children.forEach(function(row) {
                                            order.push(row.dataset.id);
                                        });
                                        document.getElementById('other-web-order-input').value = JSON.stringify(order);
                                        document.getElementById('reorder-other-webs-form').submit();
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    break;
                case 'upload_cv':
                    echo "<h3>Cargar CV (DOCX)</h3>";
                    require_once 'upload.php'; // Incluir el manejador de subidas

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
                    <form action="admin_panel.php?section=upload_cv" method="POST" enctype="multipart/form-data" class="admin-form">
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
            }
            ?>
        </div>
    </main>

    <footer class="admin-footer">
        <div class="container">
            <p>&copy; 2023 Panel de Administración - Randy Rodríguez Vidovic</p>
        </div>
    </footer>
</body>
</html>
