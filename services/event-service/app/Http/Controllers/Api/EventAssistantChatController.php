<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EventAssistantChatService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventAssistantChatController extends Controller
{
    public function __construct(
        private readonly EventAssistantChatService $chatService,
    ) {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1000'],
            'conversation_id' => ['nullable', 'string', 'max:36'],
        ]);

        return $this->chatService->stream(
            message: $validated['message'],
            token: $request->bearerToken(),
            conversationId: $validated['conversation_id'] ?? null,
        );
    }
}
