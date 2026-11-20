<?php

declare(strict_types=1);

namespace PhpGraph\Tests;

use PhpGraph\Builder\BuildResult;
use PhpGraph\Graph\Confidence;
use PhpGraph\Graph\Graph;
use PhpGraph\Graph\Relation;
use PhpGraph\Presentation\TextPresenter;
use PhpGraph\Query\GraphQuery;
use PhpGraph\Tests\Support\Dig;
use PhpGraph\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

/**
 * Eloquent relations: a model's method returning `$this->hasMany(Page::class)` relates the model to Page, and the
 * outline of the model names Page even when Page is one more member of a family.
 */
final class EloquentRelationsTest extends TestCase
{
    use TemporaryProject;

    private const ENTITY = 'namespace App\Models; use Illuminate\Database\Eloquent\Model; abstract class Entity extends Model { public function comments() { return $this->morphMany(\App\Activity\Comment::class, \'commentable\'); } }';

    private const CHILD = 'namespace App\Models; abstract class BookChild extends Entity { public function book() { return $this->belongsTo(Book::class); } }';

    private const BOOK = 'namespace App\Models; class Book extends Entity { public function pages() { return $this->hasMany(Page::class); } public function chapters() { return $this->hasMany(related: Chapter::class); } public function shelves() { return $this->belongsToMany(Shelf::class, \'shelves_books\'); } }';

    public function testAModelMethodCallingARelationMethodRelatesTheModelsAndSaysHow(): void
    {
        $graph = $this->library()->graph;

        self::assertSame('hasMany', $this->relation($graph, 'App\Models\Book::pages', 'App\Models\Page'), 'first argument');
        self::assertSame('hasMany', $this->relation($graph, 'App\Models\Book::chapters', 'App\Models\Chapter'), 'named related:');
        self::assertSame('belongsToMany', $this->relation($graph, 'App\Models\Book::shelves', 'App\Models\Shelf'), 'a pivot table after the model');
        self::assertSame('belongsTo', $this->relation($graph, 'App\Models\BookChild::book', 'App\Models\Book'), 'declared by a parent class of the models');
        self::assertSame('morphMany', $this->relation($graph, 'App\Models\Entity::comments', 'App\Activity\Comment'), 'a polymorphic relation');
    }

    public function testAClassThatIsNoModelRelatesNothing(): void
    {
        $graph = $this->buildProject([
            'app/Reports/Page.php' => 'namespace App\Reports; class Page { }',
            'app/Reports/Report.php' => 'namespace App\Reports; class Report { public function pages() { return $this->hasMany(Page::class); } private function hasMany(string $class): array { return []; } }',
        ])->graph;

        self::assertNull($this->relation($graph, 'App\Reports\Report::pages', 'App\Reports\Page'), 'a method of the same name on a class no model');
        self::assertTrue($this->hasEdge($graph, 'App\Reports\Report::pages', 'App\Reports\Page', Relation::References), 'the class is still referenced');
    }

    public function testTheOutlineOfAModelNamesTheModelsItRelatesToEvenInAFamily(): void
    {
        $presenter = new TextPresenter(new GraphQuery($this->library()->graph));

        $text = $presenter->outline('Book');
        self::assertMatchesRegularExpression('/^    Page \(class\)[^\n]* \[hasMany by Book::pages\]$/m', $text, 'Page is one more Entity, named all the same');
        self::assertMatchesRegularExpression('/^    Shelf \(class\)[^\n]* \[belongsToMany by Book::shelves\]$/m', $text);
        self::assertStringNotContainsString('Comment', $text, 'a polymorphic relation: comments belong to any entity, not to the book');

        self::assertMatchesRegularExpression('/^    Book \(class\)[^\n]* \[belongsTo by BookChild::book\]$/m', $presenter->outline('Page'), 'through the parent class of Page');

        $json = json_decode($presenter->outline('Book', 'json'), true);
        $reasons = [];
        foreach (Dig::list($json, 'core') as $index => $class) {
            $reasons[Dig::text($json, 'core', $index, 'id')] = Dig::at($json, 'core', $index, 'reason');
        }
        self::assertSame(['kind' => 'related', 'via' => 'App\Models\Book::pages', 'relation' => 'hasMany'], $reasons['App\Models\Page'] ?? null, 'the JSON names the method and the relation');
    }

    public function testAModelUsedByManyOutOfTheModuleIsNotNamed(): void
    {
        $files = $this->libraryFiles();
        $files['app/Models/Book.php'] = str_replace('{ public function pages()', '{ public function owner() { return $this->belongsTo(\\App\\Users\\User::class); } public function pages()', self::BOOK);
        $files['app/Users/User.php'] = 'namespace App\\Users; use Illuminate\\Database\\Eloquent\\Model; class User extends Model { }';
        for ($index = 1; $index <= 20; ++$index) {
            $files["app/Http/Controller{$index}.php"] = 'namespace App\Http; use App\Users\User; class Controller' . $index . ' { public function show(User $user): void { } }';
        }
        $graph = $this->buildProject($files)->graph;
        self::assertSame('belongsTo', $this->relation($graph, 'App\Models\Book::owner', 'App\Users\User'), 'the relation is in the graph');

        self::assertStringNotContainsString('belongsTo by Book::owner', (new TextPresenter(new GraphQuery($graph)))->outline('Book'), 'the User owning every record is no part of the book');
    }

    private function library(): BuildResult
    {
        return $this->buildProject($this->libraryFiles());
    }

    /**
     * @return array<string, string>
     */
    private function libraryFiles(): array
    {
        return [
            'app/Models/Entity.php' => self::ENTITY,
            'app/Models/BookChild.php' => self::CHILD,
            'app/Models/Book.php' => self::BOOK,
            'app/Models/Page.php' => 'namespace App\Models; class Page extends BookChild { }',
            'app/Models/Chapter.php' => 'namespace App\Models; class Chapter extends BookChild { public function pages() { return $this->hasMany(Page::class); } }',
            'app/Activity/Comment.php' => 'namespace App\Activity; use Illuminate\Database\Eloquent\Model; class Comment extends Model { }',
            'app/Models/Shelf.php' => 'namespace App\Models; class Shelf extends Entity { public function books() { return $this->belongsToMany(Book::class); } }',
        ];
    }

    private function relation(Graph $graph, string $method, string $model): ?string
    {
        foreach ($graph->incident($method) as $item) {
            if ($item['forward'] && $item['other'] === $model && $item['edge']->relation === Relation::RelatesTo) {
                self::assertSame(Confidence::Extracted, $item['edge']->confidence, 'read in the code');

                return $item['edge']->via();
            }
        }

        return null;
    }
}
