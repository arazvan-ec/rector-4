<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\TagUpdated;
use Ec\Cqrs\Application\Service\CqrsFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class TagUpdatedHandler
{
    private const WARMUP_MESSENGER = 'warmup::messenger';

    public function __construct(
        private readonly CqrsFactory $cqrsFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TagUpdated $message): void
    {
        $this->logger->info('Invalidating cache for updated tag', [
            'tagId' => $message->id,
        ]);

        $command = $this->cqrsFactory->buildCommandNotification('tag:cache-delete');
        $command->addParameter('-f', 'findTagById')
            ->addParameter('-p', $message->id);

        $stamp = new AmqpStamp(self::WARMUP_MESSENGER);
        $this->messageBus->dispatch($command, [$stamp]);
    }
}
