<?php

namespace Dedoc\Scramble\Exceptions;

use Illuminate\Routing\Route;

interface RouteAware
{
    public function setRoute(Route $route): static;

    public function getRoute(): ?Route;

    public function getRouteAwareMessage(Route $route, string $msg): string;
}
