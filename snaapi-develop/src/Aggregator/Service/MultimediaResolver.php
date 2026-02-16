<?php

declare(strict_types=1);

namespace App\Aggregator\Service;

use App\Orchestrator\Chain\Multimedia\MultimediaOrchestratorHandler;
use App\Orchestrator\Exceptions\OrchestratorTypeNotExistException;
use Ec\Editorial\Domain\Model\Multimedia\Multimedia;
use Ec\Editorial\Domain\Model\Multimedia\MultimediaId;
use Ec\Editorial\Domain\Model\Multimedia\PhotoExist;
use Ec\Editorial\Domain\Model\Multimedia\Video;
use Ec\Editorial\Domain\Model\Multimedia\Widget;
use Ec\Editorial\Domain\Model\NewsBase;
use Ec\Infrastructure\Client\Exceptions\InvalidBodyException;
use Ec\Multimedia\Domain\Model\Multimedia\MultimediaPhoto;
use Ec\Multimedia\Infrastructure\Client\Http\Media\QueryMultimediaClient as QueryMultimediaOpeningClient;
use Ec\Multimedia\Infrastructure\Client\Http\QueryMultimediaClient;
use GuzzleHttp\Promise\Utils;
use Http\Promise\Promise;
use Psr\Log\LoggerInterface;

class MultimediaResolver
{
    public const ASYNC = true;
    public const UNWRAPPED = true;

    public function __construct(
        private readonly QueryMultimediaClient $queryMultimediaClient,
        private readonly QueryMultimediaOpeningClient $queryMultimediaOpeningClient,
        private readonly MultimediaOrchestratorHandler $multimediaTypeOrchestratorHandler,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetch multimedia asynchronously for the editorial and settle promises.
     *
     * @return array{multimedia: array<string, mixed>|null, multimediaOpening: array<string, mixed>|null}
     */
    public function resolve(NewsBase $editorial): array
    {
        $multimediaPromises = [];
        $multimediaOpening = [];

        // Collect async multimedia promises for editorial
        $multimediaId = $this->getMultimediaId($editorial->multimedia());
        if (null !== $multimediaId) {
            $multimediaPromises[] = $this->queryMultimediaClient->findMultimediaById($multimediaId, self::ASYNC);
        }

        // Resolve opening multimedia
        $multimediaOpening = $this->resolveOpening($editorial);

        // If no opening found, try meta image
        if (empty($multimediaOpening)) {
            $multimediaOpening = $this->resolveMetaImage($editorial);
        }

        // Settle multimedia promises
        $resolvedMultimedia = null;
        if (!empty($multimediaPromises) && !($editorial->multimedia() instanceof Widget)) {
            $resolvedMultimedia = Utils::settle($multimediaPromises)
                ->then($this->createCallback([$this, 'filterFulfilled']))
                ->wait(self::UNWRAPPED);
        }

        return [
            'multimedia' => $resolvedMultimedia,
            'multimediaOpening' => $multimediaOpening ?: null,
        ];
    }

    /**
     * Collect async multimedia promise for a sub-editorial (inserted news or recommended).
     *
     * @param array<int, Promise> $promises
     *
     * @return array<int, Promise>
     */
    public function collectAsyncPromise(Multimedia $multimedia, array $promises): array
    {
        $multimediaId = $this->getMultimediaId($multimedia);
        if (null !== $multimediaId) {
            $promises[] = $this->queryMultimediaClient->findMultimediaById($multimediaId, self::ASYNC);
        }

        return $promises;
    }

    /**
     * Get the meta image ID for an editorial that has no multimedia.
     */
    public function getMetaImageId(NewsBase $editorial): ?string
    {
        if (!empty($editorial->multimedia()->id()->id())) {
            return $editorial->multimedia()->id()->id();
        }

        return $editorial->metaImage() ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveOpening(NewsBase $editorial): array
    {
        $opening = $editorial->opening();
        if (!empty($opening->multimediaId())) {
            try {
                $multimedia = $this->queryMultimediaOpeningClient->findMultimediaById($opening->multimediaId());

                return $this->multimediaTypeOrchestratorHandler->handler($multimedia);
            } catch (OrchestratorTypeNotExistException|InvalidBodyException $e) {
                $this->logger->warning($e->getMessage());
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetaImage(NewsBase $editorial): array
    {
        $result = [];
        if (!empty($editorial->metaImage())) {
            try {
                $multimedia = $this->queryMultimediaOpeningClient->findMultimediaById($editorial->metaImage());
                if (!$multimedia instanceof MultimediaPhoto) {
                    return [];
                }
                $resource = $this->queryMultimediaOpeningClient->findPhotoById($multimedia->resourceId());
                $result[$editorial->metaImage()] = [
                    'resource' => $resource,
                    'opening' => $multimedia,
                ];
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to resolve meta image', [
                    'editorialId' => $editorial->id()->id(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function getMultimediaId(Multimedia $multimedia): ?MultimediaId
    {
        $multimediaId = null;
        if ($multimedia instanceof PhotoExist) {
            $multimediaId = $multimedia->id();
        }

        if (
            ($multimedia instanceof Video || $multimedia instanceof Widget)
            && ($multimedia->photo() instanceof PhotoExist)
        ) {
            $multimediaId = $multimedia->photo()->id();
        }

        return $multimediaId;
    }

    /**
     * @param array<string, mixed> $promises
     *
     * @return array<string, \Ec\Multimedia\Domain\Model\Multimedia>
     */
    public function filterFulfilled(array $promises): array
    {
        $result = [];
        foreach ($promises as $promise) {
            if (Promise::FULFILLED === $promise['state']) {
                $multimedia = $promise['value'];
                $result[$multimedia->id()] = $multimedia;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> ...$parameters
     */
    private function createCallback(callable $callable, ...$parameters): \Closure
    {
        return static function ($element) use ($callable, $parameters) {
            return $callable($element, ...$parameters);
        };
    }
}
