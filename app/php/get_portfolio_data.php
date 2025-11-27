<?php
require_once 'db.php';
require_once 'utils.php'; // Para sanitizeInput si es necesario, aunque aquí solo se obtienen datos

header('Content-Type: application/json');

$data = [];
$section = $_GET['section'] ?? '';

switch ($section) {
    case 'experience':
        $data = getExperience();
        break;
    case 'projects':
        $data = getProjects();
        break;
    case 'other_webs':
        $data = getOtherWebs();
        break;
    case 'education': // Aunque ya funciona, lo incluimos para consistencia
        $data = getEducation();
        break;
    // Añadir más casos según sea necesario para otras secciones
    default:
        echo json_encode(['error' => 'Sección no especificada o inválida.']);
        exit;
}

echo json_encode($data);
?>