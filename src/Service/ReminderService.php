<?php

namespace App\Service;

use App\Entity\Delivery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReminderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerService $mailerService,
        private UrlGeneratorInterface $router // Nécessaire pour générer l'URL absolue
    ) {}

    public function run(): void
    {
        $deliveryRepo = $this->em->getRepository(Delivery::class);

        // Récupère les deliveries NON LU
        $deliveries = $deliveryRepo->findBy(['readAt' => null]);

        // Définir la date limite (maintenant - 3 heures) pour la comparaison
        $limitTime = (new \DateTimeImmutable('-3 hours'));

        foreach ($deliveries as $delivery) {

            // Détermine la date à vérifier :
            // Si un rappel a déjà été envoyé, on prend 'lastReminderAt'.
            // Sinon (première relance), on prend la date d'envoi initial 'sentAt'.
            $lastActionTime = $delivery->getLastReminderAt() ?? $delivery->getSentAt();

            // Si la dernière action (envoi ou rappel) est plus récente que la limite de 3h, on saute
            if ($lastActionTime && $lastActionTime > $limitTime) {
                continue;
            }

            // --- Logique d'envoi du rappel ---

            // Générer l'URL absolue en utilisant le router
            $readUrl = $this->router->generate('app_document_delivery_read', [
                'token' => $delivery->getToken(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);


            // Envoie le mail
            $this->mailerService->sendReminder(
                $delivery->getPartner()->getEmail(),
                $delivery->getDocument()->getTitle(),
                $readUrl,
                $delivery->getRelanceCount() + 1
            );

            // Mets à jour la delivery
            $now = new \DateTimeImmutable();
            $delivery->setLastReminderAt($now);
            $delivery->setRelanceCount($delivery->getRelanceCount() + 1);

            $this->em->persist($delivery);
        }

        $this->em->flush();
    }
}
