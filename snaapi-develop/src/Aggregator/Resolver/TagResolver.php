<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Tag\Domain\Model\QueryTagClient;
use GuzzleHttp\Promise\Utils;
use Http\Promise\Promise;
use Psr\Log\LoggerInterface;

final class TagResolver implements EditorialResolverInterface
{
    public function __construct(
        private readonly QueryTagClient $queryTagClient,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::Tags;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        $promises = [];
        $tagIds = [];

        foreach ($editorial->tags()->getArrayCopy() as $tag) {
            try {
                $promises[] = $this->queryTagClient->findTagById($tag->id(), true);
                $tagIds[] = $tag->id();
            } catch (\Throwable $exception) {
                $this->logger->warning('Failed to initiate async tag request', [
                    'tagId' => $tag->id(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $tags = [];

        if (!empty($promises)) {
            /** @var array<int, array{state: string, value?: mixed, reason?: \Throwable}> $results */
            $results = Utils::settle($promises)->wait();

            foreach ($results as $index => $result) {
                if (Promise::FULFILLED === $result['state']) {
                    $tags[] = $result['value'];
                } else {
                    $this->logger->warning('Failed to resolve tag', [
                        'tagId' => $tagIds[$index] ?? 'unknown',
                        'error' => $result['reason'] instanceof \Throwable
                            ? $result['reason']->getMessage()
                            : 'Unknown error',
                    ]);
                }
            }
        }

        return new ResolverResult(self::slot(), ['tags' => $tags]);
    }
}
