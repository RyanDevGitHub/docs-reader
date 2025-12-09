<?php

namespace App\Form;

use App\Entity\Document;
use App\Entity\Partner;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class DocumentUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 1. CHAMP TITRE (Mappé à l'entité Document)
            ->add('title', TextType::class, [
                'label' => 'Titre du Document',
                'constraints' => [new NotBlank()],
            ])

            // 2. CHAMP UPLOAD DE FICHIER (Non mappé, traité dans le contrôleur)
            ->add('documentFile', FileType::class, [
                'label' => 'Fichier à uploader (PDF, DocX, etc.)',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '50M', // Exemple 2MB
                        'mimeTypes' => [
                            'application/pdf',
                            'application/x-pdf',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // Ex: docx
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader un document valide (PDF, DOCX).',
                    ])
                ],
            ])

            // 3. CHAMP PARTENAIRES (Non mappé, utilise EntityType)
            ->add('partners', EntityType::class, [
                'class' => Partner::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Partenaires Destinataires',
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
        ]);
    }
}
