<?php

namespace App\Http\Controllers;

use App\Ai\Agents\InternalDocsAgent;
use App\Enums\DemoMode;
use App\Http\Requests\AskAssistantRequest;
use App\Support\OutputUrlPolicy;
use App\Support\SecurityTrace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocsAssistantController extends Controller
{
    /**
     * Show the internal documentation assistant (Demo 2).
     */
    public function show(Request $request): View
    {
        return view('demo.docs', [
            'mode' => $request->enum('mode', DemoMode::class) ?? DemoMode::Vulnerable,
            'message' => 'How do I deploy the API?',
        ]);
    }

    /**
     * Ask the documentation assistant a question. Same agent, same tools, same prompt in both modes:
     * only the rendering of the answer changes.
     */
    public function ask(AskAssistantRequest $request): View
    {
        $mode = $request->enum('mode', DemoMode::class);
        $trace = new SecurityTrace;

        $response = (new InternalDocsAgent($request->user(), $trace))->prompt($request->validated('message'));

        return view('demo.docs', [
            'mode' => $mode,
            'message' => $request->validated('message'),
            'markdown' => $response->text,
            'answer' => $this->render($response->text, $mode, $trace),
            'trace' => $trace,
        ]);
    }

    /**
     * Turn the model's Markdown into HTML.
     */
    private function render(string $markdown, DemoMode $mode, SecurityTrace $trace): string
    {
        if ($mode === DemoMode::Vulnerable) {
            // ⚠️ VULNERABLE ON PURPOSE: the model's output is trusted as HTML.
            $trace->step('Output', 'rendered as trusted HTML, links stay clickable', 'danger');

            return Str::markdown($markdown);
        }

        $trace->step('Output', 'HTML escaped, every URL checked by OutputUrlPolicy', 'ok');

        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false], [
            new OutputUrlPolicy($trace),
        ]);
    }
}
