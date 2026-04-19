<?php

namespace App\Service\formation;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;

final class LocalQuizGeneratorService
{
    private const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL = 'llama-3.3-70b-versatile';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * Méthode rétro-compatible utilisée par le contrôleur.
     *
     * @return array<int, array{enonce: string, reponse: string}>
     */
    public function generateTrueFalseQuestions(string $text, int $count = 5): array
    {
        $result = $this->generate($text, $count);
        return ($result['ok'] ?? false) ? ($result['questions'] ?? []) : [];
    }

    /**
     * @return array{ok: bool, questions?: list<array{enonce: string, reponse: string}>, error?: string}
     */
    public function generate(string $text, int $count = 5): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'Le texte source est vide.'];
        }

        $apiKey = trim((string) ($_ENV['GROQ_API_KEY'] ?? ''));
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'Clé GROQ_API_KEY manquante dans le .env.'];
        }

        $count = max(1, min(50, $count));
        $text  = $this->prepareSourceText($text, 3000);

        // Étape 1 : traduire en français si le texte est en anglais
        if ($this->isEnglish($text)) {
            $translateResult = $this->callGroq(
                "Traduis le texte suivant en français. "
                . "Si le texte est déjà en français, retourne-le tel quel. "
                . "Retourne UNIQUEMENT la traduction, sans commentaire ni explication.\n\nTexte :\n" . $text,
                $apiKey,
                2048
            );
            if (!($translateResult['ok'] ?? false)) {
                return $translateResult;
            }
            $text = trim($translateResult['content']);
        }

        // Étape 2 : générer les questions
        $prompt = <<<PROMPT
Tu es un expert en création de quiz pédagogiques en français.
À partir du texte suivant, génère exactement {$count} questions de type Vrai/Faux.

Règles STRICTES :
- Les questions portent UNIQUEMENT sur les concepts techniques, définitions et faits du contenu.
- INTERDIT : questions sur la vidéo, la série, le formateur, le cours lui-même ou la plateforme.
- INTERDIT : "Ce cours parle de...", "Cette vidéo explique...", "Le formateur montre..."
- Les énoncés sont des affirmations directes sans aucun préfixe.
- Exemples CORRECTS : "Python est un langage interprété.", "Une liste en Python est mutable."
- Exemples INTERDITS : "Ce cours enseigne Python.", "La vidéo parle de Django."
- Les affirmations fausses contiennent une vraie erreur technique (ex: confondre deux concepts).
- Toutes les questions sont en français.
- Varie les réponses (pas tout vrai ou tout faux).

Texte source :
{$text}

Réponds UNIQUEMENT avec un JSON valide, sans texte avant ni après :
{"questions":[{"enonce":"...","reponse":"vrai"},{"enonce":"...","reponse":"faux"}]}
PROMPT;

        $result = $this->callGroq($prompt, $apiKey, 4096);
        if (!($result['ok'] ?? false)) {
            return $result;
        }

        return $this->parseQuestions($result['content'], $count);
    }

    // -------------------------------------------------------------------------
    // Appel HTTP Groq
    // -------------------------------------------------------------------------

    /**
     * @return array{ok: bool, content?: string, error?: string}
     */
    private function callGroq(string $prompt, string $apiKey, int $maxTokens = 2048): array
    {
        try {
            $response = $this->httpClient->request('POST', self::GROQ_URL, [
                'timeout' => 30,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
                'json' => [
                    'model'       => self::GROQ_MODEL,
                    'temperature' => 0.3,
                    'max_tokens'  => min($maxTokens, 4096),
                    'messages'    => [
                        ['role' => 'system', 'content' => 'Tu reponds uniquement en JSON valide, sans texte autour.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                ],
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Groq inaccessible. Vérifiez votre connexion internet.'];
        }

        try {
            $status = $response->getStatusCode();
        } catch (TimeoutExceptionInterface) {
            return ['ok' => false, 'error' => 'Groq trop lent (timeout).'];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Erreur réseau lors de l\'appel Groq.'];
        }

        if ($status === 401) {
            return ['ok' => false, 'error' => 'Clé GROQ_API_KEY invalide ou expirée.'];
        }
        if ($status === 429) {
            return ['ok' => false, 'error' => 'Limite Groq atteinte. Réessayez dans quelques secondes.'];
        }
        if ($status < 200 || $status >= 300) {
            try {
                $errorBody = $response->toArray(false);
                $errorMsg  = (string) ($errorBody['error']['message'] ?? json_encode($errorBody));
            } catch (\Throwable) {
                $errorMsg = $response->getContent(false);
            }
            return ['ok' => false, 'error' => 'Groq erreur ' . $status . ' : ' . $errorMsg];
        }

        try {
            $payload = $response->toArray(false);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Réponse Groq non JSON.'];
        }

        $content = (string) ($payload['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            return ['ok' => false, 'error' => 'Réponse Groq vide.'];
        }

        return ['ok' => true, 'content' => $content];
    }

    // -------------------------------------------------------------------------
    // Parsing des questions
    // -------------------------------------------------------------------------

    /**
     * @return array{ok: bool, questions?: list<array{enonce: string, reponse: string}>, error?: string}
     */
    private function parseQuestions(string $raw, int $count): array
    {
        $data = json_decode($raw, true);
        if (!is_array($data) && preg_match('/\{.*\}/s', $raw, $m)) {
            $data = json_decode($m[0], true);
        }

        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Groq n\'a pas retourné un JSON valide.'];
        }

        $questions = $data['questions'] ?? [];
        if (!is_array($questions) || $questions === []) {
            return ['ok' => false, 'error' => 'Aucune question retournée par Groq.'];
        }

        $cleaned = [];
        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }

            $enonce  = trim((string) ($q['enonce']  ?? ''));
            $reponse = strtolower(trim((string) ($q['reponse'] ?? '')));

            if ($enonce === '') {
                continue;
            }

            // Supprimer "Vrai ou Faux : " si le modèle l'ajoute quand même
            $enonce = preg_replace('/^vrai\s+ou\s+faux\s*:\s*/iu', '', $enonce);
            $enonce = trim($enonce);

            if ($enonce === '') {
                continue;
            }

            // S'assurer que la phrase se termine par un point
            if (!in_array(mb_substr($enonce, -1), ['.', '?', '!'], true)) {
                $enonce .= '.';
            }

            if (!in_array($reponse, ['vrai', 'faux'], true)) {
                continue;
            }

            $cleaned[] = ['enonce' => $enonce, 'reponse' => $reponse];

            if (count($cleaned) >= $count) {
                break;
            }
        }

        if ($cleaned === []) {
            return ['ok' => false, 'error' => 'Questions invalides générées par Groq.'];
        }

        return ['ok' => true, 'questions' => $cleaned];
    }

    // -------------------------------------------------------------------------
    // Utilitaires
    // -------------------------------------------------------------------------

    private function isEnglish(string $text): bool
    {
        $frMarkers = ['le','la','les','de','des','du','est','sont','avec','pour','dans','sur','peut','doit','une','un'];
        $enMarkers = ['the','and','or','to','of','in','on','for','with','is','are','this','that','can','must'];

        preg_match_all('/[a-zA-Z]{2,}/i', mb_strtolower($text), $m);
        $tokens = array_unique($m[0] ?? []);

        $fr = count(array_intersect($tokens, $frMarkers));
        $en = count(array_intersect($tokens, $enMarkers));

        return $en > $fr + 2;
    }

    private function prepareSourceText(string $text, int $maxChars): string
    {
        $clean = preg_replace('/https?:\/\/\S+/iu', ' ', $text) ?? $text;
        $clean = preg_replace('/\b(subscribe|like|follow|website|join our|table of contents)\b.*/iu', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        if (mb_strlen($clean) > $maxChars) {
            $clean   = mb_substr($clean, 0, $maxChars);
            $lastDot = mb_strrpos($clean, '.');
            if ($lastDot !== false && $lastDot > (int) ($maxChars * 0.5)) {
                $clean = mb_substr($clean, 0, $lastDot + 1);
            }
        }

        return $clean;
    }
}