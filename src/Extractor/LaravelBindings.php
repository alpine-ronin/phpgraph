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

    /** @var array<string, string> facade class => the container key its accessor returns */
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
     * @return array<string, string>
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
        $returned = [];
        foreach ($method->stmts ?? [] as $statement) {
            if ($statement instanceof Stmt\Return_) {
                $returned[] = $statement->expr;
            }
        }
        $key = \count($returned) === 1 ? $this->key($returned[0]) : null;
        if ($key !== null) {
            $this->accessors[$class] = $key;
        }
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
     * `bind('key', ...)`, `singleton(X::class)`, `instance('key', new X())`... on any receiver: the container is reached
     * through `$this->app`, `app()`, `App::` or a variable.
     */
    public function onCall(Expr\MethodCall|Expr\StaticCall $call): void
    {
        if (!$call->name instanceof AstNode\Identifier || !\in_array($call->name->toLowerString(), self::BINDING_METHODS, true)) {
            return;
        }
        $arguments = [];
        foreach ($call->args as $argument) {
            if (!$argument instanceof AstNode\Arg || $argument->unpack || $argument->name !== null) {
                break;
            }
            $arguments[] = $argument->value;
        }
        $key = $this->key($arguments[0] ?? null);
        if ($key === null) {
            return;
        }
        $bound = $arguments[1] ?? null;
        if ($bound !== null) {
            $this->bindings[] = [$key, $this->boundClass($bound)];
        } elseif (str_contains($key, '\\')) {
            // `singleton(Steam::class)`: the class itself.
            $this->bindings[] = [$key, $key];
        }
    }

    /**
     * A key: a string literal, or `X::class`.
     */
    private function key(?Expr $expr): ?string
    {
        if ($expr instanceof AstNode\Scalar\String_) {
            return ltrim($expr->value, '\\');
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
            $returned = [];
            foreach ($value->stmts as $statement) {
                if ($statement instanceof Stmt\Return_ && $statement->expr !== null) {
                    $returned[] = $statement->expr;
                }
            }

            return $this->instantiated($returned) ?? $this->returnType($value->returnType);
        }

        return $this->className($value);
    }

    /**
     * The class every expression instantiates, when they all instantiate the same one.
     *
     * @param list<Expr> $expressions
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
