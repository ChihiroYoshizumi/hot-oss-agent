<?php

namespace App\AiAgents\Tools;

use Illuminate\Support\Facades\Http;
use LarAgent\Tool;

class XPostSearchTool extends Tool
{
    protected string $name = 'x_post_search';

    protected string $description = 'Search posts on X (formerly Twitter) related to a given OSS repository.';

    protected array $properties = [
        'query' => [
            'type' => 'string',
            'description' => 'Search terms to locate OSS-related posts (e.g. owner/repo or project name).',
        ],
        'language' => [
            'type' => 'string',
            'description' => 'Optional ISO 639-1 code to bias results (adds lang:code to the query).',
        ],
        'max_results' => [
            'type' => 'integer',
            'description' => 'Maximum number of posts to return (10-100).',
        ],
        'since_id' => [
            'type' => 'string',
            'description' => 'Return only posts published after the given tweet ID.',
        ],
    ];

    protected array $required = ['query'];

    public function execute(array $input): mixed
    {
        $query = isset($input['query']) ? trim((string) $input['query']) : '';

        if ($query === '') {
            return 'X search failed: query parameter is required.';
        }

        $credentials = $this->credentials();

        if ($credentials['api_key'] === null || $credentials['api_secret'] === null) {
            return 'X search failed: X_API_KEY or X_API_SECRET is not configured.';
        }

        $auth = $this->authenticate($credentials);

        if ($auth['error'] !== null) {
            return 'X search failed: '.$auth['error'];
        }

        $parameters = $this->buildQueryParameters($query, $input, $credentials['default_max_results']);

        $searchUrl = $credentials['base_url'].'/tweets/search/recent';

        try {
            $response = Http::withToken($auth['token'])
                ->timeout($credentials['timeout'])
                ->get($searchUrl, $parameters);
        } catch (\Throwable $exception) {
            return 'X search failed: '.$exception->getMessage();
        }

        if (! $response->successful()) {
            $error = $response->json('error') ?? $response->json('detail') ?? $response->body();

            if (is_array($error)) {
                $error = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return 'X search failed: '.$error;
        }

        $payload = $response->json();

        $users = collect($payload['includes']['users'] ?? [])
            ->filter(static fn ($user) => isset($user['id']))
            ->keyBy(static fn ($user) => $user['id']);

        $results = collect($payload['data'] ?? [])
            ->map(function (array $tweet) use ($users) {
                $author = $users->get($tweet['author_id'] ?? null);
                $username = $author['username'] ?? null;

                return array_filter([
                    'id' => $tweet['id'] ?? null,
                    'text' => $tweet['text'] ?? null,
                    'created_at' => $tweet['created_at'] ?? null,
                    'lang' => $tweet['lang'] ?? null,
                    'metrics' => isset($tweet['public_metrics']) ? array_filter([
                        'retweet_count' => $tweet['public_metrics']['retweet_count'] ?? null,
                        'reply_count' => $tweet['public_metrics']['reply_count'] ?? null,
                        'like_count' => $tweet['public_metrics']['like_count'] ?? null,
                        'quote_count' => $tweet['public_metrics']['quote_count'] ?? null,
                    ]) : null,
                    'author' => $author ? array_filter([
                        'id' => $author['id'] ?? null,
                        'name' => $author['name'] ?? null,
                        'username' => $username,
                        'profile_image_url' => $author['profile_image_url'] ?? null,
                    ]) : null,
                    'url' => ($username && isset($tweet['id'])) ? sprintf('https://x.com/%s/status/%s', $username, $tweet['id']) : null,
                ], static fn ($value) => $value !== null && $value !== [] && $value !== '');
            })
            ->filter()
            ->values()
            ->all();

        if ($results === []) {
            return 'X search completed with no results.';
        }

        $output = array_filter([
            'query' => $payload['meta']['query'] ?? $parameters['query'],
            'result_count' => $payload['meta']['result_count'] ?? count($results),
            'newest_id' => $payload['meta']['newest_id'] ?? null,
            'oldest_id' => $payload['meta']['oldest_id'] ?? null,
            'next_token' => $payload['meta']['next_token'] ?? null,
            'results' => $results,
        ], static fn ($value) => $value !== null && $value !== [] && $value !== '');

        return json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function credentials(): array
    {
        return [
            'api_key' => config('services.x.api_key'),
            'api_secret' => config('services.x.api_secret'),
            'auth_url' => rtrim(config('services.x.auth_url', 'https://api.x.com/oauth2/token'), '/'),
            'base_url' => rtrim(config('services.x.base_url', 'https://api.x.com/2'), '/'),
            'timeout' => (int) config('services.x.timeout', 15),
            'default_max_results' => (int) config('services.x.default_max_results', 20),
        ];
    }

    private function authenticate(array $credentials): array
    {
        try {
            $response = Http::withBasicAuth($credentials['api_key'], $credentials['api_secret'])
                ->asForm()
                ->timeout($credentials['timeout'])
                ->post($credentials['auth_url'], [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (\Throwable $exception) {
            return ['token' => null, 'error' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            $error = $response->json('error') ?? $response->json('detail') ?? $response->body();

            if (is_array($error)) {
                $error = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return ['token' => null, 'error' => $error];
        }

        $tokenType = strtolower((string) $response->json('token_type', ''));
        $accessToken = $response->json('access_token');

        if ($tokenType !== 'bearer' || ! is_string($accessToken) || $accessToken === '') {
            return ['token' => null, 'error' => 'Invalid bearer token response.'];
        }

        return ['token' => $accessToken, 'error' => null];
    }

    private function buildQueryParameters(string $query, array $input, int $defaultMaxResults): array
    {
        $searchQuery = $query;

        if (! empty($input['language']) && is_string($input['language'])) {
            $language = strtolower(trim($input['language']));

            if ($language !== '') {
                $searchQuery .= ' lang:'.$language;
            }
        }

        $maxResults = $this->normalizeMaxResults($input['max_results'] ?? null, $defaultMaxResults);

        $parameters = [
            'query' => $searchQuery,
            'max_results' => $maxResults,
            'tweet.fields' => 'created_at,lang,public_metrics',
            'expansions' => 'author_id',
            'user.fields' => 'name,username,profile_image_url',
        ];

        if (! empty($input['since_id'])) {
            $parameters['since_id'] = trim((string) $input['since_id']);
        }

        return $parameters;
    }

    private function normalizeMaxResults(mixed $value, int $default): int
    {
        if ($value === null) {
            return $this->clampMaxResults($default);
        }

        return $this->clampMaxResults((int) $value);
    }

    private function clampMaxResults(int $value): int
    {
        return max(10, min($value, 100));
    }
}
