<?php

namespace App\AiAgents;

use LarAgent\Agent;

class RepositorySummaryAgent extends Agent
{
    protected $model = 'gpt-4o-mini';

    protected $history = 'in_memory';

    protected $provider = 'default';

    protected $tools = [];

    public function instructions(): string
    {
        return <<<EOT
You are assisting developers by summarizing documentation for trending open-source repositories. Produce an actionable brief with the following structure:

1. Overview – one sentence describing the project and its primary value.
2. Key Capabilities – two to four short bullet points highlighting the most important features.
3. Getting Started – one or two bullets outlining setup or quickstart steps.
4. Notes – call out notable docs gaps, community signals, or maintenance considerations.

Keep the response under 180 words, prefer concise sentences, and rely only on the supplied documentation and metrics. If information is missing, state that explicitly.
EOT;
    }

    public function prompt($message): string
    {
        return (string) $message;
    }
}
