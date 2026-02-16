<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SectionUpdated;
use Ec\Cqrs\Application\Service\CqrsFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class SectionUpdatedHandler
{
    private const WARMUP_MESSENGER = 'warmup::messenger';

    public function __construct(
        private readonly CqrsFactory $cqrsFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SectionUpdated $message): void
    {
        $this->logger->info('Invalidating cache for updated section', [
            'sectionId' => $message->id,
        ]);

        $command = $this->cqrsFactory->buildCommandNotification('section:cache-delete');
        $command->addParameter('-f', 'findSectionById')
            ->addParameter('-p', $message->id);

        $stamp = new AmqpStamp(self::WARMUP_MESSENGER);
        $this->messageBus->dispatch($command, [$stamp]);
    }
}
