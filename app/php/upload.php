<?php
// app/php/upload.php - Maneja la subida segura de archivos

require_once 'config.php';
require_once 'db.php';
require_once 'auth.php'; // Para sanitizar y verificar CSRF

// Asegurarse de que solo usuarios autenticados puedan subir archivos
if (!isAuthenticated()) {
    header("Location: admin_panel.php");
    exit();
}

function handleFileUpload($fileInputName, $uploadDirConstant, $allowedTypesConstant, $fileTypeForDb) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo.'];
    }

    $file = $_FILES[$fileInputName];
    $fileName = sanitizeInput($file['name']);
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];

    // Validaciones de seguridad
    if ($fileError !== 0) {
        return ['success' => false, 'message' => 'Error en la subida: ' . $fileError];
    }

    if ($fileSize > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande. Tamaño máximo: ' . (MAX_FILE_SIZE / (1024 * 1024)) . 'MB.'];
    }

    $allowedTypes = constant($allowedTypesConstant);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido. Tipos permitidos: ' . implode(', ', $allowedTypes)];
    }

    // Generar un nombre de archivo único para evitar colisiones y ataques de path traversal
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $newFileName = uniqid('', true) . '.' . $fileExt;
    $uploadPath = constant($uploadDirConstant) . $newFileName;

    // Asegurarse de que el directorio de subida exista
    if (!is_dir(constant($uploadDirConstant))) {
        mkdir(constant($uploadDirConstant), 0777, true); // Crear directorio si no existe
    }

    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        // Guardar información en la base de datos
        $data = [
            'file_name' => $fileName,
            'file_path' => $newFileName, // Guardar solo el nombre único
            'file_type' => $fileTypeForDb
        ];
        if (insertIntoTable('files', $data)) {
            return ['success' => true, 'message' => 'Archivo subido y registrado exitosamente.', 'file_path' => $newFileName];
        } else {
            // Si falla el registro en DB, intentar eliminar el archivo subido
            unlink($uploadPath);
            return ['success' => false, 'message' => 'Error al registrar el archivo en la base de datos.'];
        }
    } else {
        return ['success' => false, 'message' => 'Error al mover el archivo subido.'];
    }
}

// Lógica para manejar la subida de CV
function handleCVUpload($fileInputName) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo CV.'];
    }

    $file = $_FILES[$fileInputName];
    $fileName = sanitizeInput($file['name']);
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];

    // Validaciones de seguridad para CV
    if ($fileError !== 0) {
        return ['success' => false, 'message' => 'Error en la subida del CV: ' . $fileError];
    }

    if ($fileSize > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'El archivo CV es demasiado grande. Tamaño máximo: ' . (MAX_FILE_SIZE / (1024 * 1024)) . 'MB.'];
    }

    $allowedTypes = ALLOWED_CV_TYPES;
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo CV no permitido. Tipos permitidos: ' . implode(', ', $allowedTypes)];
    }

    // Generar un nombre de archivo único para el CV
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $newFileName = 'cv_' . uniqid('', true) . '.' . $fileExt;
    $uploadPath = UPLOAD_DIR_DOCUMENTS . $newFileName; // Guardar CV en la carpeta de documentos

    if (!is_dir(UPLOAD_DIR_DOCUMENTS)) {
        mkdir(UPLOAD_DIR_DOCUMENTS, 0777, true);
    }

    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        // Llamar al parser de CV
        require_once 'cv_parser.php';
        $parseResult = parseAndSaveCV($uploadPath);

        if ($parseResult['success']) {
            // Opcional: registrar el CV subido en la tabla 'files' si se desea mantener un historial
            $data = [
                'file_name' => $fileName,
                'file_path' => $newFileName,
                'file_type' => 'cv'
            ];
            insertIntoTable('files', $data); // No es crítico si falla, el CV ya fue parseado

            return ['success' => true, 'message' => 'CV subido, parseado y datos actualizados exitosamente.'];
        } else {
            // Si falla el parseo, eliminar el archivo subido
            unlink($uploadPath);
            return ['success' => false, 'message' => 'CV subido, pero error al parsear: ' . $parseResult['message']];
        }
    } else {
        return ['success' => false, 'message' => 'Error al mover el archivo CV subido.'];
    }
}

function handleProfileImageUpload($fileInputName) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir la imagen de perfil.'];
    }

    $file = $_FILES[$fileInputName];
    $fileName = sanitizeInput($file['name']);
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileType = $file['type'];

    if ($fileError !== 0) {
        return ['success' => false, 'message' => 'Error en la subida de la imagen: ' . $fileError];
    }

    if ($fileSize > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'La imagen es demasiado grande. Tamaño máximo: ' . (MAX_FILE_SIZE / (1024 * 1024)) . 'MB.'];
    }

    $allowedTypes = ALLOWED_IMAGE_TYPES;
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de imagen no permitido. Tipos permitidos: ' . implode(', ', $allowedTypes)];
    }

    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $newFileName = 'profile_' . uniqid('', true) . '.' . $fileExt;
    $uploadPath = UPLOAD_DIR_PROFILE_PIC . $newFileName;

    if (!is_dir(UPLOAD_DIR_PROFILE_PIC)) {
        mkdir(UPLOAD_DIR_PROFILE_PIC, 0777, true);
    }

    if (move_uploaded_file($fileTmpName, $uploadPath)) {
        return ['success' => true, 'message' => 'Imagen de perfil subida exitosamente.', 'file_path' => $newFileName];
    } else {
        return ['success' => false, 'message' => 'Error al mover la imagen de perfil subida.'];
    }
}

?>