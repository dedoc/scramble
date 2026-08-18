<?php

namespace Dedoc\Scramble\Tests;

use Illuminate\Routing\Router;
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
    public function it_serves_only_dev_tools_dist_assets(): void
    {
        $route = app(Router::class)->getRoutes()->getByName('scramble.dev-tools.asset');

        $this->assertNotNull($route);

        $this->get('/_scramble/dev-tools/devtools.js')
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, public')
            ->assertHeader('content-type', 'text/javascript; charset=UTF-8');

        $this->get('/_scramble/dev-tools/devtools.css')
            ->assertOk()
            ->assertHeader('content-type', 'text/css; charset=UTF-8');

        $this->get('/_scramble/dev-tools/other.js')->assertNotFound();
    }
}
