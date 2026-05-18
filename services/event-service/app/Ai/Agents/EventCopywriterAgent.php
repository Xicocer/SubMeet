<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class EventCopywriterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You are an event marketplace copywriter.

Rules:
- Write in Russian.
- Rewrite the event description for a public ticket card.
- Make it clear, attractive, specific and easy to read for a potential buyer.
- Do not invent dates, halls, addresses, prices, artists, partners or facts that were not provided.
- If the original text is weak or short, improve it using only the title, category, age rating and tags.
- Avoid hype, fake guarantees and excessive marketing cliches.
- Keep the description between 350 and 850 characters.
- Return only structured data according to the schema.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->required(),
            'tips' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(3)
                ->required(),
        ];
    }
}
