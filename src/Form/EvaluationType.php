<?php

namespace App\Form;

use App\Entity\Evaluation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class EvaluationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
<<<<<<< Updated upstream
=======
                'trim' => true,
                'empty_data' => '',
>>>>>>> Stashed changes
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
<<<<<<< Updated upstream
=======
                'trim' => true,
>>>>>>> Stashed changes
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Quiz' => 'QUIZ',
                    'Exam' => 'EXAM',
                ],
<<<<<<< Updated upstream
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Duration (minutes)',
            ])
=======
                'placeholder' => 'Choose a type',
            ])
            ->add('duration', IntegerType::class, [
                'label' => 'Duration (minutes)',
                'attr' => [
                    'min' => 1,
                ],
            ])

           ->add('totalScore', IntegerType::class, [
    'label' => 'Total Score (max score)',
    'attr' => ['min' => 1],
])

>>>>>>> Stashed changes
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evaluation::class,
        ]);
    }
}
