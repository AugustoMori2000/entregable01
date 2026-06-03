import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

import base64
import json
import warnings
warnings.filterwarnings('ignore')

import pandas as pd
import pickle
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.naive_bayes import MultinomialNB
from sklearn.linear_model import LogisticRegression
from sklearn.pipeline import Pipeline
from sklearn.metrics import accuracy_score, classification_report
from preprocesar import limpiar

ruta_csv = sys.argv[1] if len(sys.argv) > 1 else "tramites.csv"

df = pd.read_csv(ruta_csv)
X = df["asunto"].apply(limpiar)
y = df["area_destino"]

from collections import Counter
min_class = min(Counter(y).values())
stratify = y if min_class >= 2 else None
if stratify is None:
    print(f"[AVISO] Clases muy pequeñas (min={min_class}), entrenando sin stratify")

X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42, stratify=stratify
)

vectorizer = CountVectorizer(ngram_range=(1, 2), max_features=3000)

modelos = {
    'Naive Bayes': Pipeline([('vec', vectorizer), ('clf', MultinomialNB())]),
    'Regresión Logística': Pipeline([('vec', vectorizer), ('clf', LogisticRegression(max_iter=2000))]),
}

best_score = 0
best_name = ''
best_model = None

for nombre, modelo in modelos.items():
    scores = cross_val_score(modelo, X_train, y_train, cv=3, scoring='accuracy')
    media = scores.mean()
    print(f"  {nombre}: {media*100:.2f}%")

    if media > best_score:
        best_score = media
        best_name = nombre
        best_model = modelo

best_model.fit(X_train, y_train)
predicciones = best_model.predict(X_test)
precision = accuracy_score(y_test, predicciones)

print(f"\nMejor modelo: {best_name}")
print(f"Precisión en prueba: {precision * 100:.2f}%")
print(f"\nReporte por área:")
print(classification_report(y_test, predicciones, zero_division=0))

with open("modelo_tramites.pkl", "wb") as archivo:
    pickle.dump(best_model, archivo)

info = {
    "modelo": best_name,
    "precision": round(precision * 100, 2),
    "registros": len(df),
    "clases": len(set(y))
}

print(f"\nModelo guardado en modelo_tramites.pkl")
print(f"INFO:{base64.b64encode(json.dumps(info, ensure_ascii=False).encode('utf-8')).decode('ascii')}")
