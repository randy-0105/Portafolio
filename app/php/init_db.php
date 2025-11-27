<?php

$databaseFile = __DIR__ . '/../sqlite/database.sqlite3';

if (!file_exists($databaseFile)) {
    try {
        $pdo = new PDO('sqlite:' . $databaseFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Función auxiliar para verificar si una columna existe
        function columnExists($pdo, $tableName, $columnName) {
            $stmt = $pdo->query("PRAGMA table_info($tableName);");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $column) {
                if ($column['name'] === $columnName) {
                    return true;
                }
            }
            return false;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS personal_info (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                profile_summary TEXT,
                profile_image_path TEXT DEFAULT NULL
            );",
            "CREATE TABLE IF NOT EXISTS contact_methods (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                value TEXT NOT NULL,
                order_index INTEGER DEFAULT 0,
                personal_info_id INTEGER,
                FOREIGN KEY (personal_info_id) REFERENCES personal_info(id) ON DELETE CASCADE
            );",
            "CREATE TABLE IF NOT EXISTS experience (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                company TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                location TEXT,
                description TEXT,
                order_index INTEGER DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS education (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                degree TEXT NOT NULL,
                institution TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                location TEXT,
                order_index INTEGER DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS skills (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                name TEXT NOT NULL,
                level TEXT,
                order_index INTEGER DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS languages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                level TEXT,
                order_index INTEGER DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS entrepreneurship (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                location TEXT,
                description TEXT
            );",
            "CREATE TABLE IF NOT EXISTS achievements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                date TEXT,
                description TEXT
            );",
            "CREATE TABLE IF NOT EXISTS social_services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                description TEXT
            );",
            "CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                description TEXT,
                url TEXT,
                image_path TEXT DEFAULT NULL,
                order_index INTEGER DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS other_webs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                url TEXT NOT NULL,
                order_index INTEGER DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS certificates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                issuing_organization TEXT,
                issue_date TEXT,
                expiration_date TEXT,
                credential_id TEXT,
                credential_url TEXT,
                file_path TEXT,
                order_index INTEGER DEFAULT 0
            );",
            "CREATE TABLE IF NOT EXISTS admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                email TEXT UNIQUE,
                verification_email TEXT, -- Nueva columna para el correo de verificación
                reset_token TEXT,
                reset_token_expires_at DATETIME,
                twofa_code TEXT,
                twofa_code_expires_at DATETIME
            );",
            "CREATE TABLE IF NOT EXISTS user_security_questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                question_text TEXT NOT NULL,
                answer_hash TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
            );",
            "CREATE TABLE IF NOT EXISTS files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                file_name TEXT NOT NULL,
                file_path TEXT NOT NULL,
                file_type TEXT NOT NULL,
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                order_index INTEGER DEFAULT 0,
                description TEXT DEFAULT NULL
            );",
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT,
                ip_address TEXT NOT NULL,
                attempt_time INTEGER NOT NULL
            );",
            "CREATE TABLE IF NOT EXISTS access_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                username TEXT,
                ip_address TEXT NOT NULL,
                action TEXT NOT NULL, -- 'login_success', 'login_failure', 'logout'
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                user_agent TEXT,
                details TEXT, -- Nueva columna para detalles
                FOREIGN KEY (user_id) REFERENCES admin_users(id)
            );",
            "CREATE TABLE IF NOT EXISTS sections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                display_order INTEGER NOT NULL,
                content_type TEXT NOT NULL,
                is_visible INTEGER DEFAULT 1
            );",
            "CREATE TABLE IF NOT EXISTS page_views (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                page_url TEXT NOT NULL,
                ip_address TEXT,
                user_agent TEXT,
                referrer TEXT
            );"
        ];

        foreach ($queries as $query) {
            $pdo->exec($query);
        }

        // Asegurarse de que las columnas order_index existan en las tablas que las necesitan
        if (!columnExists($pdo, 'projects', 'order_index')) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN order_index INTEGER DEFAULT 0;");
        }
        if (!columnExists($pdo, 'other_webs', 'order_index')) {
            $pdo->exec("ALTER TABLE other_webs ADD COLUMN order_index INTEGER DEFAULT 0;");
        }
        if (!columnExists($pdo, 'files', 'order_index')) {
            $pdo->exec("ALTER TABLE files ADD COLUMN order_index INTEGER DEFAULT 0;");
        }
        if (!columnExists($pdo, 'certificates', 'order_index')) {
            $pdo->exec("ALTER TABLE certificates ADD COLUMN order_index INTEGER DEFAULT 0;");
        }

        // Populate personal_info table with default data if it's empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM personal_info");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO personal_info (name, profile_summary, profile_image_path) VALUES ('Randy Rodríguez Vidovic', 'Ingeniero en Sistemas en formación con experiencia sólida en desarrollo web, automatización de procesos y asistencia virtual. Cofundador de Wittyssoft, organización enfocada en soluciones digitales como sitios web, aplicaciones móviles, de escritorio y automatizaciones. He trabajado con clientes locales e internacionales aplicando herramientas como WordPress, Python, Figma y N8N. Me caracterizo por el liderazgo, la creatividad y el compromiso con la innovación tecnológica.', 'profile.jpg')");
        }

        // Populate contact_methods table with default data if it's empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM contact_methods");
        if ($stmt->fetchColumn() == 0) {
            $default_contact_methods = [
                ['type' => 'phone', 'value' => '+58 4146643022'],
                ['type' => 'email', 'value' => 'randy.vidovic13@gmail.com']
            ];
            $insert_stmt = $pdo->prepare("INSERT INTO contact_methods (type, value) VALUES (:type, :value)");
            foreach ($default_contact_methods as $method) {
                $insert_stmt->execute($method);
            }
        }

        // Incluir el nuevo parser de HTML
        require_once 'html_parser.php';
        $portfolioData = parsePortfolioData(__DIR__ . '/../../public/index.html');

        if ($portfolioData) {
            // Populate experience table with data from index.html ONLY IF IT'S EMPTY
            $stmt = $pdo->query("SELECT COUNT(*) FROM experience");
            if ($stmt->fetchColumn() == 0) {
                $insert_stmt = $pdo->prepare("INSERT INTO experience (title, company, start_date, end_date, location, description, order_index) VALUES (:title, :company, :start_date, :end_date, :location, :description, :order_index)");
                foreach ($portfolioData['experience'] as $index => $entry) {
                    $entry['order_index'] = $index + 1;
                    $insert_stmt->execute($entry);
                }
            }

            // Populate projects table with data from index.html ONLY IF IT'S EMPTY
            $stmt = $pdo->query("SELECT COUNT(*) FROM projects");
            if ($stmt->fetchColumn() == 0) {
                $insert_stmt = $pdo->prepare("INSERT INTO projects (title, start_date, end_date, description, url, image_path, order_index) VALUES (:title, :start_date, :end_date, :description, :url, :image_path, :order_index)");
                foreach ($portfolioData['projects'] as $index => $entry) {
                    $entry['order_index'] = $index + 1;
                    $insert_stmt->execute($entry);
                }
            }
        }

        // Populate files table for documents with data from index.html
        $pdo->exec("DELETE FROM files WHERE file_type = 'document';"); // Eliminar datos existentes para resincronizar
        $real_files_entries = [
            ['file_name' => 'Mi CV (PDF)', 'file_path' => 'CV - RANDY J.R.V.pdf', 'file_type' => 'document', 'description' => 'Mi CV (PDF)']
        ];
        $insert_stmt = $pdo->prepare("INSERT INTO files (file_name, file_path, file_type, description, order_index) VALUES (:file_name, :file_path, :file_type, :description, :order_index)");
        foreach ($real_files_entries as $index => $entry) {
            $entry['order_index'] = $index + 1;
            $insert_stmt->execute($entry);
        }

        // Populate education table with default data if it's empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM education");
        if ($stmt->fetchColumn() == 0) {
            $default_education_entries = [
                ['degree' => 'Ingeniero en Sistemas (Cursando 9no Semestre)', 'institution' => 'Instituto Universitario Politécnico Santiago Mariño', 'start_date' => 'Sep 2021', 'end_date' => 'Presente', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Curso de Marketing Digital', 'institution' => 'Centro Educativo Logros', 'start_date' => 'Jun 2024', 'end_date' => '', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Curso de Asistente Contable', 'institution' => 'Centro Educativo Logros', 'start_date' => 'Mar 2022', 'end_date' => '', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Bachiller', 'institution' => 'Unidad Educativa Privada Auyantepuy', 'start_date' => 'Jun 2021', 'end_date' => '', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Técnico Básico en Mecánica Automotriz', 'institution' => 'Centro Educativo Logros', 'start_date' => 'Ago 2019', 'end_date' => '', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Técnico Básico en Refrigeración Comercial', 'institution' => 'Centro Educativo Logros', 'start_date' => 'Jul 2019', 'end_date' => '', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Técnico Básico en Electricidad', 'institution' => 'Centro Educativo Logros', 'start_date' => 'Ago 2018', 'end_date' => '', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Técnico Básico en Refrigeración', 'institution' => 'Centro Educativo Logros', 'start_date' => 'Ago 2018', 'end_date' => '', 'location' => 'Venezuela, Maracaibo'],
                ['degree' => 'Técnico Básico en Electrónica', 'institution' => 'Centro Educativo Logros', 'start_date' => 'Jun 2018', 'end_date' => '', 'location' => 'Venezuela, Maracaibo']
            ];
            $insert_stmt = $pdo->prepare("INSERT INTO education (degree, institution, start_date, end_date, location, order_index) VALUES (:degree, :institution, :start_date, :end_date, :location, :order_index)");
            foreach ($default_education_entries as $index => $entry) {
                $entry['order_index'] = $index + 1; // Asignar un order_index basado en el orden de la lista
                $insert_stmt->execute($entry);
            }
        }

        // Populate sections table with default data if it's empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM sections");
        if ($stmt->fetchColumn() == 0) {
            $default_sections = [
                ['name' => 'Info Personal', 'slug' => 'personal_info', 'display_order' => 1, 'content_type' => 'personal_info'],
                ['name' => 'Experiencia', 'slug' => 'experience', 'display_order' => 2, 'content_type' => 'experience'],
                ['name' => 'Educación', 'slug' => 'education', 'display_order' => 3, 'content_type' => 'education'],
                ['name' => 'Habilidades', 'slug' => 'skills', 'display_order' => 4, 'content_type' => 'skills'],
                ['name' => 'Proyectos', 'slug' => 'projects', 'display_order' => 5, 'content_type' => 'cards'],
                ['name' => 'Certificados', 'slug' => 'certificates', 'display_order' => 6, 'content_type' => 'documents'],
                ['name' => 'Documentos', 'slug' => 'documents', 'display_order' => 7, 'content_type' => 'documents'],
                ['name' => 'Otras Webs', 'slug' => 'other_webs', 'display_order' => 8, 'content_type' => 'links'],
                ['name' => 'Cargar CV', 'slug' => 'upload_cv', 'display_order' => 9, 'content_type' => 'upload']
            ];

            $insert_stmt = $pdo->prepare("INSERT INTO sections (name, slug, display_order, content_type) VALUES (:name, :slug, :display_order, :content_type)");

            foreach ($default_sections as $section) {
                $insert_stmt->execute($section);
            }
        }

        // Populate other_webs table with data from index.html ONLY IF IT'S EMPTY
        if ($portfolioData) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM other_webs");
            if ($stmt->fetchColumn() == 0) {
                $insert_stmt = $pdo->prepare("INSERT INTO other_webs (title, description, url, order_index) VALUES (:title, :description, :url, :order_index)");
                foreach ($portfolioData['other_webs'] as $index => $entry) {
                    $entry['order_index'] = $index + 1;
                    $insert_stmt->execute($entry);
                }
            }
        }

        // Populate certificates table with default data if it's empty
        $pdo->exec("DELETE FROM certificates;"); // Eliminar datos existentes para resincronizar
        $default_certificates_entries = [
            ['title' => 'Certificado de Desarrollo Web', 'issuing_organization' => 'Coursera', 'issue_date' => '2023-01-15', 'expiration_date' => null, 'credential_id' => 'ABC123DEF456', 'credential_url' => 'https://coursera.org/verify/ABC123DEF456', 'file_path' => 'certificado_web.pdf'],
            ['title' => 'Certificado de Python para Data Science', 'issuing_organization' => 'edX', 'issue_date' => '2022-06-30', 'expiration_date' => null, 'credential_id' => 'GHI789JKL012', 'credential_url' => 'https://edx.org/verify/GHI789JKL012', 'file_path' => 'certificado_python.pdf']
        ];
        $insert_stmt = $pdo->prepare("INSERT INTO certificates (title, issuing_organization, issue_date, expiration_date, credential_id, credential_url, file_path, order_index) VALUES (:title, :issuing_organization, :issue_date, :expiration_date, :credential_id, :credential_url, :file_path, :order_index)");
        foreach ($default_certificates_entries as $index => $entry) {
            $entry['order_index'] = $index + 1;
            $insert_stmt->execute($entry);
        }

        // Populate skills table with default data if it's empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM skills");
        if ($stmt->fetchColumn() == 0) {
            $default_skills_entries = [
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'Python', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'Java', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'HTML', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'CSS', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'JavaScript', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'PHP', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'C++', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'Visual Basic', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'WordPress', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'Elementor', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'Figma', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'N8N', 'level' => ''],
                ['category' => 'Lenguajes de Programación / Tecnologías', 'name' => 'Make', 'level' => ''],
            ];
            $insert_stmt = $pdo->prepare("INSERT INTO skills (category, name, level, order_index) VALUES (:category, :name, :level, :order_index)");
            foreach ($default_skills_entries as $index => $entry) {
                $entry['order_index'] = $index + 1;
                $insert_stmt->execute($entry);
            }
        }

        // Populate languages table with default data if it's empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM languages");
        if ($stmt->fetchColumn() == 0) {
            $default_languages_entries = [
                ['name' => 'Inglés', 'level' => 'B2 Básico Funcional'],
                ['name' => 'Español', 'level' => 'Nativo']
            ];
            $insert_stmt = $pdo->prepare("INSERT INTO languages (name, level, order_index) VALUES (:name, :level, :order_index)");
            foreach ($default_languages_entries as $index => $entry) {
                $entry['order_index'] = $index + 1;
                $insert_stmt->execute($entry);
            }
        }

        // Las preguntas de seguridad por defecto se manejarán en auth.php al crear el usuario admin.
        // No es necesario insertar preguntas genéricas aquí.

        // Incluir auth.php para tener acceso a la función createDefaultAdminUser
        require_once 'auth.php';
        // Crear el usuario administrador por defecto si no existe
        createDefaultAdminUser();

    } catch (PDOException $e) {
        echo "Error al crear la base de datos: " . $e->getMessage() . "\n";
    }
}
?>
