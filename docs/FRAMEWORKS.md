# Frameworks and conventions

- **Call graph with type inference**: return types, chained calls (`$a->b()->c()`), local variables, docblocks (`@return`, `@var`), and signatures of your `vendor/` dependencies, so chains go through Doctrine, Symfony or Laravel APIs.
- **Architecture**: DDD and hexagonal layers, bounded contexts, layer rules checked in CI (`phpgraph check`), and impact analysis (`impact_of`): what depends on a class or method, and which tests to run.
- **Messages and events**: sender → message → handler for Symfony Messenger, Laravel jobs and events, Ecotone, PrestaShop CQRS, WordPress hooks, domain events and your own buses, from attributes, service configuration (YAML, XML, PHP) or code shape.
- **HTTP**: routes (Symfony attributes, YAML and PHP routing files, API Platform resources, Laravel route files, controllers named by container service id) linked to controllers, and HTTP calls linked to the routes they reach. A route whose controller class exists neither in the project nor in `vendor/` is reported with its routing file.
- **Dependency injection**: what the Symfony container configuration injects beyond constructor types, every service of a tag (`tagged_iterator`, `#[AutowireIterator]`) or a service named by id, from PHP, YAML or XML configuration, including helpers called with literal arguments (`Wiring::wire($services, 'sales', ...)`, evaluated, never run): a validator is linked to the rules it receives. A rule declaring what it accepts (`supports(object $input): bool { return $input instanceof CreateEstimateQuery; }`, also `canHandle`, `accepts`…) is kept out of the use cases passing another input to the shared pipeline, the classes the input's properties hold included. An input typed by an interface is any project class implementing it and the parameter types it is passed on to (`QueryInterface $query` given to `setUp(CreateEstimateQueryInterface $query)`: a `CreateEstimateQuery`); a guard it cannot read, or a rule running for some of these classes only, is kept, marked `supports() not resolved`.
- **Microservices**: one id space per service, and links between services through their contracts: shared message classes, routing keys, HTTP routes.
- **API schemas**: the operations of OpenAPI (Swagger) documents linked to the routes implementing them, or, for a document of another API, to the HTTP calls of its client; the rpcs of protobuf services linked to the classes implementing their generated interface. What is documented and not implemented, and the routes no document describes, are reported. `impact` lists the operations behind a change (the contract its clients rely on), `outline` those a feature serves.
- **Zero-configuration MCP server**: built on first use, rebuilt incrementally when the code changes (about 3 s per edit on an 8,400-file project).

## Matrix

| | Handlers and listeners | Messages sent | HTTP |
|---|---|---|---|
| **Symfony** | `#[AsMessageHandler]`, `#[AsEventListener]`, tags `messenger.message_handler` and `kernel.event_listener` (YAML, XML, PHP, `_instanceof`), `getSubscribedEvents()`, `addListener()` | `MessageBusInterface`, `EventDispatcherInterface`, named events | `#[Route]` (paths in class constants too), YAML and PHP routing files, controllers named by service id; API Platform `#[ApiResource]` and operation attributes, handled by their provider, processor or controller |
| **Laravel** | `$listen`, `Event::listen()`, subscribers (`$events->listen()` in `subscribe()`, or the map it returns), listeners of a union of events (`handle(Accepted\|Rejected $event)`), jobs (`ShouldQueue`, `Dispatchable`) | `event()`, `dispatch()`, facades, `Job::dispatch()` | `Route::get/post/…`, `match`, `resource`, `view`, `redirect`, groups with `prefix` and `domain`, `routes/api.php`; `Http::` client, relative to its `baseUrl()` |
| **Ecotone** | `#[CommandHandler]`, `#[EventHandler]`, `#[QueryHandler]`, routing keys | `CommandBus`, `EventBus`, `DistributedBus`, `sendWithRouting()` | |
| **PrestaShop** | `#[AsCommandHandler]`, `#[AsQueryHandler]` | `CommandBusInterface::handle()` | YAML routing |
| **WordPress** | `add_action()`, `add_filter()` | `do_action()`, `apply_filters()` | |
| **Your own code** | handler attributes and tags recognised by name, `__invoke`/`handle(Message $m)`, `*Handler` classes | `*Bus`, `*Dispatcher`, `*Publisher` types, aggregates' `recordThat()`, your bus wrappers | Guzzle, Symfony HttpClient, PSR-18 |

## Laravel

Measured on Firefly III (1,382 files) and BookStack, both in the corpus:

- **Facades**: a static call on a facade goes to the class behind it. Its `@mixin` and `@method` tags are read first. Without them, `getFacadeAccessor()` is read: a `X::class` it returns, or a key the service providers bind (`$this->app->bind('steam', static fn (): Steam => new Steam())`, `singleton()`, `scoped()`, `instance()`, their `*If` forms, the `$bindings` and `$singletons` arrays) to `X::class`, `new X` or a closure that returns one. On Firefly III this adds 1,289 INFERRED calls (`Steam::`, `Preferences::`, `Amount::`…). `impact` of `Steam::bcround()` went from 0 to 91 classes. `overview` counts the facades and names those it could not follow, with the reason: a key bound nowhere, bound to two classes, or bound by code it does not read.
- **Eloquent models**: casts (`'paid_at' => 'datetime'` is a Carbon), timestamps and relations read as properties (`$page->book`) type the chains on them. A relation method (`return $this->hasMany(Page::class)`) is a `relates_to` edge to the related model, and `outline` of a model names the models it relates to (`[hasMany by Book::pages]`), even when they are members of a family.
- **Container**: `app(X::class)`, and `app()->make(X::class)` give an `X`. A call through an interface reaches its implementations: on Firefly III, 46 of the 47 interfaces the providers bind have a single implementation, so this is already the class the binding gives.
- **Events, jobs, routes, HTTP client**: see the matrix above.

## Microservices

A repository holding two or more independent Composer applications is split into services. Packages of a monorepo stay in their project: a package the root autoloads, declares as a path repository or `replace`s is a part, not a service; a library the services require (a path repository, or its package name) is their common code. Ids then carry their service, `billing@App\Domain\Order`, and a name never resolves into another service: services meet through contracts.

- the same message class sent by one service and handled by another: `contract`;
- a routing key (`sendWithRouting('order.place', …)`, `#[CommandHandler('ticket.create')]`, a named event): a `channel:` node shared by all services;
- an HTTP call to a route of another service: `requests`. A call whose path matches a route declared for other HTTP methods only is reported: it would fail as written;
- an API schema: the `operation:` nodes of an OpenAPI document (`GET /api/hotels/{hotel}`) or of a protobuf service (`CinemaService.Reserve`), `implemented_by` the routes or classes serving them. A document at the root of the repository describes every service (an API gateway), one in a service that service only.

An optional `phpgraph.yaml` names the services when detection does not fit:

```yaml
services:
  - services/billing
  - services/shipping
```

## Routing files of your own: action codes in XML

Legacy applications often route by an action code the screen sends (`value="saveReservation"`), mapped to a controller method by an XML file of their own. `phpgraph.yaml` describes the format, nothing is hard-coded:

```yaml
actions:
  - file: Framework/jacActions.xml
    element: //action                                 # XPath of the action elements
    code: code                                        # their attributes (these are the defaults)
    controller: controller
    method: method
    controller_file: '{dir}/control/{name}CTRL.php'   # _Stock/Reservation → _Stock/control/ReservationCTRL.php
    target: target                                    # the action or template it goes on to
    forward: { element: forward, result: result, target: target }
    cited_in: [html, js]                              # the screens quoting the codes
```

Each action is a route node, `ACTION saveReservation`, `handled_by` its method (EXTRACTED), and referencing its controller (`via` `controller`) when the method is inherited. Its target and forwards are `references` to the next action or to the template (`_Stock/SO_080/Resa.html` finds `_Stock/views/SO_080/Resa.html`), `via` `target` or `forward KO`. A file of `cited_in` quoting a code (`value="saveReservation"`, `'saveReservation&id='`) references the action, AMBIGUOUS: `impact_of` lists those screens under the route. A code that is a plain word (`default`, `login`) is not looked for, nor are minified files. `overview` counts the actions and lists those whose controller or method is missing; `outline` of a method starts its flow from its action; `query_graph("saveReservation routes")` finds it. `controller_class: '{name}CTRL'` finds the class by name instead of by file.
