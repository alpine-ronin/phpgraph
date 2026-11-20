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
        // The first declaration, as the graph keeps it: a test fixture declaring the facade again does not replace it.
        $this->accessors[$facade] ??= [$service, $key];
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
        $lineages = [];
        foreach ($this->accessors as $facade => [$service, $key]) {
            $lineage = $this->lineage($graph, $facade);
            if (!\in_array(self::FACADE, array_map('strtolower', $lineage), true)) {
                continue;
            }
            $lineages[$facade] = $lineage;
            [$class, $reason] = $this->classOf($graph, $service, $key);
            if ($class !== null) {
                $classes[$facade] = $class;
            } else {
                $unresolved[$facade] = \sprintf('%s (%s)', $facade, $reason);
            }
        }
        // The calls of a facade go to the target of its nearest parent: a subclass overriding the accessor with a key
        // not followed would get its parent's, so the parent's is dropped.
        foreach (array_keys($unresolved) as $facade) {
            foreach (\array_slice($lineages[$facade], 1) as $parent) {
                if (isset($classes[$parent])) {
                    unset($classes[$parent]);
                    $unresolved[$parent] = \sprintf('%s (its subclass %s is not followed)', $parent, $facade);
                }
            }
        }
        $reasons = array_values($unresolved);
        sort($reasons);

        return [$classes, new FacadeStats(\count($lineages), \count($classes), \count($reasons), \array_slice($reasons, 0, self::SHOWN))];
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

    /**
     * The class, then its parents, nearest first.
     *
     * @return list<string>
     */
    private function lineage(Graph $graph, string $class): array
    {
        $lineage = [];
        $current = $class;
        while ($current !== null && !\in_array($current, $lineage, true)) {
            $lineage[] = $current;
            $parent = null;
            foreach ($graph->incident($current) as $item) {
                if ($item['forward'] && $item['edge']->relation === Relation::Extends) {
                    $parent = $item['other'];
                    break;
                }
            }
            $current = $parent;
        }

        return $lineage;
    }
}
