<?php

// Función para validar contraseña fuerte
function isStrongPassword($password) {
    // Mínimo 12 caracteres
    // Al menos una letra mayúscula
    // Al menos una letra minúscula
    // Al menos un número
    // Al menos un carácter especial
    return strlen($password) >= 12 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[a-z]/', $password) &&
           preg_match('/[0-9]/', $password) &&
           preg_match('/[^A-Za-z0-9]/', $password);
}

// Función para generar un slug a partir de un texto
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text); // Reemplaza no-letras/dígitos con guiones
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); // Translitera caracteres internacionales
    $text = preg_replace('~[^-\w]+~', '', $text); // Elimina cualquier cosa que no sea guiones o palabras
    $text = trim($text, '-'); // Elimina guiones del principio y final
    $text = preg_replace('~-+~', '-', $text); // Colapsa múltiples guiones
    $text = strtolower($text); // Convierte a minúsculas

    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

?>