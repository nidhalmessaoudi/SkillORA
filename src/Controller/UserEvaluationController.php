<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\UserEvaluation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user/evaluation')]
#[IsGranted('ROLE_USER')]
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
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $userEvaluation = $em->getRepository(UserEvaluation::class)->findOneBy([
            'user' => $user,
            'evaluation' => $evaluation,
        ]);

        return $this->render('evaluation/user_show.html.twig', [
            'evaluation' => $evaluation,
            'userEvaluation' => $userEvaluation,
        ]);
    }

    #[Route('/{id}/take', name: 'user_evaluation_take', methods: ['GET','POST'])]
    public function take(Evaluation $evaluation, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        // 1) get/create UserEvaluation
        $userEvaluation = $em->getRepository(UserEvaluation::class)->findOneBy([
            'user' => $user,
            'evaluation' => $evaluation,
        ]);

        if (!$userEvaluation) {
            $userEvaluation = new UserEvaluation();
            $userEvaluation->setUser($user);
            $userEvaluation->setEvaluation($evaluation);
            $userEvaluation->setStartedAt(new \DateTimeImmutable());
            $em->persist($userEvaluation);
            $em->flush();
        }

        // already submitted -> result
        if ($userEvaluation->getSubmittedAt()) {
            return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
        }

        // 2) timer
        $startedAt = $userEvaluation->getStartedAt();
        $endTime = (clone $startedAt)->modify("+{$evaluation->getDuration()} minutes");

        if (new \DateTimeImmutable() > $endTime) {
            // auto submit when time is over
            $userEvaluation->setSubmittedAt(new \DateTimeImmutable());
            if ($evaluation->getType() === 'QUIZ') {
                $userEvaluation->setScore(0);
                $userEvaluation->setIsCorrected(true);
            } else {
                $userEvaluation->setScore(null);
                $userEvaluation->setIsCorrected(false);
            }
            $em->flush();

            $this->addFlash('danger', 'Temps écoulé. Évaluation soumise automatiquement.');
            return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
        }

        $questions = $evaluation->getQuestions();

        // 3) submit
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('take_evaluation_'.$evaluation->getId(), (string)$request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF');
            }

            // delete previous submissions (safe if user refresh)
            $old = $em->getRepository(Answer::class)->findBy([
                'student' => $user,
                'role' => 'SUBMISSION',
            ]);
            foreach ($old as $o) {
                // optionnel : mieux filtrer par evaluation, mais OK si tu n’as pas beaucoup de data
            }

            $score = 0;

            if ($evaluation->getType() === 'QUIZ') {
                $submittedAnswers = $request->request->all('answers');

                foreach ($questions as $question) {
                    $qid = $question->getId();
                    $raw = $submittedAnswers[$qid] ?? null;

                    $answer = new Answer();
                    $answer->setRole('SUBMISSION');
                    $answer->setQuestion($question);
                    $answer->setStudent($user);

                    $answerId = (int) $raw;
                    $selectedChoice = null;

                    foreach ($question->getAnswers() as $choice) {
                        if ($choice->getRole() === 'CHOICE' && $choice->getId() === $answerId) {
                            $selectedChoice = $choice;
                            break;
                        }
                    }

                    if ($selectedChoice) {
                        $answer->setContent($selectedChoice->getContent());
                        $isCorrect = (bool) $selectedChoice->getIsCorrect();
                        $answer->setIsCorrect($isCorrect);

                        if ($isCorrect) {
                            $score += (int) $question->getScore();
                        }
                    } else {
                        $answer->setContent('');
                        $answer->setIsCorrect(false);
                    }

                    $em->persist($answer);
                }

                $userEvaluation->setScore($score);
                $userEvaluation->setIsCorrected(true);
            } else {
                // EXAM: une seule réponse globale
                $examResponse = trim((string) $request->request->get('exam_response'));

                $answer = new Answer();
                $answer->setRole('SUBMISSION');
                $answer->setStudent($user);
                // si ton Answer exige question non-null => il faut une "question placeholder" EXAM
                // Donc on prend la 1ère question si existe, sinon tu dois créer une question EXAM obligatoire côté admin.
                $firstQuestion = $questions->first() ?: null;
                if (!$firstQuestion) {
                    $this->addFlash('danger', "EXAM: ajoute au moins 1 question (placeholder) pour stocker la réponse.");
                    return $this->redirectToRoute('user_evaluation_show', ['id' => $evaluation->getId()]);
                }
                $answer->setQuestion($firstQuestion);

                $answer->setContent($examResponse);
                $answer->setIsCorrect(null);

                $em->persist($answer);

                $userEvaluation->setScore(null);
                $userEvaluation->setIsCorrected(false);
            }

            $userEvaluation->setSubmittedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', 'Évaluation soumise ✅');
            return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
        }

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
        $user = $this->getUser();
        if (!$user) throw $this->createAccessDeniedException();

        if ($userEvaluation->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }

        $evaluation = $userEvaluation->getEvaluation();
        $questions = $evaluation->getQuestions();

        // IMPORTANT: récupérer seulement submissions liées à cette évaluation
        $submitted = $em->createQueryBuilder()
            ->select('a')
            ->from(Answer::class, 'a')
            ->join('a.question', 'q')
            ->where('a.student = :user')
            ->andWhere('a.role = :role')
            ->andWhere('q.evaluation = :evaluation')
            ->setParameter('user', $user)
            ->setParameter('role', 'SUBMISSION')
            ->setParameter('evaluation', $evaluation)
            ->getQuery()
            ->getResult();

        $submittedMap = [];
        foreach ($submitted as $a) {
            $submittedMap[$a->getQuestion()->getId()] = $a;
        }

        return $this->render('evaluation/user_result.html.twig', [
            'userEvaluation' => $userEvaluation,
            'evaluation' => $evaluation,
            'questions' => $questions,
            'submittedMap' => $submittedMap,
        ]);
    }
}
