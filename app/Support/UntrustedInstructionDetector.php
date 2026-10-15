<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Naive heuristic used only to label documents in the security trace.
 *
 * This is NOT a defense: an attacker can always phrase an instruction differently.
 */
class UntrustedInstructionDetector
{
    /**
     * @var list<string>
     */
    private const array MARKERS = [
        'ai assistant',
        'ai assistants',
        'language model',
        'ignore previous',
        'ignore the user',
    ];

    public static function containsInstructionForAi(string $content): bool
    {
        return Str::contains($content, self::MARKERS, ignoreCase: true);
    }
}
