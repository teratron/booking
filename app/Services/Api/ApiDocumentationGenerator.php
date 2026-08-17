<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Http\Controllers\Api\V1\Concerns\DocumentsQueryParameters;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Generates the published API reference directly from what is actually
 * registered and actually enforced — the route table for the endpoint
 * list, {@see DocumentsQueryParameters::queryParameterRules()} for each
 * endpoint's own parameters (the identical array `$request->validate()`
 * runs), and the `abilities:` route middleware for the resource ability a
 * token needs. None of these are restated by hand, so the document and the
 * contract cannot drift apart from each other.
 */
final class ApiDocumentationGenerator
{
    /**
     * @return list<array{
     *     name: string,
     *     method: string,
     *     uri: string,
     *     ability: ?string,
     *     requires_token: bool,
     *     parameters: array<string, list<string>>,
     * }>
     */
    public function generate(): array
    {
        $endpoints = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'api.v1.')) {
                continue;
            }

            $endpoints[] = [
                'name' => $name,
                'method' => $this->primaryMethod($route),
                'uri' => '/'.ltrim($route->uri(), '/'),
                'ability' => $this->ability($route),
                'requires_token' => in_array('auth:sanctum', $route->gatherMiddleware(), true),
                'parameters' => $this->parameters($route),
            ];
        }

        usort($endpoints, static fn (array $a, array $b): int => $a['uri'] <=> $b['uri']);

        return $endpoints;
    }

    private function primaryMethod(Route $route): string
    {
        foreach ($route->methods() as $method) {
            if ($method !== 'HEAD') {
                return $method;
            }
        }

        return 'GET';
    }

    private function ability(Route $route): ?string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (str_starts_with($middleware, 'abilities:')) {
                return substr($middleware, strlen('abilities:'));
            }
        }

        return null;
    }

    /** @return array<string, list<string>> */
    private function parameters(Route $route): array
    {
        $controllerClass = $route->getControllerClass();

        if ($controllerClass === null || ! is_a($controllerClass, DocumentsQueryParameters::class, true)) {
            return [];
        }

        return $controllerClass::queryParameterRules();
    }
}
