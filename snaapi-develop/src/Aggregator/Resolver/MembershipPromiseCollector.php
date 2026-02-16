<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use Ec\Editorial\Domain\Model\NewsBase;
use Http\Promise\Promise;
use Psr\Log\LoggerInterface;

final class MembershipPromiseCollector implements EditorialResolverInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::MembershipLinks;
    }

    public function priority(): int
    {
        return -10;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        /** @var Promise|null $promise */
        $promise = $context->get('membership_promise');

        /** @var array<int, string> $links */
        $links = $context->get('membership_links', []);

        $membershipLinks = $this->resolvePromise($promise, $links);

        return new ResolverResult(self::slot(), ['membershipLinks' => $membershipLinks]);
    }

    /**
     * @param array<int, string> $links
     *
     * @return array<string, mixed>
     */
    private function resolvePromise(?Promise $promise, array $links): array
    {
        if (null === $promise) {
            return [];
        }

        try {
            /** @var array<string, mixed> $membershipLinkResult */
            $membershipLinkResult = $promise->wait();
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to resolve membership links', [
                'error' => $throwable->getMessage(),
            ]);

            return [];
        }

        if (empty($membershipLinkResult)) {
            return [];
        }

        return array_combine($links, $membershipLinkResult);
    }
}
