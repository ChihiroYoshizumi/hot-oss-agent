<?php

namespace App\AiAgents;

use Illuminate\Support\Facades\Log;
use LarAgent\Agent as LarAgentAgent;
use LarAgent\Core\Contracts\Message as MessageInterface;
use LarAgent\Core\Contracts\Tool as ToolInterface;
use LarAgent\Messages\ToolCallMessage;
use LarAgent\ToolCall;

abstract class BaseAgent extends LarAgentAgent
{
    protected function afterResponse(MessageInterface $message)
    {
        $metadata = $message->getMetadata();

        if (isset($metadata['usage']) && is_array($metadata['usage'])) {
            $this->logTokenUsage($metadata['usage']);
        }

        if ($message instanceof ToolCallMessage) {
            foreach ($message->getToolCalls() as $toolCall) {
                if ($toolCall instanceof ToolCall) {
                    $this->logToolCall($toolCall);
                }
            }
        }

        return parent::afterResponse($message);
    }

    protected function afterToolExecution(ToolInterface $tool, &$result)
    {
        $this->logToolExecution($tool, $result);

        return parent::afterToolExecution($tool, $result);
    }

    private function logTokenUsage(array $usage): void
    {
        Log::info('agent_usage', [
            'agent' => class_basename(static::class),
            'model' => $this->model(),
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
        ]);
    }

    private function logToolCall(ToolCall $toolCall): void
    {
        $arguments = $this->decodeArguments($toolCall->getArguments());

        Log::info('agent_tool_call', [
            'agent' => class_basename(static::class),
            'tool' => $toolCall->getToolName(),
            'arguments' => $arguments,
        ]);
    }

    private function logToolExecution(ToolInterface $tool, mixed $result): void
    {
        Log::info('agent_tool_result', [
            'agent' => class_basename(static::class),
            'tool' => $tool->getName(),
            'result' => $this->stringifyResult($result),
        ]);
    }

    private function decodeArguments(string $arguments): mixed
    {
        $decoded = json_decode($arguments, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $arguments;
    }

    private function stringifyResult(mixed $result): mixed
    {
        if (is_null($result) || is_scalar($result)) {
            return $result;
        }

        if ($result instanceof \Stringable) {
            return (string) $result;
        }

        try {
            $encoded = json_encode($result, JSON_THROW_ON_ERROR);

            if ($encoded !== false) {
                return $encoded;
            }
        } catch (\Throwable) {
            // Fallback below
        }

        return get_debug_type($result);
    }
}
