<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

use App\Infrastructure\Enum\SitesEnum;
use Ec\Editorial\Domain\Model\Body\BodyTagMembershipCard;
use Ec\Editorial\Domain\Model\Body\MembershipCardButton;
use Ec\Editorial\Domain\Model\Editorial;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Membership\Infrastructure\Client\Http\QueryMembershipClient;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;

final class MembershipPromiseInitiator implements EditorialResolverInterface
{
    public function __construct(
        private readonly QueryMembershipClient $queryMembershipClient,
        private readonly UriFactoryInterface $uriFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public static function slot(): ResolverSlot
    {
        return ResolverSlot::MembershipLinks;
    }

    public function priority(): int
    {
        return 90;
    }

    public function resolve(NewsBase $editorial, ResolverContext $context): ResolverResult
    {
        /** @var \Ec\Section\Domain\Model\Section|null $section */
        $section = $context->get('resolved_section');

        if (null === $section || !$editorial instanceof Editorial) {
            return new ResolverResult(self::slot(), ['membershipLinks' => []]);
        }

        try {
            $linksData = $this->getLinksFromBody($editorial);

            $links = [];
            $uris = [];
            foreach ($linksData as $membershipLink) {
                $uris[] = $this->uriFactory->createUri($membershipLink);
                $links[] = $membershipLink;
            }

            /** @var \Http\Promise\Promise $promise */
            $promise = $this->queryMembershipClient->getMembershipUrl(
                $editorial->id()->id(),
                $uris,
                SitesEnum::getEncodenameById($section->siteId()),
                true
            );

            $context->set('membership_promise', $promise);
            $context->set('membership_links', $links);
        } catch (\Throwable $throwable) {
            $this->logger->warning('Failed to initiate membership links resolution', [
                'editorialId' => $editorial->id()->id(),
                'error' => $throwable->getMessage(),
            ]);
        }

        return new ResolverResult(self::slot(), ['membershipLinks' => []]);
    }

    /**
     * @return array<int, string>
     */
    private function getLinksFromBody(Editorial $editorial): array
    {
        $linksData = [];

        $bodyElementsMembership = $editorial->body()->bodyElementsOf(BodyTagMembershipCard::class);
        /** @var BodyTagMembershipCard $bodyElement */
        foreach ($bodyElementsMembership as $bodyElement) {
            /** @var MembershipCardButton $button */
            foreach ($bodyElement->buttons()->buttons() as $button) {
                $linksData[] = $button->urlMembership();
                $linksData[] = $button->url();
            }
        }

        return $linksData;
    }
}
