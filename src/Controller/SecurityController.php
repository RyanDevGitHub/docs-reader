<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
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
    public function home(AuthenticationUtils $authenticationUtils, Security $security): Response
    {
        // ----------------------------------------------------
        // NOUVEAU : Logique de Redirection pour Utilisateur Connecté
        // ----------------------------------------------------

        // La méthode isGranted('ROLE_USER') renvoie true si l'utilisateur est connecté (session ou cookie Remember Me)
        if ($security->isGranted('ROLE_USER')) {
            // Redirigez l'utilisateur vers la page principale
            return $this->redirectToRoute('admin_document_index');
        }

        // ----------------------------------------------------
        // ANCIEN : Logique d'affichage du Formulaire de Connexion
        // (Seulement si l'utilisateur N'EST PAS connecté)
        // ----------------------------------------------------

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
