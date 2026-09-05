<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Dify\DifyWorkflowClient;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;

class DifyTextProvider extends OpenAiCompatibleProvider
{
    public function __construct(
        array $config,
        Dispatcher $events,
        private readonly DifyWorkflowClient $workflowClient,
    ) {
        parent::__construct($config, $events);
    }

    #[\Override]
    public function prompt(AgentPrompt $prompt): AgentResponse
    {
        $schema = $prompt->agent instanceof HasStructuredOutput
            ? collect($prompt->agent->schema(new JsonSchemaTypeFactory))
                ->map(fn ($type): array => $type->toArray())
                ->all()
            : null;

        $outputs = $this->workflowClient->run([
            'task' => 'text_generation',
            'prompt' => $prompt->prompt,
            'instructions' => (string) $prompt->agent->instructions(),
            'schema' => $schema === null ? '' : json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], 'laravel-ai:text', (string) config('services.dify.text_api_key'));

        $usage = new Usage(
            promptTokens: (int) data_get($outputs, 'usage.prompt_tokens', 0),
            completionTokens: (int) data_get($outputs, 'usage.completion_tokens', 0),
        );
        $meta = new Meta('dify', (string) data_get($outputs, 'model', 'dify-workflow'));
        $invocationId = (string) Str::uuid7();

        if ($schema !== null) {
            $structured = $this->structuredOutput($outputs);

            return new StructuredAgentResponse(
                $invocationId,
                $structured,
                $this->textOutput($outputs, json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
                $usage,
                $meta,
            );
        }

        return new AgentResponse($invocationId, $this->textOutput($outputs), $usage, $meta);
    }

    #[\Override]
    public function defaultTextModel(): string
    {
        return 'dify-workflow';
    }

    /** @param array<string, mixed> $outputs */
    private function textOutput(array $outputs, string $fallback = ''): string
    {
        foreach (['text', 'content', 'answer', 'result', 'output'] as $key) {
            $value = data_get($outputs, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $outputs
     * @return array<string, mixed>
     */
    private function structuredOutput(array $outputs): array
    {
        foreach (['structured', 'result', 'output', 'data'] as $key) {
            $value = data_get($outputs, $key);

            if (is_array($value)) {
                return $value;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return collect($outputs)->except(['usage', 'model', 'text', 'content', 'answer'])->all();
    }
}
