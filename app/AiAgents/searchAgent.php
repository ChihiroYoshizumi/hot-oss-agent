<?php

namespace App\AiAgents;

use LarAgent\Agent;

class searchAgent extends Agent
{
    protected $model = 'gpt-5-nano';

    protected $history = 'in_memory';

    protected $provider = 'default';

    protected $tools = [];

    public function instructions()
    {
        return <<<EOT
            
        EOT;
    }

    public function prompt($message)
    {
        return $message;
    }
}
