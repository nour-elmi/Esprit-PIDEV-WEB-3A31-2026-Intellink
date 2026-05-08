<?php

namespace App\Service\formation;

use App\Entity\formation\Question;

final class QuizScoreService
{
    /**
     * @param array<int, Question> $questions
     * CHANGEMENT: accepter des cles int|string (les cles numeriques de PHP peuvent etre cast en int).
     * Ancien code (garde): @param array<string, string> $answers
     * @param array<int|string, string> $answers
     * @return array{score: float, scoreMax: float, isQuizValide: bool}
     */
    public function calculate(array $questions, array $answers): array
    {
        $score = 0.0;
        foreach ($questions as $question) {
            $questionId = (string) $question->getIdQuestion();
            $selected = strtolower(trim((string) ($answers[$questionId] ?? '')));
            $correct = strtolower(trim((string) ($question->getReponseCorrecte() ?? '')));
            if ($selected !== '' && $selected === $correct) {
                $score += (float) ($question->getPoints() ?? 1.0);
            }
        }

        $scoreMax = 0.0;
        foreach ($questions as $question) {
            $scoreMax += (float) ($question->getPoints() ?? 1.0);
        }

        $isQuizValide = $scoreMax > 0 ? $score >= ($scoreMax * 0.6) : $score > 0;

        return [
            'score' => $score,
            'scoreMax' => $scoreMax,
            'isQuizValide' => $isQuizValide,
        ];
    }
}
