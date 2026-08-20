<?php

namespace Dedoc\Scramble\Tests;

use PHPUnit\Framework\Attributes\Test;

class DevToolsRouteTest extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scramble.dev_tools', true);
        $app['config']->set('scramble.middleware', []);
    }

    #[Test]
    public function it_serves_the_dev_tools_bundle(): void
    {
        $response = $this->get('/_scramble/dev-tools/devtools.js')
            ->assertOk()
            ->assertHeader('content-type', 'text/javascript; charset=UTF-8');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }
}
