<?php

namespace Dedoc\Scramble\Support\OperationExtensions;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Infer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Reflection\ReflectionRoute;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Generator\UniqueNameOptions;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\ServerFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use ReflectionAttribute;

class RequestEssentialsExtension extends OperationExtension
{
    public function __construct(
        Infer $infer,
        TypeTransformer $openApiTransformer,
        GeneratorConfig $config,
        private OpenApi $openApi,
        private OpenApiContext $openApiContext,
    ) {
        parent::__construct($infer, $openApiTransformer, $config);
    }

    private function getDefaultTags(Operation $operation, RouteInfo $routeInfo)
    {
        $defaultName = Str::of(class_basename($routeInfo->className()))->replace('Controller', '');

        if ($groupAttrsInstances = $this->getTagsAnnotatedByGroups($routeInfo)) {
            $attributeInstance = $groupAttrsInstances[0]->newInstance();

            $operation->setAttribute('groupWeight', $attributeInstance->weight);

            return [
                $attributeInstance->name ?: $defaultName,
            ];
        }

        return array_unique([
            ...$this->extractTagsForMethod($routeInfo),
            $defaultName,
        ]);
    }

    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        $this->attachTagsToOpenApi($routeInfo);

        $pathAliases = ReflectionRoute::createFromRoute($routeInfo->route)->getSignatureParametersMap();

        $tagResolver = Scramble::$tagResolver ?? fn () => $this->getDefaultTags($operation, $routeInfo);

        $uriWithoutOptionalParams = Str::replace('?}', '}', $routeInfo->route->uri);

        $operation
            ->setMethod(strtolower($routeInfo->route->methods()[0]))
            ->setPath(Str::replace(
                collect($pathAliases)->keys()->map(fn ($k) => '{'.$k.'}')->all(),
                collect($pathAliases)->values()->map(fn ($v) => '{'.$v.'}')->all(),
                $uriWithoutOptionalParams,
            ))
            ->setTags($tagResolver($routeInfo, $operation))
            ->servers($this->getAlternativeServers($routeInfo->route));

        if (count($routeInfo->phpDoc()->getTagsByName('@unauthenticated'))) {
            $operation->security = [];
        }

        $operation->setAttribute('operationId', $this->getOperationId($routeInfo));
    }

    /**
     * Checks if route domain needs to have alternative servers defined. Route needs to have alternative servers defined if
     * the route has not matching domain to any servers in the root.
     *
     * Domain is matching if all the server variables matching.
     */
    private function getAlternativeServers(Route $route)
    {
        if (! $route->getDomain()) {
            return [];
        }

        [$protocol] = explode('://', url('/'));
        $expectedServer = (new ServerFactory($this->config->serverVariables->all()))
            ->make($protocol.'://'.$route->getDomain().'/'.$this->config->get('api_path', 'api'));

        if ($this->isServerMatchesAllGivenServers($expectedServer, $this->openApi->servers)) {
            return [];
        }

        $matchingServers = collect($this->openApi->servers)->filter(fn (Server $s) => $this->isMatchingServerUrls($expectedServer->url, $s->url));
        if ($matchingServers->count()) {
            return $matchingServers->values()->toArray();
        }

        return [$expectedServer];
    }

    private function isServerMatchesAllGivenServers(Server $expectedServer, array $actualServers)
    {
        return collect($actualServers)->every(fn (Server $s) => $this->isMatchingServerUrls($expectedServer->url, $s->url));
    }

    private function isMatchingServerUrls(string $expectedUrl, string $actualUrl)
    {
        $mask = function (string $url) {
            [, $urlPart] = explode('://', $url);
            [$domain, $path] = count($parts = explode('/', $urlPart, 2)) !== 2 ? [$parts[0], ''] : $parts;

            $params = Str::of($domain)->matchAll('/\{(.*?)\}/');

            return $params->join('.').'/'.$path;
        };

        return $mask($expectedUrl) === $mask($actualUrl);
    }

    private function extractTagsForMethod(RouteInfo $routeInfo)
    {
        $classPhpDoc = $routeInfo->reflectionMethod()
            ? $routeInfo->reflectionMethod()->getDeclaringClass()->getDocComment()
            : false;

        $classPhpDoc = $classPhpDoc ? PhpDoc::parse($classPhpDoc) : new PhpDocNode([]);

        if (! count($tagNodes = $classPhpDoc->getTagsByName('@tags'))) {
            return [];
        }

        return explode(',', array_values($tagNodes)[0]->value->value);
    }

    private function getOperationId(RouteInfo $routeInfo)
    {
        $routeClassName = $routeInfo->className() ?: '';

        return new UniqueNameOptions(
            eloquent: (function () use ($routeInfo) {
                // Manual operation ID setting.
                if (
                    ($operationId = $routeInfo->phpDoc()->getTagsByName('@operationId'))
                    && ($value = trim(Arr::first($operationId)?->value?->value))
                ) {
                    return $value;
                }

                // Using route name as operation ID if set. We need to avoid using generated route names as this
                // will result gibberish operation IDs when routes without names are cached.
                if (($name = $routeInfo->route->getName()) && ! Str::contains($name, 'generated::')) {
                    return Str::startsWith($name, 'api.') ? Str::replaceFirst('api.', '', $name) : $name;
                }

                // If no name and no operationId manually set, falling back to controller and method name (unique implementation).
                return null;
            })(),
            unique: collect(explode('\\', Str::endsWith($routeClassName, 'Controller') ? Str::replaceLast('Controller', '', $routeClassName) : $routeClassName))
                ->filter()
                ->push($routeInfo->methodName())
                ->map(function ($part) {
                    if ($part === Str::upper($part)) {
                        return Str::lower($part);
                    }

                    return Str::camel($part);
                })
                ->reject(fn ($p) => in_array(Str::lower($p), ['app', 'http', 'api', 'controllers', 'invoke']))
                ->values()
                ->toArray(),
        );
    }

    /**
     * @return ReflectionAttribute<Group>[]
     */
    private function getTagsAnnotatedByGroups(RouteInfo $routeInfo): array
    {
        return [
            ...($routeInfo->reflectionMethod()?->getAttributes(Group::class) ?? []),
            ...($routeInfo->reflectionMethod()?->getDeclaringClass()->getAttributes(Group::class) ?? []),
        ];
    }

    private function attachTagsToOpenApi(RouteInfo $routeInfo): void
    {
        if (! $groups = $this->getTagsAnnotatedByGroups($routeInfo)) {
            return;
        }

        foreach ($groups as $group) {
            $groupInstance = $group->newInstance();

            if (! $groupInstance->name) {
                continue;
            }

            $this->openApiContext->groups->push($group);
        }
    }
}
