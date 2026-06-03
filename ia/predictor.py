import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

import base64
import json
import pickle
import os
from preprocesar import limpiar

ruta_actual = os.path.dirname(__file__)
ruta_modelo = os.path.join(ruta_actual, "modelo_tramites.pkl")

with open(ruta_modelo, "rb") as archivo:
    modelo = pickle.load(archivo)

texto = sys.argv[1]
texto_limpio = limpiar(texto)

pred_class = modelo.predict([texto_limpio])[0]

probas = modelo.predict_proba([texto_limpio])[0]
idx = list(modelo.classes_).index(pred_class)
confianza = round(float(probas[idx]) * 100, 1)

resultado = json.dumps({
    "area": pred_class,
    "confianza": confianza
}, ensure_ascii=False)

sys.stdout.buffer.write(base64.b64encode(resultado.encode('utf-8')))
