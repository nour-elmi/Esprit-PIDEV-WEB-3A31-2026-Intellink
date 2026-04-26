import sys
import json
import io
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.neighbors import NearestNeighbors

def get_level(score):
    if score >= 75: return "Excellente"
    if score >= 50: return "Très bonne"
    if score >= 25: return "Moyenne"
    return "Faible"

def recommend(offer_text, candidates_data):
    if sys.platform == "win32":
        sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

    texts = [c['skills'] for c in candidates_data]
    candidate_ids = [c['id'] for c in candidates_data]

    vectorizer = TfidfVectorizer(stop_words=None) # On enlève stop_words pour mieux capter les mots courts
    X = vectorizer.fit_transform(texts)
    offer_vector = vectorizer.transform([offer_text])

    n_neighbors = len(texts) # On calcule le score pour TOUS les participants
    model = NearestNeighbors(n_neighbors=n_neighbors, metric='cosine')
    model.fit(X)

    distances, indices = model.kneighbors(offer_vector)

    results = []
    for i in range(len(indices[0])):
        idx = indices[0][i]
        score = round((1 - distances[0][i]) * 100, 2)
        results.append({
            'id': candidate_ids[idx],
            'score': score,
            'level': get_level(score) # On ajoute le niveau ici
        })
    
    return results

if __name__ == "__main__":
    try:
        candidates = json.loads(sys.argv[1])
        offer = sys.argv[2]
        output = recommend(offer, candidates)
        print(json.dumps(output, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
