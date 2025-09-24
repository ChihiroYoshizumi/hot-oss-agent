<?php

namespace App\AiAgents\Tools;

use Illuminate\Support\Facades\Http;
use LarAgent\Tool;

class TavilySearchTool extends Tool
{
    protected string $name = 'tavily_search';

    protected string $description = 'Search the web via Tavily and return concise repository context.';

    protected array $properties = [
        'query' => [
            'type' => 'string',
            'description' => 'Search query to execute with Tavily.',
        ],
        'search_depth' => [
            'type' => 'string',
            'description' => 'Preferred search depth.',
            'enum' => ['basic', 'advanced'],
        ],
        'topic' => [
            'type' => 'string',
            'description' => 'Optional search topic to refine the results.',
            'enum' => ['general', 'news', 'finance'],
        ],
        'max_results' => [
            'type' => 'integer',
            'description' => 'Maximum number of results to return (0-20).',
        ],
        'include_answer' => [
            'type' => 'string',
            'description' => 'Include a Tavily generated answer.',
            'enum' => ['false', 'true', 'basic', 'advanced'],
        ],
    ];

    protected array $required = ['query'];

    public function execute(array $input): mixed
    {
        $apiKey = config('services.tavily.api_key');

        if (empty($apiKey)) {
            return 'Tavily search failed: TAVILY_API_KEY is not configured.';
        }

        $payload = $this->buildPayload($input);

        if ($payload['query'] === '') {
            return 'Tavily search failed: query parameter is required.';
        }

        $baseUrl = rtrim(config('services.tavily.base_url', 'https://api.tavily.com'), '/');
        $timeout = (int) config('services.tavily.timeout', 15);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/json',
            ])->timeout($timeout)->post($baseUrl.'/search', $payload);
        } catch (\Throwable $exception) {
            return 'Tavily search failed: '.$exception->getMessage();
        }

        if (! $response->successful()) {
            $error = $response->json('error') ?? $response->body();

            if (is_array($error)) {
                $error = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return 'Tavily search failed: '.$error;
        }

        $data = $response->json();

        $results = collect($data['results'] ?? [])
            ->map(function ($result) {
                return array_filter([
                    'title' => $result['title'] ?? null,
                    'url' => $result['url'] ?? null,
                    'content' => $result['content'] ?? null,
                ], static fn ($value) => $value !== null && $value !== '');
            })
            ->filter()
            ->values()
            ->all();

        $output = array_filter([
            'query' => $data['query'] ?? $payload['query'],
            'answer' => $data['answer'] ?? null,
            'results' => $results,
            'response_time' => $data['response_time'] ?? null,
        ], static fn ($value) => $value !== null && $value !== []);

        if (empty($output)) {
            return 'Tavily search completed with no results.';
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function buildPayload(array $input): array
    {
        $payload = [
            'query' => isset($input['query']) ? trim((string) $input['query']) : '',
        ];

        if (! empty($input['search_depth']) && in_array($input['search_depth'], ['basic', 'advanced'], true)) {
            $payload['search_depth'] = $input['search_depth'];
        }

        if (! empty($input['topic']) && in_array($input['topic'], ['general', 'news', 'finance'], true)) {
            $payload['topic'] = $input['topic'];
        }

        if (array_key_exists('max_results', $input) && $input['max_results'] !== null) {
            $payload['max_results'] = max(0, min((int) $input['max_results'], 20));
        }

        if (array_key_exists('include_answer', $input) && $input['include_answer'] !== null) {
            $normalized = $this->normalizeIncludeAnswer($input['include_answer']);

            if ($normalized !== null) {
                $payload['include_answer'] = $normalized;
            }
        }

        return $payload;
    }

    protected function normalizeIncludeAnswer(mixed $value): string|bool|null
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['true', 'false', 'basic', 'advanced'], true)
                ? match ($normalized) {
                    'true' => true,
                    'false' => false,
                    default => $normalized,
                }
                : null;
        }

        return null;
    }
}
