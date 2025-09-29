<?php

use App\AiAgents\BaseAgent;
use Illuminate\Support\Facades\Log;
use LarAgent\Core\Abstractions\LlmDriver;
use LarAgent\Core\Contracts\ToolCall as ToolCallInterface;
use LarAgent\Messages\AssistantMessage;

beforeEach(function () {
    Log::spy();
});

class ThrowingDriver extends LlmDriver
{
    public function sendMessage(array $messages, array $options = []): AssistantMessage
    {
        throw new RuntimeException('LLM provider failure (test)');
    }

    public function sendMessageStreamed(array $messages, array $options = [], ?callable $callback = null): \Generator
    {
        throw new RuntimeException('LLM provider failure (stream)');
    }

    public function toolResultToMessage(ToolCallInterface $toolCall, mixed $result): array
    {
        return [];
    }

    public function toolCallsToMessage(array $toolCalls): array
    {
        return [];
    }
}

class ThrowingAgent extends BaseAgent
{
    protected $model = 'fake-model';

    protected $driver = ThrowingDriver::class;

    protected $history = 'in_memory';

    public function instructions(): string
    {
        return 'Test instructions';
    }

    public function prompt(string $message): string
    {
        return $message;
    }
}

it('logs engine errors when the LLM driver fails', function () {
    $agent = ThrowingAgent::for('testing');

    expect(fn () => $agent->respond('こんにちは'))->toThrow(RuntimeException::class, 'LLM provider failure (test)');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            expect($message)->toBe('agent_engine_error');
            expect($context['agent'])->toBe('ThrowingAgent');
            expect($context['provider'])->toBe('default');
            expect($context['model'])->toBe('fake-model');
            expect($context['message'])->toBe('LLM provider failure (test)');
            expect($context['exception'])->toBeInstanceOf(RuntimeException::class);

            return true;
        });
});
