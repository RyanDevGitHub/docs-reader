<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailerService
{
    public function __construct(private MailerInterface $mailer, private Environment $twig) {}

    public function sendLink(string $to, string $url)
    {
        $email = (new Email())
            ->from('no-reply@webcatalyste.fr')
            ->to($to)
            ->subject('Nouveau document à lire')
            ->html("Bonjour,<br><br>Merci de lire ce document : <a href='$url'>$url</a>");

        $this->mailer->send($email);
    }

    public function sendReminder(string $to, string $docTitle, string $url, int $number): void
    {
        // 🔑 RENDU DU TEMPLATE TWIG
        $htmlContent = $this->twig->render('emails/reminder.html.twig', [
            'docTitle' => $docTitle,
            'url' => $url,
            'number' => $number,
        ]);

        $email = (new Email())
            ->from('no-reply@webcatalyste.fr')
            ->to($to)
            ->subject("Rappel #$number : Veuillez lire le document \"$docTitle\"")
            ->html($htmlContent); // 🔑 UTILISATION DU CONTENU RENDU

        $this->mailer->send($email);
    }
}
