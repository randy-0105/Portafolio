<?php
// app/php/html_parser.php - Lógica para parsear el archivo index.html

function parsePortfolioData($htmlFilePath) {
    if (!file_exists($htmlFilePath)) {
        return null;
    }

    $html = file_get_contents($htmlFilePath);
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    $data = [
        'experience' => [],
        'projects' => [],
        'other_webs' => []
    ];

    // Extraer Experiencia
    $experienceNodes = $xpath->query("//section[@id='experience']//div[contains(@class, 'experience-item')]");
    foreach ($experienceNodes as $node) {
        $data['experience'][] = [
            'title' => $xpath->evaluate('string(.//h3)', $node),
            'company' => $xpath->evaluate('string(.//p[1])', $node),
            'start_date' => '', // No disponible directamente, se puede dejar en blanco o parsear de la descripción
            'end_date' => '', // No disponible directamente
            'location' => '', // No disponible directamente
            'description' => $xpath->evaluate('string(.//p[2])', $node)
        ];
    }

    // Extraer Proyectos
    $projectNodes = $xpath->query("//section[@id='projects']//div[contains(@class, 'project-card')]");
    foreach ($projectNodes as $node) {
        $data['projects'][] = [
            'title' => $xpath->evaluate('string(.//h3)', $node),
            'start_date' => '', // No disponible directamente
            'end_date' => '', // No disponible directamente
            'description' => $xpath->evaluate('string(.//p)', $node),
            'url' => $xpath->evaluate('string(.//a/@href)', $node),
            'image_path' => $xpath->evaluate('string(.//img/@src)', $node)
        ];
    }

    // Extraer Otras Webs
    $otherWebsNodes = $xpath->query("//section[@id='other-webs']//div[contains(@class, 'other-web-item')]");
    foreach ($otherWebsNodes as $node) {
        $data['other_webs'][] = [
            'title' => $xpath->evaluate('string(.//h3)', $node),
            'description' => $xpath->evaluate('string(.//p)', $node),
            'url' => $xpath->evaluate('string(.//a/@href)', $node)
        ];
    }

    return $data;
}
?>
