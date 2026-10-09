<?php

namespace App\Ai\Tools;

use App\Models\Document;
use App\Models\User;
use App\Support\SecurityTrace;
use App\Support\UntrustedInstructionDetector;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: returns one document, only if DocumentPolicy allows the current user to view it.
 */
class GetDocument implements Tool
{
    public function __construct(
        private User $user,
        private SecurityTrace $trace,
    ) {}

    public function name(): string
    {
        return 'get_document';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Read the full content of one internal document, given its id.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $document = Document::find($request['id']);

        if ($document === null || Gate::forUser($this->user)->denies('view', $document)) {
            $this->trace->toolCall($this->name(), $request->all(), 'DENIED');

            return 'Document not found or access denied.';
        }

        $this->trace->toolCall($this->name(), $request->all(), "{$document->slug}.md");
        $this->trace->step('Retrieved document', "{$document->slug}.md");

        if (UntrustedInstructionDetector::containsInstructionForAi($document->body)) {
            $this->trace->step('Document contains', 'UNTRUSTED AI INSTRUCTION', 'danger');
        }

        return $document->body;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The id of the document.')->required(),
        ];
    }
}
