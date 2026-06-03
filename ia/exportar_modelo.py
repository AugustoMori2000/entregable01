import json
import pickle
import os

ruta_actual = os.path.dirname(__file__)
ruta_pickle = os.path.join(ruta_actual, "modelo_tramites.pkl")
ruta_json = os.path.join(ruta_actual, "modelo_export.json")

if not os.path.exists(ruta_pickle):
    print("No hay modelo pickle para exportar")
    exit(1)

with open(ruta_pickle, 'rb') as f:
    model = pickle.load(f)

vec = model.named_steps['vec']
clf = model.named_steps['clf']

vocab_sorted = {k: v for k, v in sorted(vec.vocabulary_.items(), key=lambda item: item[1])}
features = list(vocab_sorted.keys())
classes = clf.classes_.tolist()
coef = clf.coef_.tolist()
intercept = clf.intercept_.tolist()

data = {
    'classes': classes,
    'features': features,
    'coef': coef,
    'intercept': intercept,
}

with open(ruta_json, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False)

print(f"Exportado: {len(features)} features, {len(classes)} clases a {ruta_json}")
