<?php
// app/php/db.php - Maneja la conexión a la base de datos SQLite y operaciones CRUD

require_once 'config.php'; // Incluye el archivo de configuración

function getDatabaseConnection() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $maxRetries = 10; // Aumentar el número de reintentos
    $retryDelay = 50; // milisegundos

    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Configurar un timeout para las operaciones de SQLite
            $pdo->exec('PRAGMA busy_timeout = 30000'); // 30 segundos
            return $pdo;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'locked') !== false && $i < $maxRetries - 1) {
                usleep($retryDelay * 1000); // Convertir a microsegundos
                $retryDelay *= 2; // Backoff exponencial
                error_log("DB: Base de datos bloqueada, reintentando... Intento: " . ($i + 1) . ". Error: " . $e->getMessage());
            } else {
                error_log("DB: Error de conexión a la base de datos después de " . ($i + 1) . " intentos: " . $e->getMessage());
                die("Error de conexión a la base de datos.");
            }
        }
    }
    die("Error fatal: No se pudo obtener una conexión a la base de datos después de múltiples reintentos.");
}

function fetchAllFromTable($tableName) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT * FROM " . $tableName);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchByIdFromTable($tableName, $id) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT * FROM " . $tableName . " WHERE id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function insertIntoTable($tableName, $data) {
    $db = getDatabaseConnection();
    $columns = implode(", ", array_keys($data));
    $placeholders = ":" . implode(", :", array_keys($data));
    $stmt = $db->prepare("INSERT INTO " . $tableName . " ($columns) VALUES ($placeholders)");
    return $stmt->execute($data);
}

function updateTable($tableName, $data, $id) {
    $db = getDatabaseConnection();
    $set = "";
    foreach ($data as $column => $value) {
        $set .= "$column = :$column, ";
    }
    $set = rtrim($set, ", ");
    $stmt = $db->prepare("UPDATE " . $tableName . " SET $set WHERE id = :id");
    $data['id'] = $id;
    return $stmt->execute($data);
}

function deleteFromTable($tableName, $id) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("DELETE FROM " . $tableName . " WHERE id = :id");
    return $stmt->execute(['id' => $id]);
}

// Funciones específicas para el portafolio
function getPersonalInfo() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT name, profile_summary, profile_image_path FROM personal_info LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getContactMethods() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT * FROM contact_methods");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reorderContactMethods($contact_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($contact_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE contact_methods SET order_index = :order_index WHERE id = :id");
            $stmt->execute(['order_index' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar métodos de contacto: " . $e->getMessage());
        return false;
    }
}

function getExperience() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT * FROM experience ORDER BY order_index ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reorderExperience($experience_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($experience_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE experience SET order_index = :order_index WHERE id = :id");
            $stmt->execute(['order_index' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar experiencias: " . $e->getMessage());
        return false;
    }
}

function getEducation() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT * FROM education ORDER BY order_index ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reorderEducation($education_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($education_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE education SET order_index = :order_index WHERE id = :id");
            $stmt->execute(['order_index' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar educación: " . $e->getMessage());
        return false;
    }
}

function getSkills() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT * FROM skills ORDER BY order_index ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reorderSkills($skill_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($skill_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE skills SET order_index = :order_index WHERE id = :id");
            $stmt->execute(['order_index' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar habilidades: " . $e->getMessage());
        return false;
    }
}

function getLanguages() {
    return fetchAllFromTable('languages');
}

function getEntrepreneurship() {
    return fetchAllFromTable('entrepreneurship');
}

function getAchievements() {
    return fetchAllFromTable('achievements');
}

function getSocialServices() {
    return fetchAllFromTable('social_services');
}

function getProjects() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT * FROM projects ORDER BY order_index ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reorderProjects($project_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($project_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE projects SET order_index = :order_index WHERE id = :id");
            $stmt->execute(['order_index' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar proyectos: " . $e->getMessage());
        return false;
    }
}

function getOtherWebs() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT * FROM other_webs ORDER BY order_index ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reorderOtherWebs($other_web_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($other_web_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE other_webs SET order_index = :order_index WHERE id = :id");
            $stmt->execute(['order_index' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar otras webs: " . $e->getMessage());
        return false;
    }
}

function getFiles($fileType = null) {
    $db = getDatabaseConnection();
    $sql = "SELECT * FROM files";
    $params = [];
    if ($fileType) {
        $sql .= " WHERE file_type = :file_type";
        $params['file_type'] = $fileType;
    }
    $sql .= " ORDER BY order_index ASC"; // Añadir ordenamiento por order_index
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function reorderFiles($file_ids_in_order) {
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        foreach ($file_ids_in_order as $index => $id) {
            $stmt = $db->prepare("UPDATE files SET order_index = :order_index WHERE id = :id");
            $stmt->execute(['order_index' => $index + 1, 'id' => $id]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al reordenar archivos: " . $e->getMessage());
        return false;
    }
}

function getUserEmail($userId) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT email FROM admin_users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['email'] ?? null;
}




function logPageView($page_url, $ip_address, $user_agent, $referrer = null) {
    $db = getDatabaseConnection();
    try {
        $stmt = $db->prepare("INSERT INTO page_views (page_url, ip_address, user_agent, referrer) VALUES (:page_url, :ip_address, :user_agent, :referrer)");
        $stmt->execute([
            'page_url' => $page_url,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'referrer' => $referrer
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Error al registrar visita a la página: " . $e->getMessage());
        return false;
    }
}

function getTotalPageViews() {
    $db = getDatabaseConnection();
    $stmt = $db->query("SELECT COUNT(*) FROM page_views");
    return $stmt->fetchColumn();
}

function getPageViewsLastDays($days = 7) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT DATE(visit_time) as visit_date, COUNT(*) as views FROM page_views WHERE visit_time >= DATE('now', '-:days day') GROUP BY visit_date ORDER BY visit_date ASC");
    $stmt->execute([':days' => $days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTopVisitedPages($limit = 5) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT page_url, COUNT(*) as views FROM page_views GROUP BY page_url ORDER BY views DESC LIMIT :limit");
    $stmt->execute([':limit' => $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPageViewsByReferrer($limit = 5) {
    $db = getDatabaseConnection();
    $stmt = $db->prepare("SELECT referrer, COUNT(*) as views FROM page_views WHERE referrer IS NOT NULL AND referrer != '' GROUP BY referrer ORDER BY views DESC LIMIT :limit");
    $stmt->execute([':limit' => $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
