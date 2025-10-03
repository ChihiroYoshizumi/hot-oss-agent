<?php

namespace App\Services;

use App\AiAgents\RepositorySummaryAgent;
use App\DataObjects\TrendingRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LarAgent\Core\Contracts\Message as AgentMessage;

class RepositorySummarizer
{
    public function summarize(TrendingRepository $repository, array $documents): string
    {
        $agent = RepositorySummaryAgent::for($repository->fullName());

        $agent->returnMessage();

        $prompt = $this->buildPrompt($repository, $documents);

        $response = $agent->message($prompt)->respond();

        return $this->normalizeResponse($response);
    }

    private function buildPrompt(TrendingRepository $repository, array $documents): string
    {
        $lines = [];

        $lines[] = 'Repository: '.$repository->fullName();

        if ($repository->description) {
            $lines[] = 'Description: '.$repository->description;
        }

        if ($repository->primaryLanguage) {
            $lines[] = 'Primary language: '.$repository->primaryLanguage;
        }

        $metrics = Arr::where([
            'Stars (period)' => $repository->stars,
            'Forks (period)' => $repository->forks,
            'Pull requests (period)' => $repository->pullRequests,
            'Pushes (period)' => $repository->pushes,
            'OSS Insight score' => $repository->totalScore,
        ], static fn ($value) => $value !== null);

        if (! empty($metrics)) {
            $lines[] = 'Recent metrics:';

            foreach ($metrics as $label => $value) {
                $lines[] = sprintf('- %s: %s', $label, $value);
            }
        }

        if (! empty($repository->contributors)) {
            $lines[] = 'Active contributors: '.implode(', ', $repository->contributors);
        }

        if (! empty($repository->collections)) {
            $lines[] = 'Collections: '.implode(', ', $repository->collections);
        }

        if (empty($documents)) {
            $lines[] = 'Documentation: No documentation files were retrieved.';
        } else {
            $lines[] = 'Documentation excerpts:';

            foreach ($documents as $path => $content) {
                $lines[] = '---';
                $lines[] = $path;
                $lines[] = $content;
            }
        }

        $lines[] = '---';
        $lines[] = 'Please provide a concise summary following the instructions.';

        return Str::of(implode("\n", $lines))->trim()->toString();
    }

    private function normalizeResponse(string|array|AgentMessage $response): string
    {
        if ($response instanceof AgentMessage) {
            return trim((string) $response);
        }

        if (is_string($response)) {
            return trim($response);
        }

        if (is_array($response)) {
            $content = $response['message']['content'] ?? $response['content'] ?? null;

            if ($content instanceof AgentMessage) {
                return trim((string) $content);
            }

            if (is_string($content)) {
                return trim($content);
            }

            if (is_array($content)) {
                $text = $this->extractTextFromArray($content);

                if ($text !== null) {
                    return $text;
                }
            }

            $encoded = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (is_string($encoded)) {
                return trim($encoded);
            }
        }

        return trim((string) $response);
    }

    private function extractTextFromArray(array $content): ?string
    {
        $segments = [];

        array_walk_recursive($content, static function ($value, $key) use (&$segments) {
            if (is_string($value) && in_array($key, ['text', 'content', 'value'], true)) {
                $segments[] = trim($value);
            }
        });

        $segments = array_values(array_filter($segments));

        if (empty($segments)) {
            return null;
        }

        return trim(implode("\n\n", array_unique($segments)));
    }
}
