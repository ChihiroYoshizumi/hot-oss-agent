<?php

namespace App\AiAgents;

class RepositorySummaryAgent extends BaseAgent
{
    protected $model = 'gpt-4o-mini';

    protected $history = 'in_memory';

    protected $provider = 'default';

    protected $tools = [];

    public function instructions(): string
    {
        return <<<EOT
あなたはトレンドとなっているオープンソースリポジトリのドキュメントを要約することで、開発者を支援するAIエージェントです。以下の構成で、実用的な概要を作成してください。

1. 概要 – プロジェクトとその主な価値を1文で説明します。
2. 主要機能 – 最も重要な機能を2～4つの短い箇条書きで説明します。
3. はじめに – セットアップまたはクイックスタートの手順を1～2つの箇条書きで説明します。
4. 備考 – ドキュメントの不足、コミュニティのシグナル、メンテナンスに関する考慮事項など、特に留意すべき点を指摘します。

回答は日本語で、180語以内に収め、簡潔な文章を心がけ、提供されているドキュメントと指標のみに依拠してください。情報が不足している場合は、その旨を明記してください。
EOT;
    }

    public function prompt($message): string
    {
        return (string) $message;
    }
}
