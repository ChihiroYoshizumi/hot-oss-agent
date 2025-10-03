<?php

namespace App\Console\Commands;

use App\DataObjects\TrendingRepository;
use App\Services\OssInsightClient;
use App\Services\RepositoryDocumentationFetcher;
use App\Services\RepositorySummarizer;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class SummarizeTrendingRepositories extends Command
{
    private const SUPPORTED_PERIODS = [
        'past_24_hours',
        'past_week',
        'past_month',
        'past_3_months',
    ];

    private const MAX_LIMIT = 5;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oss:trending-summaries
                            {--period=past_24_hours : 集計期間 (past_24_hours|past_week|past_month|past_3_months)}
                            {--language=All : 対象言語：Possible values: [All, JavaScript, Java, Python, PHP, C++, C#, TypeScript, Shell, C, Ruby, Rust, Go, Kotlin, HCL, PowerShell, CMake, Groovy, PLpgSQL, TSQL, Dart, Swift, HTML, CSS, Elixir, Haskell, Solidity, Assembly, R, Scala, Julia, Lua, Clojure, Erlang, Common Lisp, Emacs Lisp, OCaml, MATLAB, Objective-C, Perl, Fortran]}
                            {--limit=3 : 取得するリポジトリ数 (1-'.self::MAX_LIMIT.')}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'OSS Insight APIから盛り上がっているOSSを取得し、ドキュメントを要約する';

    public function __construct(
        private readonly OssInsightClient $ossInsightClient,
        private readonly RepositoryDocumentationFetcher $documentationFetcher,
        private readonly RepositorySummarizer $summarizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = (string) $this->option('period');
        $language = (string) $this->option('language');
        $limit = (int) $this->option('limit');

        if (! in_array($period, self::SUPPORTED_PERIODS, true)) {
            $this->components->error('集計期間の指定が不正です。');

            return self::INVALID;
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            $this->components->error('取得件数は1〜'.self::MAX_LIMIT.'の範囲で指定してください。');

            return self::INVALID;
        }

        try {
            $repositories = $this->ossInsightClient->fetchTrendingRepositories($period, $language, $limit);
        } catch (RuntimeException $exception) {
            report($exception);
            $this->components->error('OSS Insight APIの呼び出しに失敗しました。');

            return self::FAILURE;
        }

        if ($repositories === []) {
            $this->components->info('該当するリポジトリは見つかりませんでした。');

            return self::SUCCESS;
        }

        foreach ($repositories as $repository) {
            $this->outputRepositorySummary($repository);
        }

        return self::SUCCESS;
    }

    private function outputRepositorySummary(TrendingRepository $repository): void
    {
        $this->newLine();
        $this->components->info($repository->fullName());

        $metadata = array_filter([
            '主言語' => $repository->primaryLanguage,
            'コレクション' => $repository->collections ? implode(', ', $repository->collections) : null,
            '期間内スター' => $repository->stars,
            '期間内フォーク' => $repository->forks,
            '期間内PR' => $repository->pullRequests,
            '期間内プッシュ' => $repository->pushes,
            'スコア' => $repository->totalScore,
        ], static fn ($value) => $value !== null && $value !== '');

        foreach ($metadata as $label => $value) {
            $this->components->twoColumnDetail($label, (string) $value);
        }

        try {
            $documents = $this->documentationFetcher->fetchDocumentation($repository->owner, $repository->name);
        } catch (RuntimeException $exception) {
            report($exception);
            $this->components->warn('ドキュメントの取得に失敗しました。リポジトリのメタ情報のみで要約を生成します。');
            $documents = [];
        }

        try {
            $summary = $this->summarizer->summarize($repository, $documents);
        } catch (Throwable $throwable) {
            report($throwable);
            $this->components->error('要約の生成に失敗しました。');

            return;
        }

        $this->newLine();
        $this->line($summary);
    }
}
