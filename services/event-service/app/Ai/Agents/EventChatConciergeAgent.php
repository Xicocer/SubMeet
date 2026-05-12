<?php

namespace App\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class EventChatConciergeAgent implements Agent, Conversational
{
    use Promptable;
    use RemembersConversations;

    /**
     * @var array<string, mixed>
     */
    private array $requestContext = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function withRequestContext(array $context): static
    {
        $this->requestContext = $context;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $instructions = <<<'PROMPT'
You are the AI concierge for a Russian event discovery platform.

Rules:
- Answer in Russian.
- Be warm, natural, and concise.
- Help the user choose events based only on the provided candidates and the existing conversation.
- Never invent events, prices, dates, halls, or cities.
- If something is unknown, say that honestly.
- When you recommend several events, explain briefly why each one fits.
- Keep the answer focused on helping the user decide what to attend.
PROMPT;

        if ($this->requestContext !== []) {
            $instructions .= "\n\nCurrent recommendation context (hidden from the user):\n";
            $instructions .= json_encode($this->requestContext, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return $instructions;
    }
}
