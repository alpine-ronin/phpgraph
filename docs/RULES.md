# Architecture rules (`phpgraph check`, `phpgraph.yaml`)

The layer of a class is read from its namespace: the first explicit segment (`Domain`, `Application`, `Infrastructure`, `UI`, `Presentation`, `Port`, `Adapter`, `UseCase`), or else the last generic one (`Model`, `Entity`, `Controller`, `Http`, `Persistence`…). The bounded context is read before the layer (`CodelyTv\Mooc\Courses\Domain`) or after it when the project puts the layer first (`Core\Domain\Product`).

`phpgraph check` applies the default rules of a layered architecture to application code: `domain` must not depend on `application`, `infrastructure` or `interface`; `application` and `port` must not depend on `infrastructure` or `interface`. On a project that already has violations:

```bash
phpgraph check --generate-baseline   # accept today's violations in phpgraph-baseline.json
phpgraph check                       # fail on new ones only
```

The project's own rules go in `phpgraph.yaml`, checked by the same command, with the same baseline. Patterns are globs on class names (`*` within a namespace segment, `**` across segments), and a namespace ending with `\` is everything in it:

```yaml
rules:
  dependencies:                       # who may depend on what
    - name: doctrine-in-infrastructure
      dependency: 'Doctrine\'
      allow-from: ['App\*\Infrastructure\']  # or deny-from, which wins
      reason: Only infrastructure talks to the database.
  structure:                          # what the classes a pattern selects must be
    - on: 'App\**\Controller\*'
      not-on: 'App\Legacy\**'
      target: class                   # class, interface, trait or enum
      must-be-final: true             # must-be-abstract, must-be-readonly: true or false
      must-be-named: '*Controller'
      must-implement: ['App\Shared\Http\Controller']   # must-extend, must-use-trait
      only-public-methods: [__construct, __invoke]
  handlers:                           # a message handler dispatches the messages of its own bounded context
    own-context: true
    shared: [Shared]                  # contexts any handler may use
```

Dependencies are the certain ones (EXTRACTED and INFERRED: types, attributes, `new`, calls, inheritance) of application code; inheritance is read from the graph, so the parents of a class of `vendor/` are not known. The bounded context of a handler and of a message is read from their namespaces, as for the layers.
