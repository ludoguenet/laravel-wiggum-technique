<?php

namespace App\Ai\Tools;

use App\Models\Document;
use App\Models\User;
use App\Support\SecurityTrace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Read-only: searches the documents the current user is allowed to read.
 */
class SearchDocuments implements Tool
{
    public function __construct(
        private User $user,
        private SecurityTrace $trace,
    ) {}

    public function name(): string
    {
        return 'search_documents';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the internal engineering documentation with keywords. Returns ids, titles and excerpts.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $keywords = Str::of($request->string('query'))->lower()->explode(' ')->filter(fn (string $word) => mb_strlen($word) >= 3);

        $documents = Document::query()
            ->visibleTo($this->user)
            ->where(function (Builder $query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'like', "%{$keyword}%")->orWhere('body', 'like', "%{$keyword}%");
                }
            })
            ->limit(3)
            ->get();

        $this->trace->toolCall($this->name(), $request->all(), $documents->map(fn (Document $document) => "{$document->slug}.md")->join(', ') ?: 'no result');

        return $documents
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'excerpt' => Str::limit($document->body, 200),
            ])
            ->toJson();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Keywords to look for, e.g. "deploy API".')->required(),
        ];
    }
}
