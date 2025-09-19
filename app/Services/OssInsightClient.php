<?php

namespace App\Services;

use App\DataObjects\TrendingRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OssInsightClient
{
    private const BASE_URL = 'https://api.ossinsight.io/v1';
    private const ENDPOINT = '/trends/repos/';

    /**
     * @return array<int, TrendingRepository>
     */
    public function fetchTrendingRepositories(string $period, string $language, int $limit): array
    {
        $response = $this->http()->get(self::ENDPOINT, [
            'period' => $period,
            'language' => $language,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch trending repositories from OSS Insight.');
        }

        $rows = $response->json('data.rows', []);

        return Collection::wrap($rows)
            ->map(static function ($row): ?TrendingRepository {
                if (! is_array($row)) {
                    return null;
                }

                return TrendingRepository::fromApiRow($row);
            })
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 250);
    }
}
