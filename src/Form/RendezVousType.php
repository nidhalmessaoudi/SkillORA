<?php

namespace App\Form;

use App\Entity\RendezVous;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('coursePdf', FileType::class, [
            'mapped' => false,
            'required' => false,
            'label' => 'Cours PDF',
            'constraints' => [
                new File([
                    'maxSize' => '5M',
                    'mimeTypes' => ['application/pdf'],
                    'mimeTypesMessage' => 'Veuillez uploader un fichier PDF valide.',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}

