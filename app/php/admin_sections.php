<?php
// app/php/admin_sections.php - Funciones para la gestión de secciones del panel de administración

require_once 'db.php';
require_once 'auth.php'; // Para sanitizar y verificar CSRF

// Función para obtener todas las secciones
function getSections() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT * FROM sections ORDER BY display_order ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para añadir una nueva sección
function addSection($name, $content_type) {
    $db = getDatabaseConnection();
    // Obtener el siguiente order_index
    $stmt = $db->query("SELECT MAX(order_index) FROM sections");
    $maxOrder = $stmt->fetchColumn();
    $newOrder = $maxOrder + 1;

    $data = [
        'name' => sanitizeInput($name),
        'content_type' => sanitizeInput($content_type),
        'order_index' => $newOrder
    ];
    return insertIntoTable('sections', $data);
}

// Función para renombrar una sección
function renameSection($id, $new_name) {
    $db = getDatabaseConnection();
    $data = ['name' => sanitizeInput($new_name)];
    return updateTable('sections', $data, $id);
}

// Función para eliminar una sección
function deleteSection($id) {
    return deleteFromTable('sections', $id);
}

// Función para reordenar secciones
function reorderSections($section_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($section_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE sections SET display_order = :display_order WHERE id = :id");
            $stmt->execute(['display_order' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar secciones: " . $e->getMessage());
        return false;
    }
}

// Tipos de contenido permitidos para las secciones
define('ALLOWED_CONTENT_TYPES', ['texto', 'lado a lado', 'tarjetas', 'documentos', 'links']);

?>