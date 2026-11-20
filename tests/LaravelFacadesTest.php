<?php

declare(strict_types=1);

namespace PhpGraph\Tests;

use PhpGraph\Graph\Confidence;
use PhpGraph\Graph\Relation;
use PhpGraph\Tests\Support\TemporaryProject;
use PHPUnit\Framework\TestCase;

/**
 * Laravel facades without a docblock: their static calls go to the class behind `getFacadeAccessor()`, read from the
 * bindings of the service providers.
 */
final class LaravelFacadesTest extends TestCase
{
    use TemporaryProject;

    private const STEAM = 'namespace App\Support; class Steam { public function balance(): int { return 0; } }';

    private const CALLER = 'namespace App\Http; use App\Facades\Steam; class Controller { public function show(): int { return Steam::balance(); } }';

    public function testAKeyBoundByAClosureLeadsToTheClassItReturns(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \'steam\'; } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support\Steam; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->bind(\'steam\', static fn (): Steam => new Steam()); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls, Confidence::Inferred), 'the facade key is bound to Steam');
        self::assertSame([1, 1, 0], [$result->facades->facades, $result->facades->followed, $result->facades->unresolvedCount], 'one facade, followed');
    }

    public function testAnAccessorNamingAClassBoundNowhereLeadsToThatClass(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor() { return \App\Support\Steam::class; } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls, Confidence::Inferred), 'the container builds the class itself');
    }

    public function testAnAccessorNamingAnInterfaceLeadsToTheClassBoundToIt(): void
    {
        $result = $this->buildProject([
            'app/Support/BalanceInterface.php' => 'namespace App\Support; interface BalanceInterface { public function balance(): int; }',
            'app/Support/Steam.php' => 'namespace App\Support; class Steam implements BalanceInterface { public function balance(): int { return 0; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; use App\Support\BalanceInterface; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return BalanceInterface::class; } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support\BalanceInterface; use App\Support\Steam; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->singleton(BalanceInterface::class, Steam::class); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls, Confidence::Inferred), 'the interface is bound to Steam');
    }

    public function testTheBindingArraysOfAProviderAndTheClosuresInstantiatingAClassAreRead(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Support/Amount.php' => 'namespace App\Support; class Amount { public function format(): string { return ""; } }',
            'app/Support/Navigation.php' => 'namespace App\Support; class Navigation { public function next(): string { return ""; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \'steam\'; } }',
            'app/Facades/Amount.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Amount extends Facade { protected static function getFacadeAccessor(): string { return \'amount\'; } }',
            'app/Facades/Navigation.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Navigation extends Facade { protected static function getFacadeAccessor(): string { return \'navigation\'; } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public $singletons = [\'steam\' => Support\Steam::class]; public function register(): void { $this->app->bind(\'amount\', function ($app) { return new Support\Amount(); }); $this->app->instance(\'navigation\', new Support\Navigation()); } }',
            'app/Http/Controller.php' => 'namespace App\Http; use App\Facades; class Controller { public function show(): string { Facades\Steam::balance(); Facades\Navigation::next(); return Facades\Amount::format(); } }',
        ]);

        foreach (['App\Support\Steam::balance' => '$singletons', 'App\Support\Amount::format' => 'a closure returning new Amount', 'App\Support\Navigation::next' => 'instance()'] as $method => $form) {
            self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', $method, Relation::Calls, Confidence::Inferred), 'bound by ' . $form);
        }
    }

    public function testAKeyBoundToTwoClassesOrBoundNowhereIsReportedAndNotGuessed(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Support/OtherSteam.php' => 'namespace App\Support; class OtherSteam { public function balance(): int { return 1; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \'steam\'; } }',
            'app/Facades/Theme.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Theme extends Facade { protected static function getFacadeAccessor(): string { return \'theme\'; } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->bind(\'steam\', Support\Steam::class); $this->app->bind(\'steam\', Support\OtherSteam::class); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls, Confidence::Inferred), 'two classes bound to the key: no guess');
        self::assertSame(
            ["App\\Facades\\Steam ('steam' bound to App\\Support\\Steam and App\\Support\\OtherSteam)", "App\\Facades\\Theme ('theme' bound nowhere)"],
            $result->facades->unresolved,
            'each facade not followed says why',
        );
    }

    public function testATestBindingAFakeDoesNotHideTheProviderBinding(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \'steam\'; } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support\Steam; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->bind(\'steam\', fn () => new Steam()); } }',
            'tests/Feature/BalanceTest.php' => 'namespace Tests\Feature; class BalanceTest { public function testIt(): void { $this->app->instance(\'steam\', $this->createMock(\App\Support\Steam::class)); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls, Confidence::Inferred), 'the fake of a test is not what the application gets');
    }

    public function testAMixinOfTheFacadeComesFirst(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Support/Ledger.php' => 'namespace App\Support; class Ledger { public function balance(): int { return 2; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; /** @mixin \App\Support\Ledger */ class Steam extends Facade { protected static function getFacadeAccessor(): string { return \App\Support\Steam::class; } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Ledger::balance', Relation::Calls, Confidence::Inferred), 'the docblock of the facade says more than its accessor');
        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls), 'one target');
    }

    public function testAClassNotExtendingFacadeIsNoFacade(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; class Steam { public static function __callStatic(string $name, array $arguments): mixed { return null; } protected static function getFacadeAccessor(): string { return \App\Support\Steam::class; } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertSame(0, $result->facades->facades, 'getFacadeAccessor() alone does not make a facade');
    }

    public function testAnAccessorReturningTwoKeysSaysNothing(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { if (PHP_OS === "Linux") { return \'linux-steam\'; } return \App\Support\Steam::class; } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls), 'the accessor may return another key');
        self::assertSame(['App\Facades\Steam (accessor not read)'], $result->facades->unresolved, 'reported, not guessed');
    }

    public function testAFactoryReturningTwoClassesIsNotRead(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Support/OtherSteam.php' => 'namespace App\Support; class OtherSteam { public function balance(): int { return 1; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \'steam\'; } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->bind(\'steam\', function () { if (PHP_OS === "Linux") { return new Support\OtherSteam(); } return new Support\Steam(); }); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls), 'the factory may build OtherSteam');
        self::assertSame(["App\\Facades\\Steam ('steam' bound by code not read)"], $result->facades->unresolved, 'the binding is not read');
    }

    public function testARouteParameterBindingIsNoContainerBinding(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \'steam\'; } }',
            'routes/web.php' => 'use Illuminate\Support\Facades\Route; Route::bind(\'steam\', fn (string $value) => new \App\Support\Steam());',
        ]);

        self::assertSame(["App\\Facades\\Steam ('steam' bound nowhere)"], $result->facades->unresolved, 'Route::bind() binds a route parameter');
    }

    public function testANamedConcreteArgumentIsTheClassBound(): void
    {
        $result = $this->buildProject([
            'app/Support/BalanceInterface.php' => 'namespace App\Support; interface BalanceInterface { public function balance(): int; }',
            'app/Support/Steam.php' => 'namespace App\Support; class Steam implements BalanceInterface { public function balance(): int { return 0; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; use App\Support\BalanceInterface; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return BalanceInterface::class; } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support\BalanceInterface; use App\Support\Steam; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->singleton(BalanceInterface::class, concrete: Steam::class); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls, Confidence::Inferred), 'concrete: names the class, not the interface itself');
    }

    public function testASubclassWithAKeyNotFollowedDoesNotGetItsParentsClass(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/BaseSteam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class BaseSteam extends Facade { protected static function getFacadeAccessor(): string { return \App\Support\Steam::class; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; class Steam extends BaseSteam { protected static function getFacadeAccessor(): string { return \'other-steam\'; } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls), 'the subclass calls what other-steam is bound to, unknown');
        self::assertSame(['App\Facades\BaseSteam (its subclass App\Facades\Steam is not followed)', "App\\Facades\\Steam ('other-steam' bound nowhere)"], $result->facades->unresolved, 'both say why');
    }

    public function testATestFixtureDeclaringTheFacadeAgainDoesNotReplaceIt(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \App\Support\Steam::class; } }',
            'tests/Fixtures/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \Tests\FakeSteam::class; } }',
            'tests/FakeSteam.php' => 'namespace Tests; class FakeSteam { public function balance(): int { return 9; } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertTrue($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls, Confidence::Inferred), 'the declaration the graph keeps');
    }

    public function testAnEmptyKeyBesideAnUnreadOneIsNotRead(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { if (PHP_OS === "Linux") { return \'\'; } return config(\'steam.key\'); } }',
            'app/Providers/AppServiceProvider.php' => 'namespace App\Providers; use App\Support\Steam; use Illuminate\Support\ServiceProvider; class AppServiceProvider extends ServiceProvider { public function register(): void { $this->app->bind(\'\', Steam::class); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls), 'an empty string is no key');
        self::assertSame(['App\Facades\Steam (accessor not read)'], $result->facades->unresolved, 'the other return is not read');
    }

    public function testASubclassWithAnAccessorNotReadDoesNotGetItsParentsClass(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/BaseSteam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class BaseSteam extends Facade { protected static function getFacadeAccessor(): string { return \App\Support\Steam::class; } }',
            'app/Facades/Steam.php' => 'namespace App\Facades; class Steam extends BaseSteam { protected static function getFacadeAccessor(): string { return config(\'steam.key\'); } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'App\Support\Steam::balance', Relation::Calls), 'the subclass key comes from the configuration');
    }

    public function testAFirstDeclarationWithAnAccessorNotReadIsNotReplacedByAFixture(): void
    {
        $result = $this->buildProject([
            'app/Support/Steam.php' => self::STEAM,
            'app/Facades/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return config(\'steam.key\'); } }',
            'tests/Fixtures/Steam.php' => 'namespace App\Facades; use Illuminate\Support\Facades\Facade; class Steam extends Facade { protected static function getFacadeAccessor(): string { return \Tests\FakeSteam::class; } }',
            'tests/FakeSteam.php' => 'namespace Tests; class FakeSteam { public function balance(): int { return 9; } }',
            'app/Http/Controller.php' => self::CALLER,
        ]);

        self::assertFalse($this->hasEdge($result->graph, 'App\Http\Controller::show', 'Tests\FakeSteam::balance', Relation::Calls), 'the fixture is not the declaration the graph keeps');
    }
}
