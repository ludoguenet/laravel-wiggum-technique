<?php

use Illuminate\Support\Facades\Log;

test('the demo callback logs the query parameters without cookies', function () {
    Log::spy();

    $response = $this->withHeader('Cookie', 'laravel_session=fake')
        ->get('/demo/callback?document=deployment&assistant=true');

    $response->assertOk()->assertSee('Demo callback reached.');

    Log::shouldHaveReceived('info')->once()->withArgs(
        fn (string $message, array $context) => $message === 'Demo callback reached'
            && $context['query'] === ['document' => 'deployment', 'assistant' => 'true']
            && ! array_key_exists('cookie', $context['headers'])
    );
});
