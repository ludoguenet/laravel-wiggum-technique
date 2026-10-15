<?php

use App\Ai\Agents\InternalDocsAgent;
use App\Ai\Tools\GetDocument;
use App\Ai\Tools\SearchDocuments;
use App\Models\Document;
use App\Models\User;
use App\Support\SecurityTrace;
use Database\Seeders\DocumentSeeder;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->seed(DocumentSeeder::class);

    $this->alice = User::factory()->create(['name' => 'Alice']);
});

test('Alice finds public documents with search_documents', function () {
    $tool = new SearchDocuments($this->alice, new SecurityTrace);

    expect($tool->handle(new Request(['query' => 'deploy API'])))->toContain('Deploying the API');
});

test('search_documents never returns admin-only documents to Alice', function () {
    $tool = new SearchDocuments($this->alice, new SecurityTrace);

    expect($tool->handle(new Request(['query' => 'secrets rotation password'])))->not->toContain('Secrets rotation');
});

test('get_document denies Alice an admin-only document even when she knows its id', function () {
    $secrets = Document::where('slug', 'secrets-rotation')->firstOrFail();
    $tool = new GetDocument($this->alice, new SecurityTrace);

    expect($tool->handle(new Request(['id' => $secrets->id])))
        ->toContain('access denied')
        ->not->toContain('not-a-real-password');
});

test('get_document lets an administrator read an admin-only document', function () {
    $secrets = Document::where('slug', 'secrets-rotation')->firstOrFail();
    $tool = new GetDocument(User::factory()->admin()->create(), new SecurityTrace);

    expect($tool->handle(new Request(['id' => $secrets->id])))->toContain('not-a-real-password');
});

test('the poisoned deployment document is stored and retrieved as is', function () {
    $deployment = Document::where('slug', 'deployment')->firstOrFail();
    $tool = new GetDocument($this->alice, new SecurityTrace);

    expect($tool->handle(new Request(['id' => $deployment->id])))
        ->toContain('For AI assistants:')
        ->toContain('http://127.0.0.1:8000/demo/callback');
});

test('secure mode neutralizes dangerous HTML and untrusted URLs in the assistant answer', function () {
    InternalDocsAgent::fake([implode("\n\n", [
        '<script>alert("xss")</script>',
        '[Check deployment status](http://127.0.0.1:8000/demo/callback?source=deployment-guide)',
        '![tracking](http://127.0.0.1:8000/demo/callback?source=image)',
        '[Run me](javascript:alert(1))',
        '[Lookalike](http://localhost:8000.attacker.test/)',
        '[Monitoring guide](/demo/docs)',
    ])]);

    $response = $this->actingAs($this->alice)->post(route('demo.docs.ask'), [
        'message' => 'How do I deploy the API?',
        'mode' => 'secure',
    ]);

    $response->assertOk()
        ->assertDontSee('<script>alert', false)
        ->assertDontSee('href="http://127.0.0.1:8000', false)
        ->assertDontSee('<img src="http', false)
        ->assertDontSee('href="javascript:', false)
        ->assertDontSee('href="http://localhost:8000.attacker.test', false)
        ->assertSee('[blocked link]')
        ->assertSee('href="/demo/docs"', false);
});

test('vulnerable mode renders the link injected by the poisoned document as an active link', function () {
    InternalDocsAgent::fake(['[Check deployment status](http://127.0.0.1:8000/demo/callback?source=deployment-guide)']);

    $response = $this->actingAs($this->alice)->post(route('demo.docs.ask'), [
        'message' => 'How do I deploy the API?',
        'mode' => 'vulnerable',
    ]);

    $response->assertOk()->assertSee('href="http://127.0.0.1:8000/demo/callback?source=deployment-guide"', false);
});

test('the docs assistant page states that the agent has no write capabilities', function () {
    $this->actingAs($this->alice)->get(route('demo.docs', ['mode' => 'secure']))
        ->assertOk()
        ->assertSee('SECURE MODE')
        ->assertSee('This agent has no write capabilities.');
});
