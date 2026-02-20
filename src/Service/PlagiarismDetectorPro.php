<?php

namespace App\Service;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\PlagiarismPair;
use App\Entity\PlagiarismRun;
use App\Entity\SubmissionEmbedding;
use Doctrine\ORM\EntityManagerInterface;

class PlagiarismDetectorPro
{
    public function __construct(
        private EntityManagerInterface $em,
        private OpenAIEmbeddingsClient $openai
    ) {}

    public function run(Evaluation $evaluation): PlagiarismRun
    {
        if ($evaluation->getType() !== 'EXAM') {
            throw new \RuntimeException('Plagiarism only for EXAM');
        }

        // 1) récupérer toutes les submissions (Answer SUBMISSION) de cette evaluation
        $submissions = $this->em->createQueryBuilder()
            ->select('a', 'q')
            ->from(Answer::class, 'a')
            ->join('a.question', 'q')
            ->where('q.evaluation = :e')
            ->andWhere('a.role = :role')
            ->andWhere('a.student IS NOT NULL')
            ->setParameter('e', $evaluation)
            ->setParameter('role', 'SUBMISSION')
            ->getQuery()
            ->getResult();

        $run = new PlagiarismRun();
        $run->setEvaluation($evaluation);

        $thresholds = [
            'suspect' => 0.86,
            'high' => 0.92,
            'min_chars' => 50,
            'weights' => ['semantic'=>0.60, 'lexical'=>0.30, 'structure'=>0.10],
        ];
        $run->setThresholds($thresholds);
        $run->setSubmissionsCount(count($submissions));

        $this->em->persist($run);
        $this->em->flush();

        // 2) embeddings cache
        $embRepo = $this->em->getRepository(SubmissionEmbedding::class);

        foreach ($submissions as $ans) {
            $text = TextSimilarity::normalize((string) $ans->getContent());
            if (mb_strlen($text) < $thresholds['min_chars']) {
                continue; // too short, ignore (still compared => will be low)
            }

            $hash = hash('sha256', $text);
            $emb = $embRepo->findOneBy(['answer' => $ans]);

            if (!$emb) {
                $emb = new SubmissionEmbedding();
                $emb->setAnswer($ans);
            }

            if ($emb->getContentHash() !== $hash || count($emb->getEmbedding()) === 0) {
                $vec = $this->openai->embed($text);
                $emb->setContentHash($hash);
                $emb->setEmbedding($vec);
                $emb->touch();
                $this->em->persist($emb);
            }
        }
        $this->em->flush();

        // 3) comparer paires
        $n = count($submissions);
        $pairsCount = 0;

        for ($i=0; $i<$n; $i++) {
            for ($j=$i+1; $j<$n; $j++) {

                /** @var Answer $a */
                $a = $submissions[$i];
                /** @var Answer $b */
                $b = $submissions[$j];

                // éviter comparer même étudiant (si jamais)
                if ($a->getStudent() && $b->getStudent() && $a->getStudent()->getId() === $b->getStudent()->getId()) {
                    continue;
                }

                $ta = (string) $a->getContent();
                $tb = (string) $b->getContent();

                // semantic
                $ea = $embRepo->findOneBy(['answer' => $a]);
                $eb = $embRepo->findOneBy(['answer' => $b]);

                $semantic = VectorMath::cosine($ea?->getEmbedding() ?? [], $eb?->getEmbedding() ?? []);

                // lexical + highlights
                $lex = TextSimilarity::lexicalShingles($ta, $tb, 5);
                $lexical = $lex['score'];
                $highlights = $lex['common'];

                // structure
                $structure = TextSimilarity::structureScore($ta, $tb);

                // final weighted score
                $w = $thresholds['weights'];
                $final = ($w['semantic']*$semantic) + ($w['lexical']*$lexical) + ($w['structure']*$structure);

                $final = max(0.0, min(1.0, $final));
                $percent = (int) round($final * 100);

                $status = 'OK';
                if ($final >= $thresholds['high']) $status = 'HIGH';
                elseif ($final >= $thresholds['suspect']) $status = 'SUSPECT';

                // option pro : ignorer si score bas
                if ($final < 0.60) {
                    continue;
                }

                $pair = new PlagiarismPair();
                $pair->setRun($run);
                $pair->setEvaluation($evaluation);
                $pair->setAnswerA($a);
                $pair->setAnswerB($b);
                $pair->setSemantic($semantic);
                $pair->setLexical($lexical);
                $pair->setStructure($structure);
                $pair->setFinalScore($final);
                $pair->setPlagiarismPercent($percent);
                $pair->setStatus($status);
                $pair->setHighlights([
                    'common_phrases' => $highlights,
                ]);

                $this->em->persist($pair);
                $pairsCount++;
            }
        }

        $run->setPairsCount($pairsCount);
        $this->em->flush();

        return $run;
    }
}
