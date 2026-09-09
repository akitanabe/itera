# Itera

[日本語](README.ja.md)

Itera is a small PHP 8.5 library for working with collections, lazy sequences, and aggregations through explicit data-processing boundaries.

It separates four responsibilities that are often mixed together in collection APIs:

| Type | Responsibility |
| --- | --- |
| `Collection<T>` | Reusable, materialized, ordered values |
| `Map<TKey, TValue>` | Reusable, materialized key/value relationships |
| `Sequence<T>` | Lazy, mutable, single-use transformation pipeline |
| `Aggregator<T, R>` | Reusable definition for reducing Sequence output to a result |

## Core types

### Collection

`Collection<T>` represents values that have already been materialized and can be read repeatedly.

It provides value-oriented queries such as `first()`, `find()`, `contains()`, `any()`, and `all()`. When transformation is needed, `sequence()` moves processing into a lazy `Sequence`.

```php
$activeUsers = $users
    ->sequence()
    ->filter(fn (User $user): bool => $user->isActive())
    ->collect();
```

### Map

`Map<TKey, TValue>` represents materialized key/value relationships.

It provides lookup through `get()` and `has()`, and projections through `keys()`, `values()`, and `entries()`.

```php
$usersById = Map::from([
    'u1' => $user1,
    'u2' => $user2,
]);

$user = $usersById->get('u1');
```

### Sequence

`Sequence<T>` represents a lazy transformation pipeline over an iterable.

Operators such as `map()`, `filter()`, `flatMap()`, `take()`, and `until()` are registered without reading the source. Processing starts when the Sequence is consumed by a terminal operation such as `collect()`, `associate()`, `fold()`, or `aggregate()`.

A Sequence is mutable and single-use: once consumption starts, it cannot be reused.

```php
$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->take(10)
    ->collect();
```

### Aggregator

`Aggregator<T, R>` is a reusable definition for consuming Sequence output and producing a result.

Built-in aggregators include counting, numeric aggregation, searching, grouping, materialization, and more. `combine()` can run several aggregations during a single traversal.

```php
$stats = Sequence::from([10, 20, 30])
    ->aggregate(combine(
        count: counting(),
        sum: sum(),
        average: average(),
    ));
```

Unlike a Sequence, an Aggregator is reusable. Each execution receives fresh aggregation state.

## Pipe

Itera provides functions in the `Itera\Pipe` namespace for PHP 8.5's pipe operator.

```php
use function Itera\Pipe\collect;
use function Itera\Pipe\filter;
use function Itera\Pipe\map;
use function Itera\Pipe\sequence;

$result = $users
    |> sequence()
    |> filter(fn (User $user): bool => $user->isActive())
    |> map(fn (User $user): string => $user->name())
    |> collect();
```

This is the same processing model as the method-chain form:

```php
$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

The pipe form makes the data flow explicit from left to right:

```text
iterable
  |> sequence()
  |> transformation
  |> transformation
  |> terminal
```

Sequence operators and terminal operations have corresponding pipe adapters, so method chains and pipe expressions use the same underlying semantics.

## Documentation

For detailed semantics, available operations, aggregation behavior, custom Aggregators, and pipe adapters, see the [Itera Guide](docs/guide.md).

Japanese documentation is available in [README.ja.md](README.ja.md) and [docs/guide.ja.md](docs/guide.ja.md).

## Requirements

- PHP 8.5 or later

## License

MIT
