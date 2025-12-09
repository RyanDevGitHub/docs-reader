<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Entity\Document;
use App\Form\DocumentUploadType;
use App\Repository\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

use function Symfony\Component\Clock\now;

final class DocumentController extends AbstractController
{


    public function __construct(private EntityManagerInterface $em, private UrlGeneratorInterface $router, private Environment $twig) {}
    #[Route('/document', name: 'app_document')]
    public function index(): Response
    {
        return $this->render('document/index.html.twig', [
            'controller_name' => 'DocumentController',
        ]);
    }

    #[Route('/admin/document/show/{id}', name: 'app_admin_document_show', methods: ['GET'])]
    public function show(Document $document): Response
    {
        // Grâce au ParamConverter de Symfony, l'ID dans l'URL est automatiquement transformé en objet Document.

        return $this->render('document/show.html.twig', [
            'document' => $document,
            // On récupère les livraisons associées à ce document pour le tableau de suivi.
            'deliveries' => $document->getDeliveries(),
        ]);
    }

    #[Route('/admin/document/new', name: 'app_admin_document_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        SluggerInterface $slugger,
        MailerInterface $mailer // Injecté dans la méthode
    ): Response {

        // Utilisez le bon nom d'entité : Document
        $document = new \App\Entity\Document();
        $form = $this->createForm(\App\Form\DocumentUploadType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // ⚠️ 1. RÉCUPÉRATION DU FICHIER UPLOADÉ ET DE LA DATE
            /** @var UploadedFile $documentFile */
            $documentFile = $form->get('documentFile')->getData();

            if ($documentFile) {

                // Création d'un nom de fichier unique et sécurisé
                $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $documentFile->guessExtension();

                // 2. STOCKAGE PHYSIQUE DU FICHIER
                try {
                    $documentFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/documents',
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du déplacement du fichier : ' . $e->getMessage());
                    return $this->redirectToRoute('admin_document_index');
                }

                // 🔑 3. DÉFINITION DES CHAMPS NON NULLS POUR L'ENTITÉ DOCUMENT
                $document->setFilename($newFilename);
            }

            // 🔑 4. DÉFINITION DU CHAMP NON NUL 'createdAt'
            $document->setCreatedAt(new \DateTime());

            $this->em->persist($document);
            $this->em->flush(); // Flusher le Document en premier

            // ... (Logique de création des Deliveries) ...
            $selectedPartners = $form->get('partners')->getData();

            foreach ($selectedPartners as $partner) {
                $delivery = new \App\Entity\Delivery();
                $delivery->setDocument($document);
                $delivery->setPartner($partner);

                // 🔑 DÉFINITION DU CHAMP NON NUL 'sentAt'
                $delivery->setSentAt(new \DateTimeImmutable());
                $delivery->setRelanceCount(0); // Initialisation du compteur
                $delivery->setToken(bin2hex(random_bytes(16)));

                $this->em->persist($delivery);

                // 5. Envoyer l'email
                $this->sendPartnerEmail($delivery, $mailer, $this->router); // Le router est maintenant passé
            }

            $this->em->flush(); // Flusher les Deliveries

            $this->addFlash('success', 'Le document a été uploadé et les partenaires notifiés.');
            return $this->redirectToRoute('admin_document_index');
        }

        return $this->render('document/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/read/{token}', name: 'app_document_delivery_read', methods: ['GET'])]
    public function readByToken(string $token, DeliveryRepository $deliveryRepo): Response
    {
        $delivery = $deliveryRepo->findOneBy(['token' => $token]);

        if (!$delivery) {
            throw $this->createNotFoundException("Ce lien de consultation est invalide ou a expiré.");
        }

        $document = $delivery->getDocument();

        // 1. Déterminer le chemin du fichier pour l'URL publique
        $documentUrl = '/uploads/documents/' . $document->getFilename();

        // 2. Rendre le template de visualisation/confirmation
        return $this->render('document/read_with_confirmation.html.twig', [
            'delivery' => $delivery,
            'document_url' => $documentUrl,
            'token' => $token,
            'is_read' => $delivery->getReadAt() !== null,
        ]);
    }
    /**
     * Envoi d'email avec le lien sécurisé (token)
     */
    private function sendPartnerEmail(\App\Entity\Delivery $delivery, MailerInterface $mailer, UrlGeneratorInterface $router): void // 🔑 Ajout de l'objet Twig
    {
        // Générer le lien de lecture absolu
        $readUrl = $router->generate('app_document_delivery_read', [
            'token' => $delivery->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // 🔑 RENDU DU TEMPLATE TWIG
        $htmlContent = $this->twig->render('emails/document_notification.html.twig', [
            'delivery' => $delivery,
            'documentTitle' => $delivery->getDocument()->getTitle(),
            'partnerName' => $delivery->getPartner()->getName(),
            'readUrl' => $readUrl,
        ]);

        $email = (new \Symfony\Component\Mime\Email())
            ->from('noreply@votre-app.com')
            ->to($delivery->getPartner()->getEmail())
            ->subject('Nouveau Document Disponible : ' . $delivery->getDocument()->getTitle())
            ->html($htmlContent); // 🔑 UTILISATION DU CONTENU RENDU

        $mailer->send($email);
    }
    #[Route('/mark-as-read/{token}', name: 'app_document_mark_as_read', methods: ['POST'])]
    public function markAsRead(string $token, DeliveryRepository $deliveryRepo): Response
    {
        $delivery = $deliveryRepo->findOneBy(['token' => $token]);

        if (!$delivery) {
            throw $this->createNotFoundException("Lien invalide.");
        }

        if ($delivery->getReadAt() === null) {
            // Mettre à jour la date de lecture
            $delivery->setReadAt(new \DateTimeImmutable());

            // 🔑 Persistance
            $this->em->flush();

            $this->addFlash('success', "Lecture du document confirmée. Le suivi a été mis à jour.");
        } else {
            $this->addFlash('info', "Ce document était déjà marqué comme lu.");
        }

        // Rediriger vers la page de visualisation après le traitement (GET)
        return $this->redirectToRoute('app_document_delivery_read', ['token' => $token]);
    }

    #[Route('/admin/delivery/{deliveryId}/mark-read', name: 'admin_mark_as_read', methods: ['POST'])]
    public function adminMarkAsRead(int $deliveryId): Response
    {
        // 1. Chercher la Delivery par ID (on utilise l'ID car on est connecté et admin)
        $delivery = $this->em->getRepository(\App\Entity\Delivery::class)->find($deliveryId);

        if (!$delivery) {
            throw $this->createNotFoundException('Livraison introuvable.');
        }

        // 2. Mettre à jour si elle n'est pas lue
        if (!$delivery->getReadAt()) {
            $delivery->setReadAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->addFlash('success', 'Le statut de lecture pour ' . $delivery->getPartner()->getName() . ' a été mis à jour.');
        } else {
            $this->addFlash('info', 'Le document était déjà marqué comme lu pour ' . $delivery->getPartner()->getName() . '.');
        }

        // 3. Rediriger vers la page de suivi du document
        return $this->redirectToRoute('app_admin_document_show', ['id' => $delivery->getDocument()->getId()]);
    }
}
