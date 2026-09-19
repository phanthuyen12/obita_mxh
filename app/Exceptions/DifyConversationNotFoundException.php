<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when Dify returns 404 "Conversation Not Exists".
 * The caller should clear the stored dify_conversation_id and retry as a new conversation.
 */
class DifyConversationNotFoundException extends \RuntimeException {}
