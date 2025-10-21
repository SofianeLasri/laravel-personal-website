<?php

namespace App\Services\BotDetection;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;

class RouteParameterWhitelistService
{
    /**
     * @var array<string, array<string>>|null
     */
    private static ?array $whitelistCache = null;

    /**
     * Get whitelisted parameters for a given path
     *
     * @return array<string>
     */
    public static function getWhitelistedParameters(string $path): array
    {
        if (self::$whitelistCache === null) {
            self::buildWhitelistCache();
        }

        // Normalize path
        $path = trim($path, '/');

        // Direct match
        if (isset(self::$whitelistCache[$path])) {
            return self::$whitelistCache[$path];
        }

        // Try to match with route patterns
        if (is_array(self::$whitelistCache)) {
            foreach (self::$whitelistCache as $pattern => $params) {
                if (self::matchesPattern($path, $pattern)) {
                    return $params;
                }
            }
        }

        // Return common parameters that are generally safe
        return self::getCommonParameters();
    }

    /**
     * Build the whitelist cache from route definitions
     */
    private static function buildWhitelistCache(): void
    {
        self::$whitelistCache = [];

        $routes = Route::getRoutes();

        foreach ($routes->getRoutes() as $route) {
            $uri = $route->uri();
            $controller = $route->getControllerClass();
            $method = $route->getActionMethod();

            if (! $controller || ! $method) {
                continue;
            }

            $parameters = self::extractParametersFromController($controller, $method);

            // Store both the exact URI and a normalized version
            self::$whitelistCache[$uri] = $parameters;
            self::$whitelistCache[trim($uri, '/')] = $parameters;
        }

        // Add manual overrides for specific routes
        self::addManualOverrides();
    }

    /**
     * Extract expected parameters from a controller method
     *
     * @return array<string>
     */
    private static function extractParametersFromController(string $controller, string $method): array
    {
        $parameters = self::getCommonParameters();

        if (! class_exists($controller) || ! method_exists($controller, $method)) {
            return $parameters;
        }

        try {
            $reflection = new ReflectionMethod($controller, $method);
            $docComment = $reflection->getDocComment();

            // Look for FormRequest validation
            foreach ($reflection->getParameters() as $param) {
                $type = $param->getType();
                if ($type && $type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                    $className = $type->getName();
                    if (is_subclass_of($className, FormRequest::class)) {
                        $parameters = array_merge(
                            $parameters,
                            self::extractParametersFromFormRequest($className)
                        );
                    }
                }
            }

            // Look for validate() calls in the method body
            $fileName = $reflection->getFileName();
            if ($fileName === false) {
                return array_values(array_unique($parameters));
            }

            $source = file_get_contents($fileName);
            if ($source === false) {
                return array_values(array_unique($parameters));
            }

            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();
            if ($startLine === false || $endLine === false) {
                return array_values(array_unique($parameters));
            }

            $methodBody = self::extractMethodBody($source, $startLine, $endLine);

            if (preg_match('/->validate\s*\(\s*\[([^\]]+)\]/s', $methodBody, $matches)) {
                $validationRules = $matches[1];
                if (preg_match_all("/['\"]([^'\"]+)['\"]\s*=>/", $validationRules, $ruleMatches)) {
                    $parameters = array_merge($parameters, $ruleMatches[1]);
                }
            }

        } catch (Exception $e) {
            // Silently fail and return common parameters
        }

        return array_values(array_unique($parameters));
    }

    /**
     * Extract parameters from a FormRequest class
     *
     * @return array<string>
     */
    private static function extractParametersFromFormRequest(string $formRequestClass): array
    {
        $parameters = [];

        try {
            $instance = new $formRequestClass;
            if (method_exists($instance, 'rules')) {
                $rules = $instance->rules();
                $parameters = array_keys($rules);

                // Handle nested array validation (e.g., 'items.*.name')
                $parameters = array_map(function ($param) {
                    return explode('.', (string) $param)[0];
                }, $parameters);
            }
        } catch (Exception $e) {
            // Silently fail
        }

        // Ensure all elements are strings
        $parameters = array_filter($parameters, 'is_string');

        return array_values(array_unique($parameters));
    }

    /**
     * Extract method body from source code
     */
    private static function extractMethodBody(string $source, int $startLine, int $endLine): string
    {
        $lines = explode("\n", $source);
        $methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return implode("\n", $methodLines);
    }

    /**
     * Get common parameters that are generally safe
     *
     * @return array<string>
     */
    private static function getCommonParameters(): array
    {
        return [
            // Pagination
            'page',
            'per_page',
            'limit',
            'offset',

            // Sorting
            'sort',
            'order',
            'order_by',
            'sort_by',
            'direction',

            // Filtering
            'search',
            'q',
            'query',
            'filter',
            'filters',

            // Format
            'format',
            'type',

            // Locale
            'lang',
            'locale',
            'language',

            // Authentication
            'token',
            'api_key',

            // CSRF
            '_token',
            '_method',

            // Common IDs
            'id',
            'uuid',
            'slug',
        ];
    }

    /**
     * Add manual overrides for specific routes
     */
    private static function addManualOverrides(): void
    {
        // Dashboard routes
        self::$whitelistCache['dashboard/requests-log'] = array_merge(
            self::getCommonParameters(),
            [
                'is_bot',
                'include_user_agents',
                'exclude_user_agents',
                'include_ips',
                'exclude_ips',
                'date_from',
                'date_to',
                'exclude_connected_users_ips',
            ]
        );

        // Public routes
        self::$whitelistCache['projects'] = self::getCommonParameters();
        self::$whitelistCache[''] = self::getCommonParameters(); // Home page

        // API routes
        self::$whitelistCache['dashboard/api/creations'] = array_merge(
            self::getCommonParameters(),
            ['with_drafts', 'only_published']
        );

        self::$whitelistCache['dashboard/api/technologies'] = array_merge(
            self::getCommonParameters(),
            ['type', 'with_experience']
        );

        self::$whitelistCache['dashboard/api/experiences'] = array_merge(
            self::getCommonParameters(),
            ['type', 'current']
        );
    }

    /**
     * Check if a path matches a route pattern
     */
    private static function matchesPattern(string $path, string $pattern): bool
    {
        // Convert route parameters to regex
        $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        $regex = '#^'.$regex.'$#';

        return (bool) preg_match($regex, $path);
    }

    /**
     * Clear the whitelist cache
     */
    public static function clearCache(): void
    {
        self::$whitelistCache = null;
    }
}
