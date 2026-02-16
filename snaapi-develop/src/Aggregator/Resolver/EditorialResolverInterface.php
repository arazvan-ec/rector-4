<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use Ec\Editorial\Domain\Model\NewsBase;

interface EditorialResolverInterface
{
    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult;

    public static function slot(): ResolverSlot;

    public function priority(): int;
}
