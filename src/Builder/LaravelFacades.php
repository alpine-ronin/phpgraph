<?php

declare(strict_types=1);

namespace PhpGraph\Builder;

use PhpGraph\Graph\Graph;
use PhpGraph\Graph\Relation;

/**
 * The class behind each Laravel facade of the project: the key its `getFacadeAccessor()` returns, bound by the service
 * providers (`bind('steam', fn (): Steam => new Steam())`), or the class the key names when nothing binds it. A key
 * bound nowhere, bound by code not read, or bound to two classes says nothing: no guess.
 */
final class LaravelFacades
{
    private const FACADE = 'illuminate\\support\\facades\\facade';

    private const SHOWN = 10;

    /** @var array<string, array{string, string}> facade => [its service, the key its accessor returns] */
    private array $accessors = [];

    /** @var array<string, array<string, list<?string>>> service => key => the classes bound to it, null when not read */
    private array $bindings = [];

    public function addAccessor(string $facade, string $service, string $key): void
    {
        $this->accessors[$facade] = [$service, $key];
    }

    public function addBinding(string $service, string $key, ?string $class): void
    {
        $this->bindings[$service][$key][] = $class;
    }

    /**
     * @return array{array<string, string>, FacadeStats} facade => the class its calls go to, and what was found
     */
    public function resolve(Graph $graph): array
    {
        $classes = [];
        $unresolved = [];
        $facades = 0;
        foreach ($this->accessors as $facade => [$service, $key]) {
            if (!$this->isFacade($graph, $facade)) {
                continue;
            }
            ++$facades;
            [$class, $reason] = $this->classOf($graph, $service, $key);
            if ($class !== null) {
                $classes[$facade] = $class;
            } else {
                $unresolved[] = \sprintf('%s (%s)', $facade, $reason);
            }
        }
        sort($unresolved);

        return [$classes, new FacadeStats($facades, \count($classes), \count($unresolved), \array_slice($unresolved, 0, self::SHOWN))];
    }

    /**
     * @return array{?string, string} the class, or null and why
     */
    private function classOf(Graph $graph, string $service, string $key): array
    {
        $bound = [];
        foreach (array_unique([$service, ServiceMap::ROOT, '']) as $scope) {
            foreach ($this->bindings[$scope][$key] ?? [] as $class) {
                $bound[] = $class;
            }
        }
        if ($bound === []) {
            return $graph->hasNode($key) ? [$key, ''] : [null, \sprintf("'%s' bound nowhere", $key)];
        }
        if (\in_array(null, $bound, true)) {
            return [null, \sprintf("'%s' bound by code not read", $key)];
        }
        $bound = array_values(array_unique(array_filter($bound, 'is_string')));
        if (\count($bound) > 1) {
            return [null, \sprintf("'%s' bound to %s", $key, implode(' and ', $bound))];
        }

        return [$bound[0], ''];
    }

    private function isFacade(Graph $graph, string $class): bool
    {
        $seen = [];
        $parents = [$class];
        while ($parents !== []) {
            $current = array_pop($parents);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            if (strtolower($current) === self::FACADE) {
                return true;
            }
            foreach ($graph->incident($current) as $item) {
                if ($item['forward'] && $item['edge']->relation === Relation::Extends) {
                    $parents[] = $item['other'];
                }
            }
        }

        return false;
    }
}
