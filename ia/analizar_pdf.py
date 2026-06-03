import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

import base64
import json
import pickle
import os
import re
import tempfile
import logging
logging.getLogger().setLevel(logging.ERROR)

from preprocesar import limpiar
import pdfplumber

PALABRAS_OFENSIVAS = {
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
    'mierda', 'mierdas',
    'cochino', 'cochina',
    'tarado', 'tarada', 'tarados',
    'retrasado', 'retrasada',
    'maldito', 'maldita', 'malditos', 'malditas',
    'hdp', 'mrd', 'ptm',
    'fuck', 'fucking', 'shit', 'asshole',
    'puto el que lee', 'puta el que lee',
}

def detectar_ofensivo(texto):
    texto_lower = texto.lower()
    for palabra in PALABRAS_OFENSIVAS:
        if ' ' in palabra:
            if palabra in texto_lower:
                return True, palabra
        else:
            if re.search(r'\b' + re.escape(palabra) + r'\b', texto_lower):
                return True, palabra
    return False, None

def extraer_texto_pdf(ruta_pdf, usar_ocr=True):
    texto = ""
    with pdfplumber.open(ruta_pdf) as pdf:
        for pagina in pdf.pages:
            contenido = pagina.extract_text()
            if contenido:
                texto += contenido + "\n"
    texto = texto.strip()
    if usar_ocr and len(texto) < 50:
        texto_ocr = _ocr_pdf(ruta_pdf)
        if texto_ocr and len(texto_ocr) > len(texto):
            texto = texto_ocr
    return texto

_ocr_reader = None
def _ocr_pdf(ruta_pdf):
    global _ocr_reader
    try:
        import easyocr
        global _ocr_reader
        if _ocr_reader is None:
            _ocr_reader = easyocr.Reader(['es'], gpu=False, verbose=False)
        img_path = os.path.join(tempfile.gettempdir(), "ocr_" + os.path.basename(ruta_pdf) + ".png")
        with pdfplumber.open(ruta_pdf) as pdf:
            page = pdf.pages[0]
            page.to_image(resolution=200).save(img_path)
        result = _ocr_reader.readtext(img_path, paragraph=True, detail=1)
        lineas = []
        for item in result:
            if isinstance(item, (list, tuple)) and len(item) >= 2:
                texto = item[1] if len(item) > 1 else str(item[0])
                if isinstance(texto, str) and len(texto) > 1:
                    lineas.append(texto)
        return "\n".join(lineas)
    except Exception:
        return None

def similitud_textos(texto1, texto2):
    from preprocesar import limpiar
    limpio1 = limpiar(texto1)
    limpio2 = limpiar(texto2)
    palabras1 = set(limpio1.split())
    palabras2 = set(limpio2.split())
    if not palabras1:
        return 0.0
    comunes = palabras1 & palabras2
    return len(comunes) / len(palabras1)

def detectar_formato(texto):
    texto_lower = texto.lower()
    texto_sinespacios = texto_lower.replace(' ', '')
    primeras_300 = texto_lower[:300]
    formatos = {
        'Constancia': ['constancia', 'hace constar', 'el que suscribe', 'certifica', 'certifico'],
        'Orden de Compra': ['orden de compra', 'oc nro', 'oc n'],
        'Declaracion Jurada': ['declaracion jurada', 'declaro bajo juramento', 'declaro bajo protesta'],
        'Resolucion': ['resolucion', 'vistos', 'considerando', 'se resuelve'],
        'Carta': ['carta', 'remito', 'dirigido a'],
        'Memorandum': ['memorandum', 'memorando'],
        'Informe': ['informe'],
        'Solicitud': ['solicitud', 'solicito', 'solicita', 'a quien corresponda'],
        'Oficio': ['oficio'],
        'Proveido': ['proveido', 'provease', 'cursese', 'agreguese'],
    }
    puntajes = {}
    for fmt, palabras in formatos.items():
        score = 0
        for p in palabras:
            if ' ' in p:
                if p in texto_lower:
                    score += 1
            else:
                if re.search(r'\b' + re.escape(p) + r'\b', texto_lower):
                    score += 1
                elif p in texto_sinespacios:
                    score += 1
        if score > 0:
            puntajes[fmt] = score
    # Structural patterns (higher weight, word-boundaried)
    estructuras = {
        'Informe': [r'\ba\s*:', r'\bde\s*:', r'\basunto\s*:'],
        'Oficio': [r'\boficio\s+n[°º]?', r'\bes grato dirigirme\b'],
        'Resolucion': [r'\bse resuelve\b'],
    }
    for fmt, patrones in estructuras.items():
        for pat in patrones:
            if re.search(pat, texto_lower):
                puntajes[fmt] = puntajes.get(fmt, 0) + 2
    # Bonus: format name in first 300 chars (strong signal)
    nombres = {
        'Constancia': 'constancia', 'Orden de Compra': 'orden de compra',
        'Declaracion Jurada': 'declaracion jurada', 'Resolucion': 'resolucion',
        'Carta': 'carta', 'Memorandum': 'memorandum',
        'Informe': 'informe', 'Solicitud': 'solicitud',
        'Oficio': 'oficio', 'Proveido': 'proveido',
    }
    for fmt, name in nombres.items():
        if fmt in puntajes and name in primeras_300:
            puntajes[fmt] += 3
    if not puntajes:
        return None
    max_score = max(puntajes.values())
    empatados = [f for f, s in puntajes.items() if s == max_score]
    return empatados[0]

def formatos_en_texto(texto):
    texto_lower = texto.lower()
    formatos = {
        'Constancia': ['constancia', 'hace constar', 'certifica', 'certifico'],
        'Orden de Compra': ['orden de compra', 'oc nro', 'oc n'],
        'Declaracion Jurada': ['declaracion jurada', 'declaro bajo juramento', 'declaro bajo protesta'],
        'Resolucion': ['resolucion'],
        'Carta': ['carta'],
        'Memorandum': ['memorandum', 'memorando'],
        'Informe': ['informe'],
        'Solicitud': ['solicitud', 'solicito', 'solicita'],
        'Oficio': ['oficio'],
        'Proveido': ['proveido'],
    }
    encontrados = set()
    for fmt, palabras in formatos.items():
        for p in palabras:
            if p in texto_lower:
                encontrados.add(fmt)
                break
    return encontrados

if __name__ == "__main__":
    ruta_actual = os.path.dirname(__file__)
    ruta_modelo = os.path.join(ruta_actual, "modelo_tramites.pkl")

    ruta_pdf = sys.argv[1]
    asunto_ingresado = sys.argv[2] if len(sys.argv) > 2 else ""

    try:
        texto_pdf = extraer_texto_pdf(ruta_pdf)
    except Exception as e:
        resultado = json.dumps({"error": "No se pudo leer el PDF: " + str(e)}, ensure_ascii=False)
        sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
        sys.exit(0)

    if not texto_pdf or len(texto_pdf.strip()) < 10:
        resultado = json.dumps({"error": "El PDF está vacío o no contiene texto legible"}, ensure_ascii=False)
        sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
        sys.exit(0)

    ofensivo, palabra = detectar_ofensivo(texto_pdf)
    if ofensivo:
        resultado = json.dumps({
            "error": "El documento contiene lenguaje inapropiado y no puede ser procesado."
        }, ensure_ascii=False)
        sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
        sys.exit(0)

    # Verificar coincidencia con el asunto ingresado
    if asunto_ingresado:
        score = similitud_textos(asunto_ingresado, texto_pdf)
        if score < 0.3:
            resultado = json.dumps({
                "error": "El contenido del PDF no coincide con el asunto ingresado. Por favor verifique el documento."
            }, ensure_ascii=False)
            sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
            sys.exit(0)

    # Detectar formato del documento
    formato = detectar_formato(texto_pdf)
    if not formato:
        resultado = json.dumps({
            "error": "El documento no tiene un formato válido. Solo se aceptan: Constancia, Orden de Compra, Declaración Jurada, Resolución, Carta, Memorándum, Informe, Solicitud, Oficio, Proveído."
        }, ensure_ascii=False)
        sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
        sys.exit(0)

    # Verificar que el formato del PDF coincida con lo que pide el asunto
    if asunto_ingresado:
        formatos_asunto = formatos_en_texto(asunto_ingresado)
        if formatos_asunto and formato not in formatos_asunto:
            lista = ', '.join(sorted(formatos_asunto))
            resultado = json.dumps({
                "error": f"El asunto indica que el documento debería ser: {lista}. Pero el PDF adjunto tiene formato {formato}. Verifique el documento."
            }, ensure_ascii=False)
            sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
            sys.exit(0)

    with open(ruta_modelo, "rb") as archivo:
        modelo = pickle.load(archivo)

    texto_limpio = limpiar(texto_pdf)
    pred_class = modelo.predict([texto_limpio])[0]
    probas = modelo.predict_proba([texto_limpio])[0]
    idx = list(modelo.classes_).index(pred_class)
    confianza = round(float(probas[idx]) * 100, 1)

    resultado = json.dumps({
        "area": pred_class,
        "confianza": confianza,
        "texto_extraido": texto_pdf[:500],
        "formato": formato
    }, ensure_ascii=False)

    sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
