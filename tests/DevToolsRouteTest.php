<?php

trait EnablesDevToolsRoute
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scramble.dev_tools.enabled', true);
        $app['config']->set('scramble.middleware', []);
    }
}

uses(EnablesDevToolsRoute::class);

it('serves the dev tools bundle', function () {
    $response = $this->get('/_scramble/dev-tools/devtools.js')
        ->assertOk()
        ->assertHeader('content-type', 'text/javascript; charset=UTF-8');

    expect($response->headers->get('cache-control'))->toContain('no-store');
});
