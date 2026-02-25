<?php

namespace App\Controller;

use App\Entity\Evaluation;
use App\Entity\Answer;
use App\Entity\UserEvaluation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user/evaluation')]
class UserEvaluationController extends AbstractController
{
    #[Route('/', name: 'user_evaluation_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $evaluations = $em->getRepository(Evaluation::class)->findAll();

        return $this->render('evaluation/user_index.html.twig', [
            'evaluations' => $evaluations,
        ]);
    }

    #[Route('/{id}', name: 'user_evaluation_show', methods: ['GET'])]
    public function show(Evaluation $evaluation, EntityManagerInterface $em): Response
    {
        // ✅ Si c'est un QUIZ, on force l’accès au quiz directement
        if ($evaluation->getType() === 'QUIZ') {
            return $this->redirectToRoute('user_evaluation_take', [
                'id' => $evaluation->getId()
            ]);
        }

        $user = $this->getUser();

        $userEvaluation = $em->getRepository(UserEvaluation::class)->findOneBy([
            'user' => $user,
            'evaluation' => $evaluation,
        ]);

        return $this->render('evaluation/user_show.html.twig', [
            'evaluation' => $evaluation,
            'userEvaluation' => $userEvaluation,
        ]);
    }

    #[Route('/{id}/take', name: 'user_evaluation_take', methods: ['GET', 'POST'])]
    public function take(Evaluation $evaluation, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // 1) récupérer / créer UserEvaluation
        $userEvaluation = $em->getRepository(UserEvaluation::class)->findOneBy([
            'user' => $user,
            'evaluation' => $evaluation,
        ]);

        if ($userEvaluation && $userEvaluation->getSubmittedAt() !== null) {
            $this->addFlash('warning', 'Vous avez déjà passé cette évaluation.');
            return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
        }

        if (!$userEvaluation) {
            $userEvaluation = new UserEvaluation();
            $userEvaluation->setUser($user);
            $userEvaluation->setEvaluation($evaluation);
            $userEvaluation->setStartedAt(new \DateTimeImmutable()); // ✅ IMPORTANT
            $em->persist($userEvaluation);
            $em->flush();
        }

        $questions = $evaluation->getQuestions();

        // 2) timer
        $startedAt = $userEvaluation->getStartedAt();
        if (!$startedAt) {
            $startedAt = new \DateTimeImmutable();
            $userEvaluation->setStartedAt($startedAt);
            $em->flush();
        }

        $endTime = (clone $startedAt)->modify("+{$evaluation->getDuration()} minutes");

        if (new \DateTimeImmutable() > $endTime) {
            $this->addFlash('danger', 'Le temps est écoulé ! Votre évaluation est terminée.');
            return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
        }

        // 3) POST = soumission
        if ($request->isMethod('POST')) {

            // ✅ CSRF
            if (!$this->isCsrfTokenValid('take_evaluation_'.$evaluation->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token');
            }

            $submittedAnswers = $request->request->all('answers'); // answers[questionId] = ansId (QUIZ) ou texte (EXAM)
            $score = 0;

            foreach ($questions as $question) {
                $qid = $question->getId();

                if (!isset($submittedAnswers[$qid])) {
                    continue;
                }

                $rawValue = $submittedAnswers[$qid];

                $answer = new Answer();
                $answer->setRole('SUBMISSION');
                $answer->setQuestion($question);
                $answer->setStudent($user);

                // ✅ QUIZ = QCM auto-correction (même si question.isMcq())
                if ($evaluation->getType() === 'QUIZ') {
                    $answerId = (int) $rawValue;

                    $selectedChoice = null;
                    foreach ($question->getAnswers() as $choice) {
                        if ($choice->isChoice() && $choice->getId() === $answerId) {
                            $selectedChoice = $choice;
                            break;
                        }
                    }

                    if ($selectedChoice) {
                        $answer->setContent($selectedChoice->getContent());
                        $isCorrect = $selectedChoice->isCorrectAnswer();
                        $answer->setIsCorrect($isCorrect);

                        if ($isCorrect) {
                            $score += (int) $question->getScore();
                        }
                    } else {
                        $answer->setContent((string) $rawValue);
                        $answer->setIsCorrect(false);
                    }
                }
                // ✅ EXAM = réponse texte (pas de correction auto)
                else {
                    $answer->setContent(trim((string) $rawValue));
                    $answer->setIsCorrect(null);
                }

                $em->persist($answer);
            }

            // enregistrer score + submittedAt
            $userEvaluation->setScore($score);
            $userEvaluation->setSubmittedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', 'Votre évaluation a été soumise !');
            return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
        }

        // 4) GET = afficher formulaire
        return $this->render('evaluation/user_take.html.twig', [
            'evaluation' => $evaluation,
            'questions' => $questions,
            'userEvaluation' => $userEvaluation,
            'endTime' => $endTime,
        ]);
    }

    #[Route('/result/{id}', name: 'user_evaluation_result', methods: ['GET'])]
    public function result(UserEvaluation $userEvaluation, EntityManagerInterface $em): Response
    {
        $questions = $userEvaluation->getEvaluation()->getQuestions();

        $user = $this->getUser();

        $submitted = $em->getRepository(Answer::class)->createQueryBuilder('a')
            ->where('a.student = :user')
            ->andWhere('a.role = :role')
            ->andWhere('a.question IN (:questions)')
            ->setParameter('user', $user)
            ->setParameter('role', 'SUBMISSION')
            ->setParameter('questions', $questions)
            ->getQuery()
            ->getResult();

        $submittedMap = [];
        foreach ($submitted as $ans) {
            $submittedMap[$ans->getQuestion()->getId()] = $ans;
        }

        return $this->render('evaluation/user_result.html.twig', [
            'userEvaluation' => $userEvaluation,
            'questions' => $questions,
            'submittedMap' => $submittedMap,
        ]);
    }
}
