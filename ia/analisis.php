<?php
require_once __DIR__ . '/preprocesar.php';

$PALABRAS_OFENSIVAS = [
    'hijo de puta', 'hija de puta', 'hijos de puta', 'hijas de puta',
    'concha de tu madre', 'conchetumare', 'ctm',
    'mierda', 'puta', 'puto', 'putas', 'putos',
    'carajo', 'cojudo', 'cojuda', 'cojones',
    'pendejo', 'pendeja', 'pendejos', 'pendejas',
    'huevon', 'huevona', 'huevones', 'huevonas',
    'webon', 'webona', 'webones',
    'cabron', 'cabrona', 'cabrones', 'cabronas',
    'marica', 'maricon', 'maricones',
    'estupido', 'estupida', 'estupidos', 'stupid',
    'idiota', 'idiotas',
    'imbecil', 'imbeciles',
    'tonto', 'tonta', 'tontos', 'tontas',
    'basura', 'desgraciado', 'desgraciada',
    'malparido', 'malparida',
    'cerdo', 'cerda',
    'asqueroso', 'asquerosa',
    'verga', 'vergas',
    'pija', 'pijas',
    'culo', 'culos',
    'chucha', 'chuchas',
    'soplaputas', 'soplapollas',
    'mierdas',
    'cochino', 'cochina',
    'tarado', 'tarada', 'tarados',
    'retrasado', 'retrasada',
    'maldito', 'maldita', 'malditos', 'malditas',
    'hdp', 'mrd', 'ptm',
    'fuck', 'fucking', 'shit', 'asshole',
    'puto el que lee', 'puta el que lee',
];

function detectar_ofensivo($texto) {
    global $PALABRAS_OFENSIVAS;
    $texto_lower = mb_strtolower($texto, 'UTF-8');
    foreach ($PALABRAS_OFENSIVAS as $palabra) {
        if (strpos($palabra, ' ') !== false) {
            if (strpos($texto_lower, $palabra) !== false) {
                return $palabra;
            }
        } else {
            if (preg_match('/\b' . preg_quote($palabra, '/') . '\b/u', $texto_lower)) {
                return $palabra;
            }
        }
    }
    return null;
}

function detectar_formato($texto) {
    $texto_lower = mb_strtolower($texto, 'UTF-8');
    $texto_sinespacios = str_replace(' ', '', $texto_lower);
    $primeras_300 = mb_substr($texto_lower, 0, 300, 'UTF-8');

    $formatos = [
        'Constancia' => ['constancia', 'hace constar', 'el que suscribe', 'certifica', 'certifico'],
        'Orden de Compra' => ['orden de compra', 'oc nro', 'oc n°'],
        'Declaracion Jurada' => ['declaracion jurada', 'declaro bajo juramento', 'declaro bajo protesta'],
        'Resolucion' => ['resolucion', 'vistos', 'considerando', 'se resuelve'],
        'Carta' => ['carta', 'remito', 'dirigido a'],
        'Memorandum' => ['memorandum', 'memorando'],
        'Informe' => ['informe'],
        'Solicitud' => ['solicitud', 'solicito', 'solicita', 'a quien corresponda'],
        'Oficio' => ['oficio'],
        'Proveido' => ['proveido', 'provease', 'cursese', 'agreguese'],
    ];

    $puntajes = [];
    foreach ($formatos as $fmt => $palabras) {
        $score = 0;
        foreach ($palabras as $p) {
            if (strpos($p, ' ') !== false) {
                if (strpos($texto_lower, $p) !== false) $score++;
            } else {
                if (preg_match('/\b' . preg_quote($p, '/') . '\b/u', $texto_lower)) $score++;
                elseif (strpos($texto_sinespacios, $p) !== false) $score++;
            }
        }
        if ($score > 0) $puntajes[$fmt] = $score;
    }

    // Structural patterns
    $estructuras = [
        'Informe' => ['/\ba\s*:/u', '/\bde\s*:/u', '/\basunto\s*:/u'],
        'Oficio' => ['/\boficio\s+n[°º]?/u', '/\bes grato dirigirme\b/u'],
        'Resolucion' => ['/\bse resuelve\b/u'],
    ];
    foreach ($estructuras as $fmt => $patrones) {
        foreach ($patrones as $pat) {
            if (preg_match($pat, $texto_lower)) {
                $puntajes[$fmt] = ($puntajes[$fmt] ?? 0) + 2;
            }
        }
    }

    // Bonus for format name in first 300 chars
    $nombres = [
        'Constancia' => 'constancia', 'Orden de Compra' => 'orden de compra',
        'Declaracion Jurada' => 'declaracion jurada', 'Resolucion' => 'resolucion',
        'Carta' => 'carta', 'Memorandum' => 'memorandum',
        'Informe' => 'informe', 'Solicitud' => 'solicitud',
        'Oficio' => 'oficio', 'Proveido' => 'proveido',
    ];
    foreach ($nombres as $fmt => $name) {
        if (isset($puntajes[$fmt]) && strpos($primeras_300, $name) !== false) {
            $puntajes[$fmt] += 3;
        }
    }

    if (empty($puntajes)) return null;
    $max_score = max($puntajes);
    $empatados = array_keys(array_filter($puntajes, function($s) use ($max_score) { return $s === $max_score; }));
    return $empatados[0];
}

function formatos_en_texto($texto) {
    $texto_lower = mb_strtolower($texto, 'UTF-8');
    $formatos = [
        'Constancia' => ['constancia', 'hace constar', 'certifica', 'certifico'],
        'Orden de Compra' => ['orden de compra', 'oc nro', 'oc n°'],
        'Declaracion Jurada' => ['declaracion jurada', 'declaro bajo juramento', 'declaro bajo protesta'],
        'Resolucion' => ['resolucion'],
        'Carta' => ['carta'],
        'Memorandum' => ['memorandum', 'memorando'],
        'Informe' => ['informe'],
        'Solicitud' => ['solicitud', 'solicito', 'solicita'],
        'Oficio' => ['oficio'],
        'Proveido' => ['proveido'],
    ];
    $encontrados = [];
    foreach ($formatos as $fmt => $palabras) {
        foreach ($palabras as $p) {
            if (strpos($texto_lower, $p) !== false) {
                $encontrados[] = $fmt;
                break;
            }
        }
    }
    return $encontrados;
}

function similitud_textos($texto1, $texto2) {
    $limpio1 = limpiar_texto($texto1);
    $limpio2 = limpiar_texto($texto2);
    $palabras1 = array_unique(preg_split('/\s+/', trim($limpio1)));
    $palabras2 = array_unique(preg_split('/\s+/', trim($limpio2)));
    if (empty($palabras1)) return 0.0;
    $comunes = array_intersect($palabras1, $palabras2);
    return count($comunes) / count($palabras1);
}
