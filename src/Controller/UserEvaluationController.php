<?php

namespace App\Controller;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\UserEvaluation;
use App\Entity\Question;
use App\Service\AnswerPlagiarismOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
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
    public function index(
        EntityManagerInterface $em,
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        $qb = $em->getRepository(Evaluation::class)->createQueryBuilder('e')
            ->leftJoin('e.userEvaluations', 'ue')
            ->addSelect('ue')
            ->orderBy('e.createdAt', 'DESC');

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            6
        );

        return $this->render('evaluation/user_index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/{id}', name: 'user_evaluation_show', methods: ['GET'])]
    public function show(Evaluation $evaluation, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) throw $this->createAccessDeniedException();

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
    public function take(
        Evaluation $evaluation,
        Request $request,
        EntityManagerInterface $em,
        AnswerPlagiarismOrchestrator $orchestrator
    ): Response {
        $user = $this->getUser();
        if (!$user) throw $this->createAccessDeniedException();

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

        if ($userEvaluation->getSubmittedAt()) {
            if ($evaluation->getType() === 'EXAM') {
                return $this->redirectToRoute('user_evaluation_index');
            }
            return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
        }

        $startedAt = $userEvaluation->getStartedAt();
        $endTime = (clone $startedAt)->modify("+{$evaluation->getDuration()} minutes");

        if (new \DateTimeImmutable() > $endTime) {
            $userEvaluation->setSubmittedAt(new \DateTimeImmutable());

            if ($evaluation->getType() === 'QUIZ') {
                $userEvaluation->setScore(0);
                $userEvaluation->setIsCorrected(true);
                $em->flush();

                $this->addFlash('danger', 'Time is up. Your quiz has been submitted automatically.');
                return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
            }

            $userEvaluation->setScore(null);
            $userEvaluation->setIsCorrected(false);
            $em->flush();

            $this->addFlash('danger', 'Time is up. Your exam has been submitted automatically and is now pending review.');
            return $this->redirectToRoute('user_evaluation_index');
        }

        $questions = $evaluation->getQuestions();

        $examDraft = null;
        if ($evaluation->getType() === 'EXAM') {
            $firstQuestion = $questions->first() ?: null;

            if (!$firstQuestion) {
                $firstQuestion = new Question();
                $firstQuestion->setEvaluation($evaluation);
                $firstQuestion->setType('TEXT');
                $firstQuestion->setScore(0);
                $firstQuestion->setContent('EXAM_SUBMISSION_PLACEHOLDER');
                $em->persist($firstQuestion);
                $em->flush();
            }

            $examDraft = $em->getRepository(Answer::class)->findOneBy([
                'student' => $user,
                'question' => $firstQuestion,
                'role' => 'SUBMISSION',
            ]);

            if (!$examDraft) {
                $examDraft = new Answer();
                $examDraft->setRole('SUBMISSION');
                $examDraft->setStudent($user);
                $examDraft->setQuestion($firstQuestion);
                $examDraft->setContent('');
                $examDraft->setIsCorrect(null);
                $em->persist($examDraft);
                $em->flush();
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('take_evaluation_'.$evaluation->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF');
            }

            if ($evaluation->getType() === 'QUIZ') {
                $oldSubmissions = $em->createQueryBuilder()
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

                foreach ($oldSubmissions as $old) {
                    $em->remove($old);
                }

                $score = 0;
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
                $userEvaluation->setSubmittedAt(new \DateTimeImmutable());
                $em->flush();

                $this->addFlash('success', 'Quiz submitted successfully.');
                return $this->redirectToRoute('user_evaluation_result', ['id' => $userEvaluation->getId()]);
            }

            $examResponse = trim((string) $request->request->get('exam_response', ''));

            if (!$examDraft) {
                throw new \RuntimeException('Exam draft not found');
            }

            $examDraft->setContent($examResponse);
            $em->persist($examDraft);

            $userEvaluation->setScore(null);
            $userEvaluation->setIsCorrected(false);
            $userEvaluation->setSubmittedAt(new \DateTimeImmutable());

            $em->flush();

            $orchestrator->analyzeAndSave($examDraft);

            $this->addFlash('success', 'Submission successful. Your exam is being analyzed for plagiarism.');
            return $this->redirectToRoute('user_evaluation_index');
        }

        return $this->render('evaluation/user_take.html.twig', [
            'evaluation' => $evaluation,
            'questions' => $questions,
            'userEvaluation' => $userEvaluation,
            'endTime' => $endTime,
            'examDraft' => $examDraft,
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