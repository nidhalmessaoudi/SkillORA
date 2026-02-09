<?php

namespace App\Form;

use App\Entity\Lesson;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

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
                    "PDF" => "pdf",
                    "Video" => "video",
                ],
            ])
            ->add("content", TextareaType::class, [
                "required" => false,
                "help" => "Only for Text lessons.",
            ])
            ->add("upload", FileType::class, [
                "mapped" => false,
                "required" => false,
                "help" =>
                    "Required for PDF/Video lessons. Ignored for Text lessons.",
                "constraints" => [
                    new File([
                        "maxSize" => "500M",
                    ]),
                ],
                "attr" => [
                    "type" => "file",
                    "accept" => "application/pdf,video/*",
                ],
            ])
            ->add("position", IntegerType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => Lesson::class,
        ]);
    }
}
