<?php

it('infers redirect helper expressions', function (string $expression, string $expectedType, ?string $expectedTargetUrl = null) {
    $type = getStatementType($expression);

    expect($type->toString())->toBe($expectedType);

    if ($expectedTargetUrl !== null) {
        expect($type->getAttribute('targetUrl')->toString())->toBe($expectedTargetUrl);
    }
})->with([
    ['redirect()', 'Illuminate\Routing\Redirector'],
    ['redirect(null)', 'Illuminate\Routing\Redirector'],
    ["redirect('/home')", 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["redirect('/home', 301, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(301), array{X-Foo: string(bar)}>', 'string'],
    ["redirect(to: '/home', headers: ['X-Foo' => 'bar'], status: 307)", 'Illuminate\Http\RedirectResponse<string(), int(307), array{X-Foo: string(bar)}>', 'string'],
    ['back()', 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["back(303, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(303), array{X-Foo: string(bar)}>', 'string'],
    ["to_route('home')", 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["to_route('home', ['id' => 1], 308, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(308), array{X-Foo: string(bar)}>', 'string'],
    ["to_route('home', headers: ['X-Foo' => 'bar'], status: 301)", 'Illuminate\Http\RedirectResponse<string(), int(301), array{X-Foo: string(bar)}>', 'string'],
]);

it('infers redirector method expressions', function (string $expression, string $expectedType, string $expectedTargetUrl) {
    $type = getStatementType($expression);

    expect($type->toString())->toBe($expectedType)
        ->and($type->getAttribute('targetUrl')->toString())->toBe($expectedTargetUrl);
})->with([
    ['redirect()->back()', 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["redirect()->back(303, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(303), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->refresh(status: 307, headers: ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(307), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->to('/home')", 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["redirect()->to('/home', 301, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(301), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->secure('/home', 308, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(308), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->guest('/login', 303, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(303), array{X-Foo: string(bar)}>', 'string'],
    ['redirect()->intended()', 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["redirect()->intended('/home', headers: ['X-Foo' => 'bar'], status: 307)", 'Illuminate\Http\RedirectResponse<string(), int(307), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->away('https://example.com')", 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string(https://example.com)'],
    ["redirect()->away(path: 'https://example.com', headers: ['X-Foo' => 'bar'], status: 301)", 'Illuminate\Http\RedirectResponse<string(), int(301), array{X-Foo: string(bar)}>', 'string(https://example.com)'],
    ["redirect()->route('home')", 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["redirect()->route('home', ['id' => 1], 303, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(303), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->action('HomeController@index', [], 307, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(307), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->signedRoute('home')", 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string'],
    ["redirect()->signedRoute('home', ['id' => 1], null, 308, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(308), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->temporarySignedRoute('home', 123, [], 301, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(301), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->temporarySignedRoute('home', expiration: 123, headers: ['X-Foo' => 'bar'], status: 303)", 'Illuminate\Http\RedirectResponse<string(), int(303), array{X-Foo: string(bar)}>', 'string'],
    ["redirect()->away('https://example.com')->setStatusCode(308)", 'Illuminate\Http\RedirectResponse<string(), int(308), array<mixed>>', 'string(https://example.com)'],
]);

it('infers redirect response creation', function (string $expression, string $expectedType, string $expectedTargetUrl) {
    $type = getStatementType($expression);

    expect($type->toString())->toBe($expectedType)
        ->and($type->getAttribute('targetUrl')->toString())->toBe($expectedTargetUrl);
})->with([
    ["new Illuminate\Http\RedirectResponse('/home')", 'Illuminate\Http\RedirectResponse<string(), int(302), array<mixed>>', 'string(/home)'],
    ["new Illuminate\Http\RedirectResponse('/home', 301, ['X-Foo' => 'bar'])", 'Illuminate\Http\RedirectResponse<string(), int(301), array{X-Foo: string(bar)}>', 'string(/home)'],
    ["new Symfony\Component\HttpFoundation\RedirectResponse('/home')", 'Symfony\Component\HttpFoundation\RedirectResponse<string(), int(302), array<mixed>>', 'string(/home)'],
    ["new Symfony\Component\HttpFoundation\RedirectResponse(url: '/home', headers: ['X-Foo' => 'bar'], status: 307)", 'Symfony\Component\HttpFoundation\RedirectResponse<string(), int(307), array{X-Foo: string(bar)}>', 'string(/home)'],
    ["(new Illuminate\Http\RedirectResponse('/home'))->setStatusCode(303)", 'Illuminate\Http\RedirectResponse<string(), int(303), array<mixed>>', 'string(/home)'],
]);
