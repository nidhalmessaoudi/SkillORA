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
            ->add("content", TextareaType::class, [
                "required" => false,
                "help" =>
                    "For Video lessons, put the URL here. For Text lessons, put the formatted text here.",
            ])
            ->add("filePath", TextType::class, [
                "required" => false,
                "help" =>
                    "For File lessons, put a path like /uploads/lessons/file.pdf (we’ll add real upload later).",
            ])
            ->add("position", IntegerType::class);

        // section + createdAt + updatedAt are set in backend (controller + lifecycle callbacks)
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => Lesson::class,
        ]);
    }
}
