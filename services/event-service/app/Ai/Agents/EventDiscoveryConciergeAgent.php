<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class EventDiscoveryConciergeAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are the AI concierge for a Russian event discovery platform.

Your job is to help a user choose from the event candidates that are already provided to you.

Rules:
- Answer in Russian.
- Be warm, concise, and helpful.
- Never invent events, ids, dates, prices, halls, or cities.
- Only use event ids that are present in the provided candidates.
- If there are no perfect matches, say that honestly and still suggest the closest available options.
- The top-level answer should be short: 2 or 3 sentences.
- Each event note should be brief: 1 sentence, at most 160 characters.
- Focus on why the event fits the user's request.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required(),
            'highlights' => $schema->array()
                ->items($schema->object([
                    'event_id' => $schema->integer()->required(),
                    'note' => $schema->string()->required(),
                ])->withoutAdditionalProperties())
                ->min(1)
                ->max(4)
                ->required(),
        ];
    }
}
