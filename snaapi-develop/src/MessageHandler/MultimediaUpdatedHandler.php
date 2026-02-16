<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\MultimediaUpdated;
use Ec\Cqrs\Application\Service\CqrsFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class MultimediaUpdatedHandler
{
    private const WARMUP_MESSENGER = 'warmup::messenger';

    public function __construct(
        private readonly CqrsFactory $cqrsFactory,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(MultimediaUpdated $message): void
    {
        $this->logger->info('Invalidating cache for updated multimedia', [
            'multimediaId' => $message->id,
        ]);

        $command = $this->cqrsFactory->buildCommandNotification('multimedia:cache-delete');
        $command->addParameter('-f', 'findMultimediaById')
            ->addParameter('-p', $message->id);

        $stamp = new AmqpStamp(self::WARMUP_MESSENGER);
        $this->messageBus->dispatch($command, [$stamp]);
    }
}
