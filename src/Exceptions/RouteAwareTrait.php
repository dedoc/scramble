<?php

namespace Dedoc\Scramble\Exceptions;

use Exception;
use Illuminate\Routing\Route;

/**
 * @mixin Exception
 */
trait RouteAwareTrait
{
    protected ?Route $route = null;

    public function setRoute(Route $route): static
    {
        $this->route = $route;

        if (method_exists($this, 'getRouteAwareMessage')) {
            $this->message = $this->getRouteAwareMessage($route, $this->getMessage());
        }

        return $this;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function getRouteAwareMessage(Route $route, string $msg): string
    {
        $method = $route->methods()[0];
        $action = $route->getAction('uses');

        return "'$method $route->uri' ($action): ".$msg;
    }
}
