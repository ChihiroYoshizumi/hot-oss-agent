<?php

use App\Console\Commands\SummarizeTrendingRepositories;
use App\DataObjects\TrendingRepository;
use App\Services\OssInsightClient;
use App\Services\RepositoryDocumentationFetcher;
use App\Services\RepositorySummarizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use function Pest\Laravel\artisan;

beforeEach(function () {
    Log::spy();
});

afterEach(function () {
    \Mockery::close();
});

function makeRepository(array $overrides = []): TrendingRepository
{
    $row = array_merge([
        'repo_name' => 'laravel/framework',
        'description' => 'A PHP framework for web artisans.',
        'primary_language' => 'PHP',
        'stars' => 42,
        'forks' => 7,
        'pull_requests' => 3,
        'pushes' => 5,
        'total_score' => 123.45,
        'contributor_logins' => 'taylorotwell,driesvints',
        'collection_names' => 'Frameworks',
    ], $overrides);

    $repository = TrendingRepository::fromApiRow($row);

    if (! $repository) {
        throw new \RuntimeException('Failed to create repository fixture.');
    }

    return $repository;
}

function bindMocks(): array
{
    $ossClient = \Mockery::mock(OssInsightClient::class);
    $fetcher = \Mockery::mock(RepositoryDocumentationFetcher::class);
    $summarizer = \Mockery::mock(RepositorySummarizer::class);

    app()->instance(OssInsightClient::class, $ossClient);
    app()->instance(RepositoryDocumentationFetcher::class, $fetcher);
    app()->instance(RepositorySummarizer::class, $summarizer);

    return [$ossClient, $fetcher, $summarizer];
}

function runCommand(array $options = []): PendingCommand
{
    return artisan('oss:trending-summaries', $options);
}

test('summarizes trending repositories with documentation excerpts', function () {
    [$ossClient, $fetcher, $summarizer] = bindMocks();

    $repository = makeRepository();
    $documents = ['README.md' => '# Intro'];
    $summaryText = "概要\n- 重要な点";

    $ossClient->shouldReceive('fetchTrendingRepositories')
        ->once()
        ->with('past_week', 'TypeScript', 1)
        ->andReturn([$repository]);

    $fetcher->shouldReceive('fetchDocumentation')
        ->once()
        ->with('laravel', 'framework')
        ->andReturn($documents);

    $summarizer->shouldReceive('summarize')
        ->once()
        ->with($repository, $documents)
        ->andReturn($summaryText);

    runCommand([
        '--period' => 'past_week',
        '--language' => 'TypeScript',
        '--limit' => 1,
    ])
        ->expectsOutputToContain('laravel/framework')
        ->expectsOutputToContain('主言語')
        ->expectsOutputToContain($summaryText)
        ->assertExitCode(0);
});

test('prints info when no repositories are returned', function () {
    [$ossClient, $fetcher, $summarizer] = bindMocks();

    $ossClient->shouldReceive('fetchTrendingRepositories')
        ->once()
        ->with('past_24_hours', 'All', 3)
        ->andReturn([]);

    $fetcher->shouldNotReceive('fetchDocumentation');
    $summarizer->shouldNotReceive('summarize');

    runCommand()
        ->expectsOutputToContain('該当するリポジトリは見つかりませんでした。')
        ->assertExitCode(0);
});

test('warns when documentation fetch fails but still summarizes', function () {
    [$ossClient, $fetcher, $summarizer] = bindMocks();

    $repository = makeRepository([
        'repo_name' => 'hot-oss/agent',
        'primary_language' => 'TypeScript',
    ]);

    $ossClient->shouldReceive('fetchTrendingRepositories')
        ->once()
        ->with('past_24_hours', 'All', 3)
        ->andReturn([$repository]);

    $fetcher->shouldReceive('fetchDocumentation')
        ->once()
        ->andThrow(new \RuntimeException('GitHub API error.'));

    $summarizer->shouldReceive('summarize')
        ->once()
        ->with($repository, [])
        ->andReturn('summary');

    runCommand()
        ->expectsOutputToContain('ドキュメントの取得に失敗しました。')
        ->expectsOutputToContain('summary')
        ->assertExitCode(0);
});

test('returns invalid exit code for unsupported period', function () {
    runCommand(['--period' => 'unknown'])
        ->assertExitCode(SummarizeTrendingRepositories::INVALID);
});

test('returns invalid exit code when limit is out of range', function () {
    runCommand(['--limit' => 0])
        ->assertExitCode(SummarizeTrendingRepositories::INVALID);
});

test('returns failure when OSS Insight API call fails', function () {
    [$ossClient] = bindMocks();

    $ossClient->shouldReceive('fetchTrendingRepositories')
        ->once()
        ->with('past_24_hours', 'All', 3)
        ->andThrow(new \RuntimeException('OSS Insight failure.'));

    runCommand()
        ->expectsOutputToContain('OSS Insight APIの呼び出しに失敗しました。')
        ->assertExitCode(SummarizeTrendingRepositories::FAILURE);
});
