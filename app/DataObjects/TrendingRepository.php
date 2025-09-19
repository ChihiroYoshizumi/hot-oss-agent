<?php

namespace App\DataObjects;

use Illuminate\Support\Str;

final class TrendingRepository
{
    /**
     * @param  array<int, string>  $contributors
     * @param  array<int, string>  $collections
     */
    private function __construct(
        public readonly string $owner,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $primaryLanguage,
        public readonly ?int $stars,
        public readonly ?int $forks,
        public readonly ?int $pullRequests,
        public readonly ?int $pushes,
        public readonly ?float $totalScore,
        public readonly array $contributors,
        public readonly array $collections,
    ) {
    }

    public static function fromApiRow(array $row): ?self
    {
        $fullName = $row['repo_name'] ?? null;

        if (! is_string($fullName) || ! Str::contains($fullName, '/')) {
            return null;
        }

        [$owner, $name] = explode('/', $fullName, 2);

        return new self(
            owner: $owner,
            name: $name,
            description: self::stringOrNull($row['description'] ?? null),
            primaryLanguage: self::stringOrNull($row['primary_language'] ?? null),
            stars: self::intOrNull($row['stars'] ?? null),
            forks: self::intOrNull($row['forks'] ?? null),
            pullRequests: self::intOrNull($row['pull_requests'] ?? null),
            pushes: self::intOrNull($row['pushes'] ?? null),
            totalScore: self::floatOrNull($row['total_score'] ?? null),
            contributors: self::splitCsv($row['contributor_logins'] ?? null),
            collections: self::splitCsv($row['collection_names'] ?? null),
        );
    }

    public function fullName(): string
    {
        return $this->owner.'/'.$this->name;
    }

    private static function splitCsv(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(static fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
