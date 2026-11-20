<?php

declare(strict_types=1);

namespace PhpGraph\Builder;

use PhpGraph\Values;

/**
 * The Laravel facades of the project (LaravelFacades): how many lead to a class.
 */
final readonly class FacadeStats
{
    /**
     * @param int          $facades         facades declared by the project
     * @param int          $followed        those whose class is known: their static calls go to it
     * @param int          $unresolvedCount those whose key is bound nowhere, by code not read, or to two classes
     * @param list<string> $unresolved      the first of them, with why
     */
    public function __construct(
        public int $facades = 0,
        public int $followed = 0,
        public int $unresolvedCount = 0,
        public array $unresolved = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->facades === 0;
    }

    /**
     * @param array<mixed> $data as written by toArray()
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Values::number($data['facades'] ?? 0),
            Values::number($data['followed'] ?? 0),
            Values::number($data['unresolvedCount'] ?? 0),
            array_values(array_filter(\is_array($data['unresolved'] ?? null) ? $data['unresolved'] : [], 'is_string')),
        );
    }

    /**
     * @return array{facades: int, followed: int, unresolvedCount: int, unresolved: list<string>}
     */
    public function toArray(): array
    {
        return [
            'facades' => $this->facades,
            'followed' => $this->followed,
            'unresolvedCount' => $this->unresolvedCount,
            'unresolved' => $this->unresolved,
        ];
    }
}
