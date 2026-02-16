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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
            ])
            ->add("upload", FileType::class, [
                "mapped" => false,
                "required" => false,
                "constraints" => [
                    new File([
                        "maxSize" => "500M",
                    ]),
                ],
            ])
            ->add("position", IntegerType::class);

        $builder->addEventListener(FormEvents::SUBMIT, function (
            FormEvent $event,
        ) {
            $lesson = $event->getData();
            $form = $event->getForm();

            if (!$lesson instanceof Lesson) {
                return;
            }

            $type = $lesson->getType();

            // For text lessons, validate content is not empty
            if ($type === "text") {
                $content = $lesson->getContent();
                if (empty(trim($content ?? ""))) {
                    $lesson->setContent(""); // This will trigger NotBlank constraint
                }
            }

            // For PDF/Video lessons without existing file, ensure upload is present
            if ($type === "pdf" || $type === "video") {
                $hasExistingFile = !empty(trim($lesson->getFilePath() ?? ""));
                $uploadField = $form->get("upload");
                $uploadedFile = $uploadField->getData();

                // Only require upload if there's no existing file
                if (!$hasExistingFile && !$uploadedFile) {
                    // Add a validation constraint dynamically
                    $lesson->setFilePath(""); // Trigger validation
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            "data_class" => Lesson::class,
            "constraints" => [
                new Callback(function (
                    $lesson,
                    ExecutionContextInterface $context,
                ) {
                    if (!$lesson instanceof Lesson) {
                        return;
                    }

                    $type = $lesson->getType();

                    // Validate text lesson has content
                    if ($type === "text") {
                        $content = trim($lesson->getContent() ?? "");
                        if ($content === "") {
                            $context
                                ->buildViolation(
                                    "Content is required for Text lessons.",
                                )
                                ->atPath("content")
                                ->addViolation();
                        }
                    }

                    // Validate PDF/Video lesson has file
                    if ($type === "pdf" || $type === "video") {
                        $filePath = trim($lesson->getFilePath() ?? "");
                        if ($filePath === "") {
                            // Check if upload field has a file
                            $form = $context->getRoot();
                            $uploadField = $form->get("upload");
                            $uploadedFile = $uploadField->getData();

                            if (!$uploadedFile) {
                                $context
                                    ->buildViolation(
                                        "Please upload a file for PDF/Video lessons.",
                                    )
                                    ->atPath("upload")
                                    ->addViolation();
                            }
                        }
                    }
                }),
            ],
        ]);
    }
}
