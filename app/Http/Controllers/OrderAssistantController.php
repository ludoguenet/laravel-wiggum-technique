<?php

namespace App\Http\Controllers;

use App\Ai\Agents\OrderAssistant;
use App\Enums\DemoMode;
use App\Http\Requests\AskAssistantRequest;
use App\Support\SecurityTrace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderAssistantController extends Controller
{
    /**
     * Show the orders assistant (Demo 1).
     */
    public function show(Request $request): View
    {
        return view('demo.orders', [
            'mode' => $request->enum('mode', DemoMode::class) ?? DemoMode::Vulnerable,
            'message' => 'Show me my latest orders.',
        ]);
    }

    /**
     * Ask the orders assistant a question.
     */
    public function ask(AskAssistantRequest $request): View
    {
        $mode = $request->enum('mode', DemoMode::class);
        $trace = new SecurityTrace;

        $response = (new OrderAssistant($request->user(), $mode, $trace))->prompt($request->validated('message'));

        return view('demo.orders', [
            'mode' => $mode,
            'message' => $request->validated('message'),
            'markdown' => $response->text,
            'answer' => Str::markdown($response->text, ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'trace' => $trace,
        ]);
    }
}
