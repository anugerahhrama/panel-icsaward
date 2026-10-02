<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/__trusted-proxy-check', fn (Request $request) => [
        'url' => url('/'),
        'secure' => $request->secure(),
        'ip' => $request->ip(),
    ]);
});

test('urls use https when the reverse proxy terminated tls', function () {
    $response = $this->get('/__trusted-proxy-check', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'assessment.example',
        'X-Forwarded-Port' => '443',
        'X-Forwarded-For' => '203.0.113.7',
    ]);

    $response->assertOk()->assertExactJson([
        'url' => 'https://assessment.example',
        'secure' => true,
        'ip' => '203.0.113.7',
    ]);
});

test('urls stay plain http without forwarded headers', function () {
    $response = $this->get('/__trusted-proxy-check');

    $response->assertOk()->assertJson(['secure' => false]);
    expect($response->json('url'))->toStartWith('http://');
});
