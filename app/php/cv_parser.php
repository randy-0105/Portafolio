<?php
// app/php/cv_parser.php - Lógica para parsear un archivo CV (DOCX)

require_once 'config.php';
require_once 'db.php';

// Función para extraer texto de un archivo DOCX (rudimentario)
function extractTextFromDocx($filePath) {
    $zip = new ZipArchive;
    if ($zip->open($filePath) === TRUE) {
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return false; // No se pudo encontrar el archivo XML principal
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);

        // Extraer texto de los nodos <w:t>
        $text = '';
        $paragraphs = $dom->getElementsByTagName('p');
        foreach ($paragraphs as $paragraph) {
            $text .= $paragraph->textContent . "\n";
        }
        return $text;
    }
    return false; // No se pudo abrir el archivo DOCX
}

// Función para parsear el texto del CV y guardarlo en la base de datos
function parseAndSaveCV($filePath) {
    $text = extractTextFromDocx($filePath);

    if ($text === false) {
        return ['success' => false, 'message' => 'Error al extraer texto del DOCX.'];
    }

    $data = [
        'personal_info' => [],
        'experience' => [],
        'education' => [],
        'skills' => [],
        'languages' => [],
        'entrepreneurship' => [],
        'achievements' => [],
        'social_services' => [],
        'projects' => []
    ];

    // Dividir el CV en secciones usando los encabezados como delimitadores
    $sections = preg_split('/^(PERFIL PROFESIONAL|EXPERIENCIA LABORAL|FORMACIÓN|COMPETENCIAS|IDIOMAS|EMPRENDIMIENTOS|LOGROS CLAVE|SERVICIOS SOCIALES Y PROFESIONALES|PROYECTOS DESTACADOS)\s*\n/m', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    $currentSection = '';
    $sectionContent = '';

    foreach ($sections as $i => $part) {
        if (in_array(trim($part), ['PERFIL PROFESIONAL', 'EXPERIENCIA LABORAL', 'FORMACIÓN', 'COMPETENCIAS', 'IDIOMAS', 'EMPRENDIMIENTOS', 'LOGROS CLAVE', 'SERVICIOS SOCIALES Y PROFESIONALES', 'PROYECTOS DESTACADOS'])) {
            if ($currentSection !== '') {
                // Procesar la sección anterior antes de cambiar a la nueva
                $data = processSection($currentSection, $sectionContent, $data);
            }
            $currentSection = trim($part);
            $sectionContent = '';
        } else {
            $sectionContent .= $part;
        }
    }
    // Procesar la última sección
    if ($currentSection !== '') {
        $data = processSection($currentSection, $sectionContent, $data);
    }

    // Extraer información personal (fuera de las secciones principales ya que está al inicio)
    if (preg_match('/RANDY RODRÍGUEZ VIDOVIC\s*\n\s*\n\s*(\+?[0-9\s]+)\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s*\n/', $text, $matches)) {
        $data['personal_info'] = [
            'name' => 'RANDY RODRÍGUEZ VIDOVIC',
            'phone' => str_replace(' ', '', $matches[1]),
            'email' => $matches[2],
            'profile_summary' => $data['personal_info']['profile_summary'] ?? '' // Se llenará desde la sección de perfil
        ];
    }


    // Guardar en la base de datos
    $db = getDatabaseConnection();
    $db->beginTransaction();
    try {
        // Limpiar tablas existentes antes de insertar nuevos datos
        $db->exec("DELETE FROM personal_info");
        $db->exec("DELETE FROM experience");
        $db->exec("DELETE FROM education");
        $db->exec("DELETE FROM skills");
        $db->exec("DELETE FROM languages");
        $db->exec("DELETE FROM entrepreneurship");
        $db->exec("DELETE FROM achievements");
        $db->exec("DELETE FROM social_services");
        $db->exec("DELETE FROM projects");

        // Insertar información personal
        if (!empty($data['personal_info'])) {
            // Intentar actualizar si ya existe, si no, insertar
            $existingInfo = getPersonalInfo();
            if ($existingInfo) {
                updateTable('personal_info', $data['personal_info'], $existingInfo['id']);
            } else {
                insertIntoTable('personal_info', $data['personal_info']);
            }
        }

        // Insertar experiencia
        foreach ($data['experience'] as $item) {
            insertIntoTable('experience', $item);
        }

        // Insertar educación
        foreach ($data['education'] as $item) {
            insertIntoTable('education', $item);
        }

        // Insertar habilidades
        foreach ($data['skills'] as $item) {
            insertIntoTable('skills', $item);
        }

        // Insertar idiomas
        foreach ($data['languages'] as $item) {
            insertIntoTable('languages', $item);
        }

        // Insertar emprendimientos
        foreach ($data['entrepreneurship'] as $item) {
            insertIntoTable('entrepreneurship', $item);
        }

        // Insertar logros
        foreach ($data['achievements'] as $item) {
            insertIntoTable('achievements', $item);
        }

        // Insertar servicios sociales
        foreach ($data['social_services'] as $item) {
            insertIntoTable('social_services', $item);
        }

        // Insertar proyectos
        foreach ($data['projects'] as $item) {
            insertIntoTable('projects', $item);
        }

        $db->commit();
        return ['success' => true, 'message' => 'CV parseado y guardado exitosamente.'];

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error al guardar CV en la base de datos: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al guardar CV en la base de datos: ' . $e->getMessage()];
    }
}

function processSection($sectionName, $content, $data) {
    $content = trim($content);

    switch ($sectionName) {
        case 'PERFIL PROFESIONAL':
            $data['personal_info']['profile_summary'] = $content;
            break;
        case 'EXPERIENCIA LABORAL':
            preg_match_all('/(.*?)\s*I\s*(.*?)\s*I\s*(.*?)\s*-\s*(.*?)\s*\(\s*(.*?)\s*\)\s*.*?(\w{3}\s*\d{4}\s*-\s*(?:\w{3}\s*\d{4}|Presente))\s*-\s*(.*?)\s*\n(.*?)(?=\n\n|\nCofundador I|\nDesarrollo de Web|\nAnalista de Inventario|\nAsistente Virtual|\nGerente general|\nAtención al Cliente|\nFORMACIÓN|$)/s', $content, $matches_exp, PREG_SET_ORDER);
            foreach ($matches_exp as $match) {
                $data['experience'][] = [
                    'title' => trim($match[1] . ' I ' . $match[2] . ' I ' . $match[3]),
                    'company' => trim($match[4] . ' (' . $match[5] . ')'),
                    'start_date' => trim(explode('-', $match[6])[0]),
                    'end_date' => trim(explode('-', $match[6])[1]),
                    'location' => trim($match[7]),
                    'description' => trim($match[8])
                ];
            }
            break;
        case 'FORMACIÓN':
            preg_match_all('/(.*?)\s*–\s*(.*?)\s*-\s*(.*?)\s*.*?(\w{3}\s*\d{4}\s*-\s*(?:\w{3}\s*\d{4}|Presente))\s*.*?(\w+)\s*\n(.*?)(?=\n\n|\nCentro Educativo Logros|\nUnidad Educativa Privada Auyantepuy|\nCOMPETENCIAS|$)/s', $content, $matches_edu, PREG_SET_ORDER);
            foreach ($matches_edu as $match) {
                $data['education'][] = [
                    'degree' => trim($match[1]),
                    'institution' => trim($match[2]),
                    'start_date' => trim(explode('-', $match[4])[0]),
                    'end_date' => trim(explode('-', $match[4])[1]),
                    'location' => trim($match[5])
                ];
            }
            break;
        case 'COMPETENCIAS':
            $skill_lines = explode("\n", trim($content));
            $current_category = '';
            foreach ($skill_lines as $line) {
                if (trim($line) === '') continue;

                if (preg_match('/^(.*?)\s*…………………………\s*Experiencia$/', $line, $skill_match)) {
                    $data['skills'][] = [
                        'category' => 'Lenguajes de Programación / Tecnologías', // Asumiendo categoría por defecto
                        'name' => trim($skill_match[1]),
                        'level' => 'Experiencia'
                    ];
                } else if (preg_match('/^(.*?)\s*…………………\s*Experiencia$/', $line, $skill_match)) {
                    $data['skills'][] = [
                        'category' => 'Herramientas de Diseño / Ofimática', // Asumiendo categoría por defecto
                        'name' => trim($skill_match[1]),
                        'level' => 'Experiencia'
                    ];
                } else if (preg_match('/^(.*?)\s*…………………\s*Experiencia$/', $line, $skill_match)) {
                    $data['skills'][] = [
                        'category' => 'Habilidades Blandas', // Asumiendo categoría por defecto
                        'name' => trim($skill_match[1]),
                        'level' => 'Experiencia'
                    ];
                }
            }
            break;
        case 'IDIOMAS':
            $language_lines = explode("\n", trim($content));
            foreach ($language_lines as $line) {
                if (trim($line) === '') continue;
                if (preg_match('/(.*?)\s*-\s*(.*?)\s*…………………………/', $line, $lang_match)) {
                    $data['languages'][] = [
                        'name' => trim($lang_match[1]),
                        'level' => trim($lang_match[2])
                    ];
                }
            }
            break;
        case 'EMPRENDIMIENTOS':
            preg_match_all('/(.*?)\s*\(\s*(.*?)\s*\)\s*………………………….*?(\w{3}\s*\d{4}\s*-\s*(?:\w{3}\s*\d{4}|Presente))\s*-\s*(.*?)\s*\n(.*?)(?=\n\n|\nLOGROS CLAVE|$)/s', $content, $matches_entre, PREG_SET_ORDER);
            foreach ($matches_entre as $match) {
                $data['entrepreneurship'][] = [
                    'name' => trim($match[1] . ' (' . $match[2] . ')'),
                    'start_date' => trim(explode('-', $match[3])[0]),
                    'end_date' => trim(explode('-', $match[3])[1]),
                    'location' => trim($match[4]),
                    'description' => trim($match[5])
                ];
            }
            break;
        case 'LOGROS CLAVE':
            preg_match_all('/(.*?)\s*-\s*(.*?)\s*………………………….*?(\w{3}\s*\d{4}\s*-\s*(?:\w{3}\s*\d{4}|Presente))/s', $content, $matches_achieve, PREG_SET_ORDER);
            foreach ($matches_achieve as $match) {
                $data['achievements'][] = [
                    'title' => trim($match[1] . ' - ' . $match[2]),
                    'date' => trim($match[3]),
                    'description' => '' // No hay descripción en el CV para logros
                ];
            }
            break;
        case 'SERVICIOS SOCIALES Y PROFESIONALES':
            preg_match_all('/(.*?)\s*………………………….*?(\w{3}\s*\d{4}\s*-\s*(?:\w{3}\s*\d{4}|Presente))/s', $content, $matches_social, PREG_SET_ORDER);
            foreach ($matches_social as $match) {
                $data['social_services'][] = [
                    'title' => trim($match[1]),
                    'start_date' => trim(explode('-', $match[2])[0]),
                    'end_date' => trim(explode('-', $match[2])[1]),
                    'description' => '' // No hay descripción en el CV para servicios sociales
                ];
            }
            break;
        case 'PROYECTOS DESTACADOS':
            preg_match_all('/(.*?)\s*………………………….*?(\w{3}\s*\d{4}\s*–\s*(?:\w{3}\s*\d{4}|Presente))\s*\n(.*?)(?=\n\n|\nSitio Web para el Centro|\nDron Automatizado|$)/s', $content, $matches_proj, PREG_SET_ORDER);
            foreach ($matches_proj as $match) {
                $data['projects'][] = [
                    'title' => trim($match[1]),
                    'start_date' => trim(explode('–', $match[2])[0]),
                    'end_date' => trim(explode('–', $match[2])[1]),
                    'description' => trim($match[3]),
                    'url' => '' // URL no está en el CV
                ];
            }
            break;
    }
    return $data;
}

?>