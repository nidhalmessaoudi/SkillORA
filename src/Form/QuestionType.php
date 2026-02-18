<?php

namespace App\Form;

use App\Entity\Answer;
use App\Entity\Evaluation;
use App\Entity\Question;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class QuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Question / Exercice',
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir le contenu de la question/exercice.']),
                ],
                'attr' => ['rows' => 10],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Question type',
                'choices' => [
                    'MCQ' => 'MCQ',
                    'TEXT' => 'TEXT',
                ],
            ])
            ->add('score', IntegerType::class, [
                'label' => 'Points',
                'attr' => ['min' => 1],
                'empty_data' => '1',
            ])
            ->add('evaluation', EntityType::class, [
                'class' => Evaluation::class,
                'choice_label' => 'title',
                'label' => 'Evaluation',
                'disabled' => $options['evaluation_locked'],
            ])
            ->add('answers', CollectionType::class, [
                'entry_type' => AnswerType::class,
                'label' => false,
                'required' => false,
                'by_reference' => false,
                'allow_add' => false,
                'allow_delete' => false,
            ]);

        // Pré-remplir 4 choix si nouveau MCQ
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $question = $event->getData();
            if (!$question) return;
            if ($question->getId() !== null) return;
            if ($question->getType() !== 'MCQ') return;
            if (count($question->getAnswers()) > 0) return;

            for ($i = 0; $i < 4; $i++) {
                $a = new Answer();
                $a->setRole('CHOICE');
                $a->setIsCorrect(false);
                $a->setContent('');
                $question->addAnswer($a);
            }
        });

        // Nettoyage
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $question = $event->getData();
            if (!$question) return;

            // content toujours string
            $question->setContent(trim((string) $question->getContent()));

            if ((int) $question->getScore() < 1) {
                $question->setScore(1);
            }

            if ($question->getType() === 'MCQ') {
                foreach ($question->getAnswers() as $a) {
                    $a->setRole('CHOICE');
                    $a->setStudent(null);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Question::class,
            'evaluation_locked' => false,
        ]);

        $resolver->setAllowedTypes('evaluation_locked', 'bool');
    }
}
