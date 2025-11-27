<?php
session_start();
include 'db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

// Función para obtener los datos del CV
function getCVData($db) {
    $stmt = $db->prepare("SELECT * FROM cv_data LIMIT 1");
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para actualizar los datos del CV
function updateCVData($db, $data) {
    $stmt = $db->prepare("UPDATE cv_data SET name = ?, email = ?, phone = ?, experience = ?, education = ?, skills = ? WHERE id = 1");
    return $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['experience'], $data['education'], $data['skills']]);
}

// Manejo de la actualización del CV
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'experience' => $_POST['experience'],
        'education' => $_POST['education'],
        'skills' => $_POST['skills']
    ];
    if (updateCVData($db, $data)) {
        $message = "CV actualizado con éxito.";
    } else {
        $message = "Error al actualizar el CV.";
    }
}

// Obtener los datos actuales del CV
$cvData = getCVData($db);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Portafolio</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="admin-container">
        <h1>Administración del Portafolio</h1>
        <?php if (isset($message)) : ?>
            <p><?php echo $message; ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="name">Nombre:</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($cvData['name']); ?>" required>

            <label for="email">Correo Electrónico:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($cvData['email']); ?>" required>

            <label for="phone">Teléfono:</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($cvData['phone']); ?>" required>

            <label for="experience">Experiencia:</label>
            <textarea id="experience" name="experience" required><?php echo htmlspecialchars($cvData['experience']); ?></textarea>

            <label for="education">Educación:</label>
            <textarea id="education" name="education" required><?php echo htmlspecialchars($cvData['education']); ?></textarea>

            <label for="skills">Habilidades:</label>
            <textarea id="skills" name="skills" required><?php echo htmlspecialchars($cvData['skills']); ?></textarea>

            <button type="submit">Actualizar CV</button>
        </form>
        <a href="upload.php">Subir Documentos</a>
        <a href="auth.php?logout=true">Cerrar Sesión</a>
    </div>
</body>
</html>