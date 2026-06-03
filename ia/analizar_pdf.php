<?php
/**
 * Extrae texto de un PDF usando métodos disponibles en el sistema.
 * Prioridad: pdftotext (exec) > smalot/pdfparser (Composer) > extracción básica raw
 */
function extraer_texto_pdf($ruta_pdf) {
    // Método 1: pdftotext (común en Linux hosting)
    $texto = extraer_con_pdftotext($ruta_pdf);
    if ($texto !== null && strlen(trim($texto)) >= 50) return trim($texto);

    // Método 2: smalot/pdfparser via Composer
    $texto = extraer_con_pdfparser($ruta_pdf);
    if ($texto !== null && strlen(trim($texto)) >= 50) return trim($texto);

    // Método 3: Extracción raw básica
    $texto = extraer_raw_pdf($ruta_pdf);
    if ($texto !== null && strlen(trim($texto)) >= 20) return trim($texto);

    return '';
}

function extraer_con_pdftotext($ruta_pdf) {
    $ruta_escapada = escapeshellarg($ruta_pdf);
    $output = null;
    $retval = null;
    $cmd = "pdftotext $ruta_escapada - 2>/dev/null";
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = "pdftotext.exe $ruta_escapada - 2>NUL";
    }
    exec($cmd, $output, $retval);
    if ($retval === 0 && !empty($output)) {
        return implode("\n", $output);
    }
    return null;
}

function extraer_con_pdfparser($ruta_pdf) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) return null;
    require_once $autoload;
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($ruta_pdf);
        return $pdf->getText();
    } catch (Exception $e) {
        return null;
    }
}

function extraer_raw_pdf($ruta_pdf) {
    $contenido = file_get_contents($ruta_pdf);
    if ($contenido === false) return null;

    $texto = '';

    // Extraer texto entre paréntesis en operadores Tj y TJ
    preg_match_all('/\(([^)]*)\)\s*Tj/iu', $contenido, $matches);
    $texto .= implode(' ', $matches[1]) . ' ';

    // Extraer texto de operadores TJ (arrays de cadenas)
    preg_match_all('/\[([^\]]*)\]\s*TJ/iu', $contenido, $matches_tj);
    foreach ($matches_tj[1] as $block) {
        preg_match_all('/\(([^)]*)\)/u', $block, $parts);
        $texto .= implode('', $parts[1]) . ' ';
    }

    // Extraer texto en hex <hex> Tj
    preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/iu', $contenido, $hex_matches);
    foreach ($hex_matches[1] as $hex) {
        $texto .= hex2bin($hex) . ' ';
    }

    // Limpiar caracteres de control y escapes PDF
    $texto = preg_replace('/\\\\[rn]/u', "\n", $texto);
    $texto = preg_replace('/\\\\[()\\\\]/u', '', $texto);
    $texto = preg_replace('/[^\p{L}\p{N}\s\.\,\;\:\!\?\-\/]/u', ' ', $texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);

    return trim($texto);
}

/**
 * Predice el área usando el modelo exportado a JSON
 */
function predecir_area($texto_limpio) {
    $modelo_json = __DIR__ . '/modelo_export.json';
    if (!file_exists($modelo_json)) {
        // Fallback: si no hay modelo, usar predicción por palabras clave
        return predecir_area_keyword($texto_limpio);
    }
    $modelo = json_decode(file_get_contents($modelo_json), true);
    if (!$modelo) {
        return predecir_area_keyword($texto_limpio);
    }

    $features = $modelo['features'];
    $classes = $modelo['classes'];
    $coef = $modelo['coef'];
    $intercept = $modelo['intercept'];

    $tokens = preg_split('/\s+/', trim($texto_limpio));

    // Contar unigrams y bigrams que existen en el vocabulario
    $vector = array_fill(0, count($features), 0);
    for ($i = 0; $i < count($tokens); $i++) {
        // Unigram
        $unigram = $tokens[$i];
        $idx = array_search($unigram, $features);
        if ($idx !== false) $vector[$idx]++;

        // Bigram
        if ($i + 1 < count($tokens)) {
            $bigram = $tokens[$i] . ' ' . $tokens[$i + 1];
            $idx = array_search($bigram, $features);
            if ($idx !== false) $vector[$idx]++;
        }
    }

    // Calcular scores por clase (logistic regression)
    $scores = [];
    foreach ($classes as $j => $class) {
        $score = $intercept[$j];
        for ($i = 0; $i < count($features); $i++) {
            if ($vector[$i] > 0) {
                $score += $vector[$i] * $coef[$j][$i];
            }
        }
        $scores[$class] = $score;
    }

    // Softmax para obtener probabilidades
    $max_score = max($scores);
    $exp_sum = 0;
    $exp_scores = [];
    foreach ($scores as $class => $score) {
        $exp_scores[$class] = exp($score - $max_score);
        $exp_sum += $exp_scores[$class];
    }

    $probabilidades = [];
    foreach ($exp_scores as $class => $e) {
        $probabilidades[$class] = $e / $exp_sum;
    }

    arsort($probabilidades);
    $pred_class = array_key_first($probabilidades);
    $confianza = round($probabilidades[$pred_class] * 100, 1);

    return [
        'area' => $pred_class,
        'confianza' => $confianza
    ];
}

/**
 * Predicción por palabras clave cuando no hay modelo exportado
 */
function predecir_area_keyword($texto) {
    $texto_lower = mb_strtolower($texto, 'UTF-8');
    $areas_keywords = [
        'Concejo Municipal' => ['ordenanza', 'concejo', 'sesion', 'reglamento', 'mocion', 'comision', 'dictamen', 'regidor', 'acuerdo de concejo'],
        'Alcaldia' => ['alcalde', 'alcaldia', 'decreto de alcaldia', 'audiencia', 'ceremonia', 'protocolar', 'donacion', 'feriado', 'convenio', 'nombramiento'],
        'Gerencia Municipal' => ['licencia', 'permiso', 'autorizacion', 'funcionamiento', 'rotulo', 'construccion', 'giro', 'terraza', 'publicidad', 'feria', 'espectaculo', 'negocio', 'restaurante', 'renovacion', 'cartel'],
        'Oficina General de Atención al Ciudadano y Gestión Documental' => ['certificado domiciliario', 'presentacion', 'seguimiento', 'expediente', 'recepcion', 'declaracion jurada', 'certificado de domicilio', 'atencion al ciudadano', 'partida', 'orientacion', 'mesa de partes', 'reclamo', 'ventanilla'],
        'Oficina General de Administración' => ['adquisicion', 'contratacion', 'mantenimiento', 'inventario', 'material de oficina', 'cas', 'reposicion', 'alta de equipos', 'baja de bienes', 'conformidad', 'orden de compra', 'contrato', 'vigilancia', 'pasajes', 'viaticos', 'rendicion', 'combustible', 'alquiler'],
        'Oficina General de Asesoría Jurídica' => ['informe legal', 'demanda', 'queja', 'contraloria', 'juridico', 'proceso judicial', 'convenio', 'licitacion', 'apelacion', 'denuncia', 'arbitraje', 'amparo', 'contencioso', 'conciliacion', 'coactiva', 'nulidad'],
        'Oficina General de Planeamiento y Presupuesto' => ['plan operativo', 'presupuesto', 'programacion', 'ejecucion presupuestal', 'inversiones', 'certificacion', 'credito presupuestario', 'preinversion', 'perfil de proyecto', 'viabilidad', 'desarrollo concertado', 'monitoreo'],
        'Gerencia de Desarrollo Económico y Administración Tributaria' => ['impuesto predial', 'arbitrios', 'deuda tributaria', 'fraccionamiento', 'fiscalizacion', 'padron', 'contribuyente', 'condonacion', 'recibo', 'revaluacion', 'amnistia', 'comercio informal', 'formalizacion', 'emprendimiento', 'mype'],
        'Gerencia de Desarrollo Territorial e Infraestructura' => ['construccion', 'edificacion', 'inspeccion tecnica', 'demolicion', 'habilitacion urbana', 'agua', 'desague', 'pistas', 'veredas', 'losa', 'parque', 'alumbrado', 'factibilidad', 'parametros urbanisticos', 'planos', 'expediente tecnico', 'supervision', 'asfaltado'],
        'Gerencia de Servicios Municipales y Gestión Ambiental' => ['residuos', 'areas verdes', 'barrido', 'basura', 'reciclaje', 'fumigacion', 'limpieza', 'cementerio', 'quema', 'parques', 'jardines', 'serenazgo', 'contenedores', 'calidad del aire', 'educacion ambiental', 'reforestacion', 'poda', 'rio', 'contaminacion sonora'],
        'Gerencia de Desarrollo Social' => ['programa social', 'vaso de leche', 'comedor', 'cuna mas', 'bono', 'pension', 'discapacidad', 'adulto mayor', 'campaña de salud', 'apoyo psicologico', 'ayuda humanitaria', 'capacitacion juvenil', 'defensoria', 'refugio', 'vacunacion', 'prevencion de violencia', 'alimentacion escolar', 'club de madres', 'silla de ruedas'],
        'Gerencia de Desarrollo del Pueblo Asháninka' => ['traduccion', 'intercultural', 'consulta previa', 'comunidad nativa', 'interprete', 'titulacion', 'artesania', 'productos nativos', 'saneamiento', 'becas interculturales', 'comunidad campesina', 'medico intercultural', 'lengua materna', 'saberes ancestrales', 'patrimonio cultural'],
    ];

    $puntajes = [];
    foreach ($areas_keywords as $area => $keywords) {
        $score = 0;
        foreach ($keywords as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $texto_lower)) {
                $score++;
            }
        }
        if ($score > 0) $puntajes[$area] = $score;
    }

    if (empty($puntajes)) {
        return ['area' => 'Oficina General de Atención al Ciudadano y Gestión Documental', 'confianza' => 30.0];
    }

    $total = array_sum($puntajes);
    $max_score = max($puntajes);
    $confianza = round($max_score / $total * 100, 1);
    $max_area = array_search($max_score, $puntajes);

    return ['area' => $max_area, 'confianza' => min($confianza, 95.0)];
}
