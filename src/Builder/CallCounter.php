<?php

declare(strict_types=1);

namespace PhpGraph\Builder;

/**
 * Counts call site outcomes while the builder resolves them, for all code and for test code.
 */
final class CallCounter
{
    private const OUTCOMES = ['inferred' => 0, 'ambiguous' => 0, 'outsideProject' => 0, 'unknownReceiver' => 0, 'chainOutsideProject' => 0];

    /** @var array<string, int> */
    private array $all = self::OUTCOMES;

    /** @var array<string, int> */
    private array $tests = self::OUTCOMES;

    /** @var array<string, int> lowercase method name => unresolved call sites in application code */
    private array $unresolved = [];

    /** @var array<string, array<string, list<int>>> lowercase method name => file => lines, tests included */
    private array $unresolvedSites = [];

    /** @var array<string, string> lowercase method name => first spelling seen */
    private array $spellings = [];

    /**
     * @param string $outcome inferred, ambiguous, outsideProject, unknownReceiver or chainOutsideProject (a
     *                        chainOutsideProject call is also an unknownReceiver one)
     * @param string $file    where the call is written, kept with its line for an unresolved call
     */
    public function add(string $outcome, bool $inTests, string $method = '', string $file = '', ?int $line = null): void
    {
        if (!isset($this->all[$outcome])) {
            throw new \InvalidArgumentException(sprintf('Unknown call outcome "%s".', $outcome));
        }

        ++$this->all[$outcome];
        $unresolved = $outcome === 'unknownReceiver' || $outcome === 'chainOutsideProject';
        if ($unresolved) {
            $this->unresolvedSites[strtolower($method)][$file][] = $line ?? 0;
        }
        if ($inTests) {
            ++$this->tests[$outcome];
        } elseif ($unresolved) {
            $name = strtolower($method);
            $this->unresolved[$name] = ($this->unresolved[$name] ?? 0) + 1;
            $this->spellings[$name] ??= $method;
        }
    }

    /**
     * The method names most often called on a receiver of unknown type, in application code.
     *
     * @return array<string, int>
     */
    public function mostUnresolved(int $limit): array
    {
        arsort($this->unresolved);

        $most = [];
        foreach (\array_slice($this->unresolved, 0, $limit, true) as $name => $count) {
            $most[$this->spellings[$name] ?? (string) $name] = $count;
        }

        return $most;
    }

    /**
     * Where each method name is called on a receiver of unknown type, tests included: no edge says where those calls
     * go. Lines are written as edges write theirs, `12,40` (0 for a call whose line is not known).
     *
     * @return array<string, array<string, string>> lowercase method name => file => lines
     */
    public function unresolvedCalls(): array
    {
        $sites = [];
        ksort($this->unresolvedSites);
        foreach ($this->unresolvedSites as $name => $files) {
            ksort($files);
            foreach ($files as $file => $lines) {
                sort($lines);
                $sites[$name][$file] = implode(',', $lines);
            }
        }

        return $sites;
    }

    public function all(): CallStats
    {
        return self::stats($this->all);
    }

    public function tests(): CallStats
    {
        return self::stats($this->tests);
    }

    /**
     * @param array<string, int> $counts
     */
    private static function stats(array $counts): CallStats
    {
        return new CallStats(
            $counts['inferred'],
            $counts['ambiguous'],
            $counts['outsideProject'],
            $counts['unknownReceiver'] + $counts['chainOutsideProject'],
            $counts['chainOutsideProject'],
        );
    }
}
