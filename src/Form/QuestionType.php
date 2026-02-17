<?php

namespace App\Form;

use App\Entity\Answer;
<<<<<<< Updated upstream
use App\Entity\Question;
use App\Entity\Evaluation;
=======
use App\Entity\Evaluation;
use App\Entity\Question;
>>>>>>> Stashed changes
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

class QuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Question content',
                'attr' => ['rows' => 4],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Question type',
<<<<<<< Updated upstream
                'choices' => [
                    'MCQ' => 'MCQ',
                    'TEXT' => 'TEXT',
                ],
=======
                'choices' => ['MCQ' => 'MCQ', 'TEXT' => 'TEXT'],
>>>>>>> Stashed changes
            ])
            ->add('score', IntegerType::class, [
                'label' => 'Score',
            ])
            ->add('evaluation', EntityType::class, [
                'class' => Evaluation::class,
                'choice_label' => 'title',
                'label' => 'Evaluation',
<<<<<<< Updated upstream
            ])
            // ✅ CHOICES (A/B/C/D)
=======
                'disabled' => $options['evaluation_locked'], // ✅ lock if coming from exam
            ])
>>>>>>> Stashed changes
            ->add('answers', CollectionType::class, [
                'entry_type' => AnswerType::class,
                'label' => false,
                'required' => false,
                'by_reference' => false,
                'allow_add' => false,
                'allow_delete' => false,
            ]);

<<<<<<< Updated upstream
        // ✅ si MCQ et NEW => crée automatiquement 4 choices vides
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $question = $event->getData();
            if (!$question) return;

            if ($question->getId() !== null) return; // edit => ne touche pas
=======
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $question = $event->getData();
            if (!$question) return;
            if ($question->getId() !== null) return;
>>>>>>> Stashed changes
            if ($question->getType() !== 'MCQ') return;
            if (count($question->getAnswers()) > 0) return;

            for ($i = 0; $i < 4; $i++) {
                $a = new Answer();
                $a->setRole('CHOICE');
                $a->setIsCorrect(false);
<<<<<<< Updated upstream
                $question->addAnswer($a);
                
            }
        });

        // ✅ au submit: forcer role = CHOICE pour les answers du MCQ
=======
                $a->setContent('');
                $question->addAnswer($a);
            }
        });

>>>>>>> Stashed changes
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $question = $event->getData();
            if (!$question) return;

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
<<<<<<< Updated upstream
        ]);
=======
            'evaluation_locked' => false,
        ]);

        $resolver->setAllowedTypes('evaluation_locked', 'bool');
>>>>>>> Stashed changes
    }
}
