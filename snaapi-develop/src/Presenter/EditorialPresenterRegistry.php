<?php

declare(strict_types=1);

namespace App\Presenter;

use App\Aggregator\DTO\ResolvedEditorial;

class EditorialPresenterRegistry
{
    /** @var EditorialPresenterInterface[] */
    private array $presenters = [];

    public function addPresenter(EditorialPresenterInterface $presenter): void
    {
        $this->presenters[] = $presenter;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(ResolvedEditorial $resolved, string $format): array
    {
        foreach ($this->presenters as $presenter) {
            if ($presenter->supports($format)) {
                return $presenter->present($resolved);
            }
        }

        throw new \InvalidArgumentException(sprintf('No presenter found for format "%s"', $format));
    }
}
