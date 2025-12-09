<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Delivery;
use App\Form\DocumentType;
use App\Form\DocumentUploadType;
use App\Repository\PartnerRepository;
use App\Service\TokenService;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;;

class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PartnerRepository $partnerRepo,
        private TokenService $tokenService,
        private MailerService $mailerService
    ) {}


    #[Route('/admin/document/upload', name: 'admin_document_upload')]
    public function upload(Request $request): Response
    {
        $document = new Document();
        $form = $this->createForm(DocumentUploadType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupère le fichier uploadé (mapped=false dans le form)
            $file = $form->get('file')->getData();

            if ($file) {
                // Répertoire privé (en dehors de public) — config parameter 'documents_directory'
                $targetDir = $this->getParameter('documents_directory'); // ex: '%kernel.project_dir%/var/storage/documents'

                // crée le dossier si absent
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                $fileName = uniqid('doc_') . '.' . $file->guessExtension();

                try {
                    $file->move($targetDir, $fileName);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l’upload du fichier : ' . $e->getMessage());
                    return $this->redirectToRoute('admin_document_upload');
                }

                // Enregistre les infos du document
                $document->setFilename($fileName);
                // filepath relatif ou absolu selon ton choix ; ici on stocke le chemin relatif dans var
                $document->setFilepath($targetDir . DIRECTORY_SEPARATOR . $fileName);
                $document->setCreatedAt(new \DateTime());

                $this->em->persist($document);
                $this->em->flush();
            }

            // --- Création des deliveries et envoi de mails à tous les partners ---
            $partners = $this->partnerRepo->findAll();

            foreach ($partners as $partner) {
                // génère token unique
                $token = $this->tokenService->generate();

                $delivery = new Delivery();
                $delivery->setPartner($partner);
                $delivery->setDocument($document);
                $delivery->setToken($token);
                $delivery->setSentAt(new \DateTimeImmutable());
                $delivery->setRelanceCount(0);

                $this->em->persist($delivery);
                // flush hors boucle pour performance (mais on flushera après la boucle)
            }

            $this->em->flush();

            // Maintenant envoie les mails (après flush pour avoir les deliveries persistées si besoin)
            // Récupération des deliveries créées pour ce document
            $deliveries = $this->em->getRepository(Delivery::class)->findBy(['document' => $document]);

            // Base URL de l'app (ajoute param 'app_base_url' dans config/services.yaml si pas présent)
            $baseUrl = $this->getParameter('app_base_url'); // ex: https://docs.tondomaine.fr

            foreach ($deliveries as $delivery) {
                $token = $delivery->getToken();
                $partner = $delivery->getPartner();
                $url = rtrim($baseUrl, '/') . '/read/' . $token;

                // envoie le mail (MailerService -> sendLink($to, $url))
                try {
                    $this->mailerService->sendLink($partner->getEmail(), $url);
                } catch (\Throwable $e) {
                    // log et continuer — en prod tu gères mieux les erreurs (monolog)
                    // option : incrémenter un champ 'mail_error' si besoin
                }
            }

            $this->addFlash('success', 'Document uploadé et mails envoyés aux partenaires.');

            return $this->redirectToRoute('admin_document_upload');
        }

        return $this->render('admin/document_upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/admin/documents', name: 'admin_document_index')]
    public function index(): Response
    {
        $documentRepo = $this->em->getRepository(Document::class);

        // 2. Récupérer TOUS les documents (pour la vue grille)
        // On pourrait ajouter un orderBy pour un tri par défaut (ex: le plus récent en premier)
        $documents = $documentRepo->findBy([], ['createdAt' => 'DESC']);

        // 3. Renvoyer au template
        return $this->render('document/index.html.twig', [
            'documents' => $documents,
        ]);
    }
    #[Route('/admin/document/shows/{id}/detail', name: 'admin_document_detail')]
    public function detail(Document $document): Response
    {
        // Les livraisons sont déjà chargées par la relation OneToMany sur l'entité Document
        // (Si vous utilisez le code que j'ai mis en Étape 1, sinon on utiliserait le repository)

        return $this->render('admin/document_detail.html.twig', [
            'document' => $document,
            'deliveries' => $document->getDeliveries()->toArray(), // Assure un tableau pour Twig
        ]);
    }
    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $documentRepo = $this->em->getRepository(Document::class);

        // Récupère tous les documents avec les relations de delivery pour optimiser
        $documents = $documentRepo->createQueryBuilder('d')
            ->leftJoin('d.deliveries', 'del')
            ->addSelect('del')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/dashboard.html.twig', [
            'documents' => $documents,
        ]);
    }
}
