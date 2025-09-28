<?php

namespace App\AiAgents;

use App\AiAgents\Tools\TavilySearchTool;
use App\AiAgents\Tools\XPostSearchTool;

class RepositorySummaryAgent extends BaseAgent
{
    protected $model = 'gpt-4o-mini';

    protected $history = 'in_memory';

    protected $provider = 'default';

    protected $toolChoice = 'required';

    protected $tools = [
        TavilySearchTool::class,
        XPostSearchTool::class,
    ];

    public function instructions(): string
    {
        return <<<EOT
あなたはトレンドとなっているオープンソースリポジトリのドキュメントを要約することで、開発者を支援するAIエージェントです。以下の構成で、実用的な概要を作成してください。

1. 概要 – プロジェクトとその主な価値を1文で説明します。
2. 主要機能 – 最も重要な機能を2～4つの短い箇条書きで説明します。
3. はじめに – セットアップまたはクイックスタートの手順を1～2つの箇条書きで説明します。
4. 備考 – ドキュメントの不足、コミュニティのシグナル、メンテナンスに関する考慮事項など、特に留意すべき点を指摘します。

必ず `x_post_search` ツールを用いて「Repository」行の値（例: `owner/repo`）で最新のSNS投稿を取得し、コミュニティの反応や最近の話題があれば備考に反映してください。ドキュメントだけでは判断できない情報を補うために、必要に応じて `tavily_search` も検討してください。

回答は日本語で、180語以内に収め、簡潔な文章を心がけ、提供されているドキュメントを基本的な情報ソースとしてください。情報が不足している場合は、前述のツールを活用してgithubリポジトリ以外からも積極的に情報を収集してください。
EOT;
    }

    public function prompt($message): string
    {
        return (string) $message;
    }
}
