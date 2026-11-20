<?php

declare(strict_types=1);

namespace PhpGraph\Extractor;

use PhpParser\Node as AstNode;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

/**
 * The Eloquent relations a method declares, read from the code, never run: the relation method it calls on `$this`
 * and the model class it names. Whether the class is a model is known once every class is: the builder decides.
 *
 *     public function pages(): HasMany { return $this->hasMany(Page::class); }
 */
final class EloquentRelations
{
    /** The relation methods of `Illuminate\Database\Eloquent\Concerns\HasRelationships` naming the related model first. */
    private const METHODS = [
        'hasone' => 'hasOne',
        'hasmany' => 'hasMany',
        'hasonethrough' => 'hasOneThrough',
        'hasmanythrough' => 'hasManyThrough',
        'belongsto' => 'belongsTo',
        'belongstomany' => 'belongsToMany',
        'morphone' => 'morphOne',
        'morphmany' => 'morphMany',
        'morphtomany' => 'morphToMany',
        'morphedbymany' => 'morphedByMany',
    ];

    /** @var list<array{string, string, string}> method => the relation method it calls and the model it names */
    private array $relations = [];

    /**
     * @param \Closure(Name): ?string $resolve
     */
    public function __construct(private readonly \Closure $resolve)
    {
    }

    public function onCall(Expr\MethodCall $call, ?string $method): void
    {
        if ($method === null || !str_contains($method, '::') || !$call->var instanceof Expr\Variable || $call->var->name !== 'this'
            || !$call->name instanceof AstNode\Identifier) {
            return;
        }
        $kind = self::METHODS[$call->name->toLowerString()] ?? null;
        if ($kind === null) {
            return;
        }
        $related = $this->related($call);
        if ($related !== null) {
            $this->relations[] = [$method, $kind, $related];
        }
    }

    /**
     * @return list<array{string, string, string}>
     */
    public function relations(): array
    {
        return $this->relations;
    }

    /**
     * The class of `Page::class`, first or named `related:`.
     */
    private function related(Expr\MethodCall $call): ?string
    {
        $value = null;
        foreach ($call->args as $position => $argument) {
            if (!$argument instanceof AstNode\Arg || $argument->unpack) {
                return null;
            }
            if ($argument->name?->toLowerString() === 'related' || ($argument->name === null && $position === 0)) {
                $value = $argument->value;
                break;
            }
        }
        if (!$value instanceof Expr\ClassConstFetch || !$value->class instanceof Name || !$value->name instanceof AstNode\Identifier
            || $value->name->toLowerString() !== 'class') {
            return null;
        }

        return ($this->resolve)($value->class);
    }
}
