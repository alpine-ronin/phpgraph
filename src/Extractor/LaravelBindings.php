<?php

declare(strict_types=1);

namespace PhpGraph\Extractor;

use PhpParser\Node as AstNode;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

/**
 * What a Laravel facade stands for, read from the code, never run: the key its `getFacadeAccessor()` returns, and the
 * classes the service providers bind keys to.
 *
 *     protected static function getFacadeAccessor(): string { return 'steam'; }
 *     $this->app->bind('steam', static fn (): Steam => new Steam());
 *     public $singletons = ['activity' => ActivityLogger::class];
 */
final class LaravelBindings
{
    private const BINDING_METHODS = ['bind', 'bindif', 'singleton', 'singletonif', 'scoped', 'scopedif', 'instance'];

    private const BINDING_PROPERTIES = ['bindings', 'singletons'];

    /** Receivers of a `bind()` that is no container binding: `Route::bind('user', ...)`, `$router->bind(...)`. */
    private const ROUTERS = ['route', 'router'];

    /** @var array<string, ?string> facade class => the container key its accessor returns, null when not read */
    private array $accessors = [];

    /** @var list<array{string, ?string}> container key => the class bound to it, null when not read */
    private array $bindings = [];

    /**
     * @param \Closure(Name): ?string $resolve
     */
    public function __construct(private readonly \Closure $resolve)
    {
    }

    /**
     * @return array<string, ?string>
     */
    public function accessors(): array
    {
        return $this->accessors;
    }

    /**
     * @return list<array{string, ?string}>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    public function onMethod(string $class, Stmt\ClassMethod $method): void
    {
        if (strtolower($method->name->toString()) !== 'getfacadeaccessor') {
            return;
        }
        // Every return gives the same key, or the key is not read (`if (...) { return 'a'; } return config('b');`):
        // recorded all the same, so that the facade does not take a parent's key nor another declaration's.
        $keys = array_map(fn (?Expr $returned): ?string => $this->key($returned), $this->returns($method->stmts ?? []));
        $unique = array_values(array_unique(array_filter($keys, 'is_string')));
        $isRead = $keys !== [] && !\in_array(null, $keys, true) && \count($unique) === 1;
        $this->accessors[$class] = $isRead ? $unique[0] : null;
    }

    /**
     * `$bindings` and `$singletons` of a service provider.
     */
    public function onProperty(?string $parent, Stmt\Property $property): void
    {
        if ($parent === null || !str_ends_with(strtolower($parent), 'provider')) {
            return;
        }
        foreach ($property->props as $item) {
            if (!\in_array(strtolower($item->name->toString()), self::BINDING_PROPERTIES, true) || !$item->default instanceof Expr\Array_) {
                continue;
            }
            foreach ($item->default->items as $entry) {
                $key = $this->key($entry->key);
                if ($key !== null) {
                    $this->bindings[] = [$key, $this->boundClass($entry->value)];
                }
            }
        }
    }

    /**
     * `bind('key', ...)`, `singleton(X::class)`, `instance('key', new X())`... on any receiver but the router: the
     * container is reached through `$this->app`, `app()`, `App::` or a variable; `Route::bind()` binds a route parameter.
     */
    public function onCall(Expr\MethodCall|Expr\StaticCall $call): void
    {
        if (!$call->name instanceof AstNode\Identifier || !\in_array($call->name->toLowerString(), self::BINDING_METHODS, true) || $this->isRouter($call)) {
            return;
        }
        $positional = $named = [];
        $unread = false;
        foreach ($call->args as $argument) {
            if (!$argument instanceof AstNode\Arg || $argument->unpack) {
                $unread = true;
                break;
            }
            if ($argument->name !== null) {
                $named[$argument->name->toLowerString()] = $argument->value;
            } elseif ($named === []) {
                $positional[] = $argument->value;
            }
        }
        $key = $this->key($positional[0] ?? $named['abstract'] ?? null);
        if ($key === null) {
            return;
        }
        $bound = $positional[1] ?? $named['concrete'] ?? $named['instance'] ?? null;
        if ($bound !== null) {
            $this->bindings[] = [$key, $this->boundClass($bound)];
        } elseif ($unread) {
            // `bind(Clock::class, ...$arguments)`: the class bound is not read.
            $this->bindings[] = [$key, null];
        } elseif (str_contains($key, '\\')) {
            // `singleton(Steam::class)`: the class itself.
            $this->bindings[] = [$key, $key];
        }
    }

    private function isRouter(Expr\MethodCall|Expr\StaticCall $call): bool
    {
        if ($call instanceof Expr\StaticCall) {
            $class = $call->class instanceof Name ? ($this->resolve)($call->class) : null;

            $shortName = $class === null ? null : strtolower(substr((string) strrchr('\\' . $class, '\\'), 1));

            return \in_array($shortName, self::ROUTERS, true);
        }
        $receiver = $call->var;
        $name = match (true) {
            $receiver instanceof Expr\Variable => $receiver->name,
            $receiver instanceof Expr\PropertyFetch => $receiver->name instanceof AstNode\Identifier ? $receiver->name->toString() : null,
            default => null,
        };

        return \is_string($name) && \in_array(strtolower($name), self::ROUTERS, true);
    }

    /**
     * The values returned by statements, out of the closures and classes they declare.
     *
     * @param array<AstNode> $nodes
     *
     * @return list<?Expr>
     */
    private function returns(array $nodes): array
    {
        $returned = [];
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Return_) {
                $returned[] = $node->expr;
                continue;
            }
            if ($node instanceof AstNode\FunctionLike || $node instanceof Stmt\ClassLike) {
                continue;
            }
            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->{$name};
                $children = array_filter(\is_array($child) ? $child : [$child], static fn (mixed $item): bool => $item instanceof AstNode);
                foreach ($this->returns($children) as $value) {
                    $returned[] = $value;
                }
            }
        }

        return $returned;
    }

    /**
     * A key: a string literal, or `X::class`.
     */
    private function key(?Expr $expr): ?string
    {
        if ($expr instanceof AstNode\Scalar\String_) {
            $key = ltrim($expr->value, '\\');

            return $key === '' ? null : $key;
        }

        return $this->className($expr);
    }

    /**
     * The class of what is bound: `X::class`, a class name, `new X()`, a closure returning `new X()` or declaring one
     * class as its return type.
     */
    private function boundClass(Expr $value): ?string
    {
        if ($value instanceof AstNode\Scalar\String_) {
            return preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $value->value) === 1 ? ltrim($value->value, '\\') : null;
        }
        if ($value instanceof Expr\New_) {
            return $value->class instanceof Name ? ($this->resolve)($value->class) : null;
        }
        if ($value instanceof Expr\ArrowFunction) {
            return $this->instantiated([$value->expr]) ?? $this->returnType($value->returnType);
        }
        if ($value instanceof Expr\Closure) {
            return $this->instantiated($this->returns($value->stmts)) ?? $this->returnType($value->returnType);
        }

        return $this->className($value);
    }

    /**
     * The class every expression instantiates, when they all instantiate the same one.
     *
     * @param list<?Expr> $expressions
     */
    private function instantiated(array $expressions): ?string
    {
        $classes = [];
        foreach ($expressions as $expression) {
            if (!$expression instanceof Expr\New_ || !$expression->class instanceof Name) {
                return null;
            }
            $classes[] = ($this->resolve)($expression->class);
        }
        $classes = array_unique($classes);

        return \count($classes) === 1 ? reset($classes) : null;
    }

    private function returnType(AstNode\Identifier|Name|AstNode\ComplexType|null $type): ?string
    {
        return $type instanceof Name ? ($this->resolve)($type) : null;
    }

    private function className(?Expr $expr): ?string
    {
        return $expr instanceof Expr\ClassConstFetch && $expr->class instanceof Name && $expr->name instanceof AstNode\Identifier
            && $expr->name->toLowerString() === 'class'
            ? ($this->resolve)($expr->class)
            : null;
    }
}
