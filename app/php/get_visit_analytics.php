<?php
require_once 'db.php';
require_once 'auth.php'; // Para isAuthenticated() y checkSessionActivity()

// Asegurarse de que solo los usuarios autenticados puedan acceder a esta API
if (!isAuthenticated()) {
    http_response_code(403); // Prohibido
    echo json_encode(['error' => 'Acceso no autorizado.']);
    exit;
}

header('Content-Type: application/json');

$data = [];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'total_views':
        $data = ['total_views' => getTotalPageViews()];
        break;
    case 'views_by_day':
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
        $data = getPageViewsLastDays($days);
        break;
    case 'top_pages':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $data = getTopVisitedPages($limit);
        break;
    case 'referrers':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $data = getPageViewsByReferrer($limit);
        break;
    default:
        echo json_encode(['error' => 'Acción no especificada o inválida.']);
        exit;
}

echo json_encode($data);
?>