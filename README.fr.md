<h1 align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/docs/brand/logo-dark.svg">
    <img src="https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/docs/brand/logo.svg" alt="phpgraph" width="484">
  </picture>
</h1>

<p align="center"><b>Le graphe de connaissances de votre code PHP, pour les agents IA.</b></p>

<p align="center">
  <a href="https://github.com/alpine-ronin/phpgraph/actions/workflows/ci.yml"><img src="https://github.com/alpine-ronin/phpgraph/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://packagist.org/packages/alpine-ronin/phpgraph"><img src="https://img.shields.io/packagist/v/alpine-ronin/phpgraph" alt="Version Packagist"></a>
  <a href="https://packagist.org/packages/alpine-ronin/phpgraph"><img src="https://img.shields.io/packagist/dt/alpine-ronin/phpgraph" alt="Téléchargements"></a>
  <img src="https://img.shields.io/badge/php-%3E%3D8.5-777bb4" alt="PHP 8.5+ pour l'exécuter depuis les sources">
  <a href="https://github.com/alpine-ronin/phpgraph/blob/main/LICENSE"><img src="https://img.shields.io/badge/licence-MIT-blue" alt="Licence : MIT"></a>
</p>

<p align="center"><a href="https://github.com/alpine-ronin/phpgraph/blob/main/README.md">English</a> · <b>Français</b></p>

---

Un agent de code dans un projet PHP qu'il ne connaît pas cherche des noms avec grep, lit fichier après fichier, et
devine quand la réponse passe par quelque chose qu'une recherche de noms ne montre pas : une interface, le conteneur
de services, un bus de messages, une route. phpgraph analyse le projet une fois et donne la réponse à l'agent à
partir d'un graphe, par MCP, en un appel. Dans les benchmarks, il a été rentable sur le plus grand projet mesuré,
pour une question sur l'impact d'un changement ; sur des projets plus petits, la même question a coûté plus de tokens
avec lui.

## Démo

![démo](docs/assets/demo.gif)

Une vidéo de 68 secondes, la même question à Claude Code avec et sans phpgraph, une vraie exécution de chaque côté :
[youtu.be/Cv4ULkN3FF0](https://youtu.be/Cv4ULkN3FF0).

Pas de LLM, pas de réseau, pas d'embeddings : votre code ne quitte jamais votre machine, et phpgraph l'analyse sans
l'exécuter. Chaque fait dit à quel point il est sûr (`EXTRACTED`, `INFERRED` ou `AMBIGUOUS`), et ce que phpgraph n'a
pas su relier est signalé.

## Où il aide et où il n'aide pas

Akeneo PIM (8 416 fichiers PHP), tâche : *lister toutes les classes à modifier quand
`GetCategoryInterface::byCode()` prend un second paramètre*. Claude Code 2.1.325 avec Sonnet, 15 novembre 2026 :
phpgraph 2.3.0, 6 exécutions ; sans phpgraph, 3 exécutions :

| | Avec phpgraph | Sans |
|---|---:|---:|
| Réponses correctes | 6/6 | 3/3 |
| Tokens par exécution, médiane | 110 254 | 170 660 |
| Tokens par exécution, plage | 87 643 à 181 273 | 127 193 à 176 783 |
| Durée par exécution | 15,9 à 27,9 s | 15,9 à 26,8 s |

Où il aide : la même réponse avec 35 % de tokens en moins (médiane) sur le plus grand projet ; 3 exécutions sur 6 ont
répondu avec `impact_of` seul, les autres l'ont vérifié par une à trois recherches. Où il n'aide pas : sur les 6 tâches
d'impact du benchmark, avec 2.2.0, phpgraph a donné plus de bonnes réponses (17/18 contre 15/18) mais a utilisé 9,8 %
de tokens en plus au total, de −30 % à +36 % selon la tâche, et la durée n'était pas meilleure. Les résultats varient
d'une exécution à l'autre : avec 2.2.0, la même tâche Akeneo a pris de 87 000 à 222 000 tokens.
Tous les résultats, groupés par version, avec la méthode :
[docs/BENCHMARKS.md](docs/BENCHMARKS.md) (en anglais).

## Démarrage rapide

**1. Installer** l'exécutable (Linux, macOS ; il contient son propre PHP, aucun n'est nécessaire) :

```bash
curl -fsSL https://raw.githubusercontent.com/alpine-ronin/phpgraph/main/install.sh | sh
```

Il vérifie le SHA-256 et installe dans `~/.local/bin`. Windows, Composer, PHAR et Docker :
[docs/INSTALL.md](docs/INSTALL.md).

**2. Connecter Claude Code.** Depuis le dossier du projet :

```bash
phpgraph mcp-config claude
```

La commande affiche une commande `claude mcp add phpgraph -- ...` avec les chemins remplis (à lancer), un `.mcp.json`
à partager avec l'équipe à la place, et une ligne pour `CLAUDE.md` qui dit à l'agent d'appeler phpgraph avant grep.
Codex, Cursor, Sherpa et les autres clients MCP : `phpgraph mcp-config codex|cursor|sherpa|json`.

**3. Demander.** Le graphe est construit au premier appel, puis tenu à jour pendant que vous modifiez le code.

## Ce que vous pouvez demander

- **Qu'est-ce qui casse si je change cette méthode ?** `impact_of` liste chaque dépendant, du plus proche au plus
  lointain, y compris les appelants passant par l'interface qu'elle implémente, les routes qui l'atteignent et les
  tests à lancer.
- **Comment marche cette fonctionnalité ?** `outline` donne les classes, les routes qui y mènent, le comportement lu
  dans leur code, le câblage et les tests en une réponse : l'agent ne lit que les fichiers à la logique non évidente.
- **Quel handler reçoit ce message, quel contrôleur sert cette route ?** Envois, messages et handlers sont reliés pour
  Symfony Messenger, Laravel, Ecotone, PrestaShop, les hooks WordPress et vos propres bus ; les routes à leurs
  contrôleurs, les appels HTTP aux routes qu'ils atteignent.
- **Qui appelle ceci, dans un code ancien ou volumineux ?** Les récepteurs sont typés par les types de retour, les
  docblocks, les génériques, les branches et les signatures de `vendor/`, et la syntaxe PHP 4 et 5 est analysée.

## Ce qu'il n'est pas

- **Pas un outil d'exécution.** Il lit le code et la configuration, sans jamais les exécuter : les services construits
  à l'exécution (compiler passes, ids calculés à l'exécution) et les appels dynamiques (`$this->$name()`, `__call`
  sans `@method`) restent non résolus.
- **Pas tous les frameworks.** Les conventions de Symfony, Laravel, Ecotone, PrestaShop et WordPress sont reconnues,
  plus des conventions génériques (attributs de handler, `*Handler`, `*Bus`) ; les autres n'ont que le graphe
  d'appels. Seuls les fichiers PHP sont lus.
- **Meilleur avec `vendor/`.** Sans dépendances installées, les chaînes d'appels s'arrêtent à la première.
- **Versions de PHP.** Le projet analysé peut être de n'importe quelle version de PHP, syntaxe PHP 4 comprise, et son
  PHP n'a pas besoin d'être installé. phpgraph lui-même n'a besoin d'aucun PHP en exécutable ou sous Docker, et de
  PHP 8.5 ou plus récent avec Composer ou le PHAR.

La liste complète : [docs/LIMITATIONS.md](docs/LIMITATIONS.md).

## Documentation

En anglais :

- [Installation](docs/INSTALL.md) : toutes les méthodes, tous les agents
- [Outils MCP](docs/MCP-TOOLS.md) : les 8 outils, 4 listés par défaut
- [Ligne de commande](docs/CLI.md) : `build`, `impact`, `outline`, `check`, `serve` et leurs options
- [Schéma du graphe et niveaux de confiance](docs/GRAPH.md) : relations, `EXTRACTED` / `INFERRED` / `AMBIGUOUS`,
  fonctionnement
- [Règles d'architecture](docs/RULES.md) : couches, `phpgraph check` en CI, `phpgraph.yaml`
- [Frameworks](docs/FRAMEWORKS.md) : ce qui est reconnu, microservices, routage XML par codes d'action
- [Benchmarks](docs/BENCHMARKS.md) : agents avec et sans phpgraph, autres graphes de code, corpus de mesure
- [Limites](docs/LIMITATIONS.md) et [FAQ](docs/FAQ.md)
- [Contribuer](CONTRIBUTING.md), [architecture du code](docs/ARCHITECTURE.md),
  [providers de bibliothèques](docs/PROVIDERS.md), [stabilité](docs/STABILITY.md), [changelog](CHANGELOG.md)
- [L'ancien README](docs/ARCHIVE.md), conservé tel quel

## Licence

[MIT](LICENSE).
