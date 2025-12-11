<?php
// src/EventSubscriber/Redirect404Subscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Redirect404Subscriber implements EventSubscriberInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // 1. Traiter uniquement les 404
        if (!$exception instanceof NotFoundHttpException) {
            return;
        }

        // 2. Simplification : Rediriger TOUJOURS vers la route protégée
        // Symfony se chargera de renvoyer l'utilisateur vers le login si nécessaire.
        $redirectRoute = 'admin_document_index';

        // 3. Créer la réponse de redirection (HTTP 302)
        $response = new RedirectResponse($this->urlGenerator->generate($redirectRoute));

        // Remplacer l'exception par la redirection
        $event->setResponse($response);
    }

    public static function getSubscribedEvents(): array
    {
        // Nous pouvons laisser la priorité par défaut (ou la supprimer, car c'est la seule logique).
        // Cependant, si nous voulons être absolument sûrs qu'il s'exécute, mettons une priorité positive simple.
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10], // Priorité positive
        ];
    }
}
