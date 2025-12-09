<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // // Redirige l'utilisateur s'il est déjà connecté
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('app_admin_document_index');
        // }

        // Récupère l'erreur de connexion (si la soumission a échoué)
        $error = $authenticationUtils->getLastAuthenticationError();
        // Récupère le dernier email entré
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }
    #[Route(path: '/', name: 'home')]
    public function home(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        // Récupère le dernier email entré
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }


    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // La déconnexion est gérée automatiquement par Symfony (le firewall)
        throw new \LogicException('Cette méthode ne doit pas être atteinte.');
    }
}
