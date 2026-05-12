<?php

namespace App\Ai;

class AuthConversationParticipant
{
    public function __construct(
        public int $id,
        public ?string $displayName = null,
    ) {
    }
}
