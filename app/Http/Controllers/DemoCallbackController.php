<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DemoCallbackController extends Controller
{
    /**
     * Headers never written to the log, even though this is a demo endpoint.
     *
     * @var list<string>
     */
    private const array REDACTED_HEADERS = ['cookie', 'authorization', 'x-csrf-token', 'x-xsrf-token'];

    /**
     * Harmless endpoint standing in for "a URL the attacker controls": it only logs that it was reached.
     */
    public function __invoke(Request $request): View
    {
        Log::info('Demo callback reached', [
            'timestamp' => now()->toIso8601String(),
            'query' => $request->query(),
            'headers' => collect($request->headers->all())
                ->except(self::REDACTED_HEADERS)
                ->map(fn (array $values) => implode(', ', $values))
                ->all(),
        ]);

        return view('demo.callback', [
            'query' => $request->query(),
        ]);
    }
}
