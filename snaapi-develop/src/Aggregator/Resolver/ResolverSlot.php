<?php

declare(strict_types=1);

namespace App\Aggregator\Resolver;

enum ResolverSlot: string
{
    case Section = 'section';
    case Tags = 'tags';
    case Signatures = 'signatures';
    case Multimedia = 'multimedia';
    case InsertedNews = 'insertedNews';
    case RecommendedEditorials = 'recommendedEditorials';
    case PhotoFromBodyTags = 'photoFromBodyTags';
    case MembershipLinks = 'membershipLinks';
    case CommentCount = 'commentCount';
}
