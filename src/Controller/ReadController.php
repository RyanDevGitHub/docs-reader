<?php

namespace App\Controller;

use App\Entity\Delivery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

class ReadController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/read/{token}', name: 'read_document')]
    public function read(string $token): Response
    {
        $delivery = $this->em->getRepository(Delivery::class)
            ->findOneBy(['token' => $token]);

        if (!$delivery) {
            return $this->render('read/error.html.twig', [
                'message' => 'Lien invalide ou expiré.'
            ]);
        }


        $document = $delivery->getDocument();

        return $this->render('read/view.html.twig', [
            'delivery' => $delivery,
            'document' => $document,
            'token' => $token
        ]);
    }

    #[Route('/read/{token}/confirm', name: 'confirm_read')]
    public function confirmRead(string $token): Response
    {
        $delivery = $this->em->getRepository(Delivery::class)
            ->findOneBy(['token' => $token]);

        if (!$delivery) {
            return $this->render('read/error.html.twig', [
                'message' => 'Lien invalide.'
            ]);
        }

        // Si déjà lu, pas grave, on évite la double validation
        if (!$delivery->getReadAt()) {
            $delivery->setReadAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        return $this->render('read/confirmed.html.twig', [
            'partner' => $delivery->getPartner(),
            'document' => $delivery->getDocument()
        ]);
    }
    #[Route('/read/{token}/file', name: 'read_document_file')]
    public function serveFile(string $token): Response
    {
        $delivery = $this->em->getRepository(Delivery::class)
            ->findOneBy(['token' => $token]);

        if (!$delivery) {
            throw $this->createNotFoundException('Lien invalide.');
        }

        $document = $delivery->getDocument();
        $filePath = "" . __DIR__ . '/../../public/uploads/documents/' . $document->getFilename();



        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file($filePath, $document->getFilename(), ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
