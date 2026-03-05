# Community Post Quality Guide

This guide is dedicated to **community post functionality only**.

## What is covered

- Community entities: `Post`, `Reply`, `Reaction`
- Unit tests for core behavior
- Doctrine mapping checks scoped to community entities

## Run community tests

```bash
composer test:community
```

This runs only the `Community` testsuite (`tests/Community`).

## Run community Doctrine checks

```bash
composer doctrine:community:check
```

This validates Doctrine mapping for:

- `App\Entity\Post`
- `App\Entity\Reply`
- `App\Entity\Reaction`

### Optional DB schema sync check

```bash
php bin/console app:community:doctrine-check --with-db
```

Use `--with-db` only when your database connection is available.

## Doctrine Doctor integration

Doctrine Doctor is enabled in dev/test and appears in Symfony Profiler.
Use it while browsing `/community` pages to profile and improve community queries.
