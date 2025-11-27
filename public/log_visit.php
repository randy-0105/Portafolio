<?php
require_once '../app/php/db.php';
require_once '../app/php/utils.php'; // Para getUserIpAddr()

$page_url = $_SERVER['REQUEST_URI'] ?? 'unknown'; // URL de la página actual
$ip_address = getUserIpAddr();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
$referrer = $_SERVER['HTTP_REFERER'] ?? null;

logPageView($page_url, $ip_address, $user_agent, $referrer);

// Opcional: devolver una imagen 1x1 transparente para que el navegador no muestre un icono roto
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICRAEAOw==');
?>