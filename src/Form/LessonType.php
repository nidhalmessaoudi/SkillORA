<?php

namespace App\Form;

use App\Entity\Lesson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LessonType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add("title", TextType::class)
            ->add("type", ChoiceType::class, [
                "choices" => [
                    "Text" => "text",
                    "Video (URL)" => "video",
                    "File" => "file",
                ],
            ])
            ->add("content", TextareaType::class, ["required" => false])
            ->add("filePath", TextType::class, ["required" => false])
            ->add("position", IntegerType::class);
        // section set in controller
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(["data_class" => Lesson::class]);
    }
}
