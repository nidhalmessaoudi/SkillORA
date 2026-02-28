<?php

namespace App\Tests\Service;

use App\Entity\Evaluation;
use App\Entity\Question;
use App\Service\EvaluationManager;
use PHPUnit\Framework\TestCase;

class EvaluationManagerTest extends TestCase
{
    public function testValidTypeQuiz()
    {
        $evaluation = new Evaluation();
        $evaluation->setType('QUIZ');

        $manager = new EvaluationManager();
        $this->assertTrue($manager->validateType($evaluation));
    }

    public function testInvalidTypeThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $evaluation = new Evaluation();
        $evaluation->setType('BAD_TYPE');

        $manager = new EvaluationManager();
        $manager->validateType($evaluation);
    }

    public function testTotalScoreIsSumOfQuestions()
    {
        $evaluation = new Evaluation();

        // On crée de vraies questions (pas besoin de DB)
        $q1 = new Question();
        $q1->setScore(5);

        $q2 = new Question();
        $q2->setScore(10);

        // IMPORTANT : ta relation ManyToOne Question->Evaluation est nullable=false
        // donc on associe l'évaluation
        $q1->setEvaluation($evaluation);
        $q2->setEvaluation($evaluation);

        // On ajoute les questions dans Evaluation
        // ⚠️ Ton entity Evaluation n'a pas addQuestion() dans le code envoyé
        // donc on ajoute via la collection directement :
        $evaluation->getQuestions()->add($q1);
        $evaluation->getQuestions()->add($q2);

        $manager = new EvaluationManager();
        $this->assertTrue($manager->validateTotalScore($evaluation));

        $this->assertEquals(15, $evaluation->getTotalScore());
    }
}