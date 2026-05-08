<?php

namespace App\Tests\Service\formation;

use App\Entity\formation\Question;
use App\Service\formation\QuizScoreService;
use PHPUnit\Framework\TestCase;

final class QuizScoreServiceTest extends TestCase
{
    public function testCalculateReturnsExpectedScoreAndValidation(): void
    {
        $q1 = $this->makeQuestion(1, 'vrai', 2.0);
        $q2 = $this->makeQuestion(2, 'faux', 1.0);
        $q3 = $this->makeQuestion(3, 'vrai', 2.0);

        $answers = [
            '1' => 'vrai',
            '2' => 'vrai',
            '3' => 'faux',
        ];

        $service = new QuizScoreService();
        $result = $service->calculate([$q1, $q2, $q3], $answers);

        $this->assertSame(3.0, $result['score']);
        $this->assertSame(5.0, $result['scoreMax']);
        $this->assertTrue($result['isQuizValide']);
    }

    public function testCalculateFailsWhenBelowSixtyPercent(): void
    {
        $q1 = $this->makeQuestion(1, 'vrai', 1.0);
        $q2 = $this->makeQuestion(2, 'faux', 1.0);
        $q3 = $this->makeQuestion(3, 'vrai', 1.0);
        $q4 = $this->makeQuestion(4, 'faux', 1.0);
        $q5 = $this->makeQuestion(5, 'vrai', 1.0);

        $answers = [
            '1' => 'vrai',
            '2' => 'faux',
            '3' => 'faux',
            '4' => 'vrai',
            '5' => 'faux',
        ];

        $service = new QuizScoreService();
        $result = $service->calculate([$q1, $q2, $q3, $q4, $q5], $answers);

        $this->assertSame(2.0, $result['score']);
        $this->assertSame(5.0, $result['scoreMax']);
        $this->assertFalse($result['isQuizValide']);
    }

    public function testCalculatePassesAtExactSixtyPercent(): void
    {
        $q1 = $this->makeQuestion(1, 'vrai', 1.0);
        $q2 = $this->makeQuestion(2, 'faux', 1.0);
        $q3 = $this->makeQuestion(3, 'vrai', 1.0);
        $q4 = $this->makeQuestion(4, 'faux', 1.0);
        $q5 = $this->makeQuestion(5, 'vrai', 1.0);

        $answers = [
            '1' => 'vrai',
            '2' => 'faux',
            '3' => 'vrai',
        ];

        $service = new QuizScoreService();
        $result = $service->calculate([$q1, $q2, $q3, $q4, $q5], $answers);

        $this->assertSame(3.0, $result['score']);
        $this->assertSame(5.0, $result['scoreMax']);
        $this->assertTrue($result['isQuizValide']);
    }

    private function makeQuestion(int $id, string $correct, float $points): Question
    {
        $question = new Question();
        $question->setReponseCorrecte($correct);
        $question->setPoints($points);

        $ref = new \ReflectionProperty($question, 'idQuestion');
        $ref->setValue($question, $id);

        return $question;
    }
}
