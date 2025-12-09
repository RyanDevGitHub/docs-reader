<?php

namespace App\Command;

use App\Service\ReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-reminders',
    description: 'Envoie les relances aux partenaires qui n’ont pas encore lu le document.',
)]
class SendRemindersCommand extends Command
{
    public function __construct(private ReminderService $reminderService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->reminderService->run();
        $output->writeln('Relances envoyées.');

        return Command::SUCCESS;
    }
}
