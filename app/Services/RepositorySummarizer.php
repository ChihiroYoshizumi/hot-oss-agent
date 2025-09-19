<?php

namespace App\Services;

use App\AiAgents\RepositorySummaryAgent;
use App\DataObjects\TrendingRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RepositorySummarizer
{
    public function summarize(TrendingRepository $repository, array $documents): string
    {
        $agent = RepositorySummaryAgent::for($repository->fullName());

        $prompt = $this->buildPrompt($repository, $documents);

        $response = $agent->message($prompt)->respond();

        return is_string($response) ? trim($response) : trim((string) $response);
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
}
