# Itera Guide

[日本語](guide.ja.md)

Itera is a small PHP library for separating four concerns when working with iterable data: **materialized values**, **key/value relationships**, **lazy transformation**, and **aggregation**.

Its core types are:

| Type | Responsibility | Semantics |
| --- | --- | --- |
| `Collection<T>` | Ordered materialized values | reusable |
| `Map<TKey, TValue>` | Materialized key/value relationships | reusable |
| `Sequence<T>` | Lazy transformation pipeline over an iterable | mutable / single-use |
| `Aggregator<T, R>` | Reusable definition that reduces Sequence output to `R` | reusable definition |

The overall data flow looks like this:

```text
iterable
   │
   ▼
Sequence ── map / filter / flatMap / ... ──┐
   │                                        │
   ├─ collect() ───────────────► Collection │
   ├─ associate() ─────────────► Map        │
   ├─ fold() ──────────────────► value      │
   └─ aggregate(Aggregator) ───► result     │
                                            │
Collection ── sequence() ───────────────────┘
```

With PHP 8.5's pipe operator, the same flow can be written directly from left to right.

---

## Collection

`Collection<T>` is a materialized, ordered collection of values.

```php
use Itera\Collection;

$users = Collection::of($user1, $user2, $user3);

$first = $users->first();
$active = $users->find(
    fn (User $user): bool => $user->isActive(),
);
```

Because a `Collection` owns materialized values, it can be read repeatedly. Its responsibility is querying and accessing values that already exist in memory.

Main operations:

| Operation | Description |
| --- | --- |
| `of(...$values)` | Create a Collection from values |
| `from($iterable)` | Materialize an iterable into a Collection |
| `empty()` | Create an empty Collection |
| `at($index)` | Read a value by index; negative indexes count from the end |
| `first()` / `last()` | Read the first or last value |
| `count()` / `isEmpty()` | Count values or test for emptiness |
| `contains($value)` | Find a value using strict comparison |
| `find($predicate)` | Return the first matching value |
| `indexOf($value)` | Return the index of a strictly equal value |
| `findIndex($predicate)` | Return the first matching index |
| `any($predicate)` / `all($predicate)` | Existential / universal queries |
| `associate($keySelector)` | Build a `Map` using keys derived from values |
| `values()` | Return the underlying `list<T>` |
| `sequence()` | Start a lazy `Sequence<T>` pipeline |

### Collection boundary

Itera intentionally does not put transformation APIs such as `map()` and `filter()` on `Collection`.

When transformation starts, move to `Sequence`:

```php
$result = $users
    ->sequence()
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

This keeps the roles explicit:

```text
Collection = values that already exist
Sequence   = values that are about to be processed
```

### associate

`associate()` keeps each Collection value as the Map value and derives its key with the selector.

```php
$usersById = $users->associate(
    fn (User $user): string => $user->id(),
);
```

If the same key is produced more than once, the later value replaces the earlier one.

---

## Map

`Map<TKey, TValue>` is a materialized key/value collection.

```php
use Itera\Map;

$usersById = Map::from([
    'u1' => $user1,
    'u2' => $user2,
]);

$user = $usersById->get('u1');
```

Where `Collection` represents ordered values, `Map` represents relationships between keys and values.

Main operations:

| Operation | Description |
| --- | --- |
| `from($array)` | Create a Map from a PHP array |
| `empty()` | Create an empty Map |
| `get($key)` | Read the value for a key |
| `has($key)` | Test whether a key exists |
| `keys()` | Project keys as a `Collection` |
| `values()` | Project values as a `Collection` |
| `entries()` | Project `[key, value]` pairs as a `Collection` |
| `count()` / `isEmpty()` | Count entries or test for emptiness |
| `raw()` | Return the underlying `array<TKey, TValue>` |

`get()` returns `null` both when a key is missing and when that key stores `null`. Use `has()` when the distinction matters.

```php
if ($map->has($key)) {
    $value = $map->get($key);
}
```

Iteration preserves original keys:

```php
foreach ($usersById as $id => $user) {
    // $id and $user are preserved.
}
```

`Map` itself does not provide transformation operators. Use `keys()`, `values()`, or `entries()` to project into a `Collection` when further value-oriented processing is needed.

---

## Sequence

`Sequence<T>` is a **lazy transformation pipeline** over an iterable.

```php
use Itera\Sequence;

$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): Profile => $user->profile())
    ->map(fn (Profile $profile): string => $profile->name())
    ->take(10)
    ->collect();
```

Registering `map()`, `filter()`, and other operators does not read the source or execute callbacks. Consumption starts only when a terminal operation such as `collect()`, `associate()`, `fold()`, `aggregate()`, or `getIterator()` begins.

### Mutable and single-use

Sequence operators append work to the same Sequence instance rather than building an immutable chain of new instances.

```php
$sequence = Sequence::from($values);

$sequence->filter($predicate);
$sequence->map($mapper);
```

A Sequence can only be consumed once:

```php
$sequence = Sequence::from([1, 2, 3]);

$values = $sequence->collect();

// The Sequence has already been consumed.
// $sequence->collect();
```

Once consumption starts, the Sequence cannot be reused, including after early termination or an exception.

This is the main semantic difference from materialized `Collection` and `Map` values.

### Creating a Sequence

```php
Sequence::from($iterable);
Sequence::of(1, 2, 3);
Sequence::empty();
```

Passing an existing Sequence to `Sequence::from()` returns that same instance.

### Transformation operators

| Operator | Description |
| --- | --- |
| `map($mapper)` | Transform each value |
| `filter($predicate)` | Keep values whose predicate result is truthy |
| `flatMap($mapper)` | Map each value to an iterable and flatten its values |
| `scan($initial, $step)` | Update state and emit each updated state |
| `tap($effect)` | Run a side effect and forward the original value |
| `chunk($size)` | Materialize consecutive values into Collections |

`scan()` does not emit its initial state. It emits the updated state after each input value.

```php
$totals = Sequence::from([1, 2, 3])
    ->scan(0, fn (int $state, int $value): int => $state + $value)
    ->collect()
    ->values();

// [1, 3, 6]
```

`tap()` is useful for logging, tracing, or other effects that should not change the value flowing through the pipeline.

```php
$result = Sequence::from([1, 2, 3])
    ->tap(function (int $value) use ($logger): void {
        $logger->debug((string) $value);
    })
    ->filter(fn (int $value): bool => $value > 1)
    ->collect();
```

`chunk()` creates a fresh Collection for each chunk.

```php
$chunks = Sequence::from([1, 2, 3, 4, 5])
    ->chunk(2)
    ->collect()
    ->values();

// Collection([1, 2]), Collection([3, 4]), Collection([5])
```

### Boundary and stopping operators

| Operator | Description |
| --- | --- |
| `take($count)` | Emit at most `count` values |
| `drop($count)` | Omit the first `count` values |
| `until($predicate)` | Emit through the first matching value, then stop |
| `skipUntil($predicate)` | Skip until the first match, then emit that value and everything after it |

`until()` can stop reading upstream entirely, which makes it useful for infinite or expensive sources.

```php
$result = Sequence::from($stream)
    ->until(fn (Message $message): bool => $message->isLast())
    ->collect();
```

### Terminal operations

Terminal operations consume the Sequence.

| Terminal | Result |
| --- | --- |
| `collect()` | `Collection<T>` |
| `associate($keySelector)` | `Map<TKey, T>` |
| `fold($initial, $step)` | Arbitrary state value |
| `aggregate($aggregator)` | Result defined by the Aggregator |
| `getIterator()` | `Traversable<int, T>` |

`collect()`, `associate()`, `fold()`, and Aggregators that require all input do not terminate on an infinite source. Use `take()` or `until()` first when the pipeline must become finite.

### concat

`Sequence::concat()` lazily concatenates multiple Sequences in declaration order.

```php
$values = Sequence::concat(
    Sequence::from([1, 2]),
    Sequence::from([3]),
)
    ->collect()
    ->values();

// [1, 2, 3]
```

Inputs are not copied into an array of values. The next Sequence starts when the previous Sequence has finished.

Operators added after concatenation apply to the combined output:

```php
$result = Sequence::concat($first, $second)
    ->filter($predicate)
    ->map($mapper)
    ->take(10)
    ->collect();
```

If an infinite Sequence comes first, later inputs are unreachable unless the first input is made finite itself.

---

## Aggregator

`Aggregator<T, R>` is a **reusable aggregation definition** that consumes `Sequence<T>` output and produces `R`.

```php
use function Itera\Aggregator\sum;

$total = Sequence::from([10, 20, 30])
    ->aggregate(sum());

// 60
```

An Aggregator is a definition, not the mutable execution state of one aggregation.

```php
$sum = sum();

$a = Sequence::from([1, 2, 3])->aggregate($sum);
$b = Sequence::from([10, 20])->aggregate($sum);
```

The same Aggregator can be applied to multiple Sequences. Each execution receives fresh state.

### Basic Aggregators

| Aggregator | Result |
| --- | --- |
| `count()` | Number of values |
| `any($predicate)` | Whether any value matches |
| `all($predicate)` | Whether every value matches |
| `collect()` | `Collection` |
| `associate($keySelector)` | `Map` |
| `folding($initial, $step)` | Final fold state |

`any()` can stop at the first truthy result. `all()` can stop at the first falsy result.

### Transforming Aggregators

| Aggregator | Result |
| --- | --- |
| `mapping($mapper)` | Collection of mapped values |
| `filtering($predicate)` | Collection of matching values |
| `flatMapping($mapper)` | Flattened Collection |
| `scanning($initial, $step)` | Collection of updated states |

These resemble Sequence operators, but they serve a different role: they create the **final aggregation result** instead of changing the downstream Sequence pipeline.

For example, `Sequence::map()` changes the values seen by the next operator, while `mapping()` returns a mapped Collection as the Aggregator result.

### Numeric Aggregators

| Aggregator | Result |
| --- | --- |
| `sum()` | Sum |
| `min()` | Minimum value, or `null` for empty input |
| `max()` | Maximum value, or `null` for empty input |
| `average()` | Arithmetic mean, or `null` for empty input |

### Query and grouping Aggregators

| Aggregator | Result |
| --- | --- |
| `find($predicate)` | First matching value, or `null` |
| `first()` | First value, or `null` |
| `unique()` | Collection of first strictly equal occurrences |
| `groupBy($keySelector)` | `Map<TKey, Collection<T>>` |
| `partition($predicate)` | `matched` / `unmatched` Collections |
| `countBy($keySelector)` | Map of counts by key |
| `join($separator)` | Joined string |

### combine

`combine()` applies multiple named Aggregators during **one traversal** of the Sequence output.

```php
use function Itera\Aggregator\average;
use function Itera\Aggregator\combine;
use function Itera\Aggregator\count as counting;
use function Itera\Aggregator\sum;

$stats = Sequence::from([10, 20, 30])
    ->aggregate(combine(
        count: counting(),
        sum: sum(),
        average: average(),
    ));

// [
//     'count' => 3,
//     'sum' => 60,
//     'average' => 20.0,
// ]
```

Rather than traversing the Sequence three times, each value is forwarded to each child aggregation execution and the final results are returned together.

`combine()` accepts named Aggregators only. A combined Aggregator cannot contain another combined Aggregator.

### Custom Aggregators

Use `Aggregator::custom()` when a reusable aggregation is not covered by the built-ins.

The custom factory returns a fresh `AggregatorExecution<T, R>` for each execution.

```php
use Itera\Aggregator;
use Itera\AggregatorExecution;

$aggregator = Aggregator::custom(
    fn () => new class implements AggregatorExecution {
        private int $sum = 0;

        public function advance(mixed $value): void
        {
            $this->sum += $value;
        }

        public function isComplete(): bool
        {
            return false;
        }

        public function finish(): mixed
        {
            return $this->sum;
        }
    },
);
```

`AggregatorExecution` has three responsibilities:

- `advance($value)` receives input while the execution is incomplete.
- `isComplete()` reports whether aggregation can stop early.
- `finish()` returns the final result.

The factory is not invoked when the Aggregator definition is created. It is invoked once per execution when the Aggregator is actually applied to a Sequence.

---

## Pipe

Itera provides adapters in the `Itera\Pipe` namespace for PHP 8.5's pipe operator.

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

This represents the same processing flow as:

```php
$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

The pipe form makes the flow read directly from input to result:

```text
iterable
  |> sequence()
  |> transformation
  |> transformation
  |> terminal
```

### sequence

`sequence()` adapts `iterable<T>` to `Sequence<T>`.

```php
$result = $values
    |> sequence()
    |> map($mapper)
    |> collect();
```

This allows a pipe to start directly from arrays, Generators, Collections, or other iterables.

### Operator adapters

The following functions return Closures that apply the corresponding Sequence operator:

```text
map()
scan()
tap()
chunk()
filter()
flatMap()
until()
skipUntil()
take()
drop()
```

For example:

```php
$result = $values
    |> sequence()
    |> filter($predicate)
    |> map($mapper)
    |> take(10)
    |> collect();
```

Each adapter adds work to the same mutable Sequence pipeline.

### Terminal adapters

The following functions consume a Sequence and return a result:

```text
collect()
associate()
fold()
aggregate()
getIterator()
```

Aggregators fit naturally at the end of a pipe:

```php
use function Itera\Aggregator\combine;
use function Itera\Aggregator\count as counting;
use function Itera\Aggregator\sum;
use function Itera\Pipe\aggregate;
use function Itera\Pipe\filter;
use function Itera\Pipe\sequence;

$stats = $values
    |> sequence()
    |> filter(fn (int $value): bool => $value > 0)
    |> aggregate(combine(
        count: counting(),
        sum: sum(),
    ));
```

### concat

`concat()` is different from the Closure-producing adapters. It is a composer that accepts multiple Sequences and immediately returns a new concatenated Sequence.

```php
use function Itera\Pipe\concat;

$sequence = concat(
    Sequence::from([1, 2]),
    Sequence::from([3, 4]),
);
```

Normal pipe processing can continue from the result:

```php
$result = concat($first, $second)
    |> map($mapper)
    |> collect();
```

---

## Choosing a type

Use `Collection` when values are already available and should be reusable.

```php
$items = Collection::from($values);
```

Use `Map` when key/value identity matters.

```php
$itemsById = Map::from($valuesById);
```

Use `Sequence` when an iterable should be transformed lazily.

```php
$result = Sequence::from($source)
    ->filter($predicate)
    ->map($mapper)
    ->take(100)
    ->collect();
```

This is particularly useful with Generators, large iterables, and pipelines that can terminate early.

Use `Aggregator` when Sequence output should be reduced into a reusable aggregation result.

```php
$result = $sequence->aggregate($aggregator);
```

It is especially useful when the aggregation definition itself should be reusable or when `combine()` can produce multiple results from one traversal.

---

## Design boundaries

Itera intentionally makes several boundaries explicit.

### Materialized vs. lazy

`Collection` and `Map` are materialized.

`Sequence` is lazy.

The type communicates whether values are already owned and reusable or are still part of a processing pipeline.

### Reusable values vs. single-use pipeline

`Collection` and `Map` can be read repeatedly.

`Sequence` cannot be reused after consumption begins.

`Aggregator` is reusable because it describes an aggregation rather than storing one execution's mutable state.

### Transformation vs. aggregation

Sequence operators transform the stream seen by later operators.

Aggregators consume the stream and produce a final result.

```text
Sequence operator
    input -> transformed stream -> next operator

Aggregator
    input -> aggregation state -> final result
```

This separation keeps intermediate transformation distinct from terminal aggregation.

---

## Typical flows

### Start from a Collection

```php
$result = $users
    ->sequence()
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

### Start from a PHP array with pipe

```php
$result = $users
    |> sequence()
    |> filter(fn (User $user): bool => $user->isActive())
    |> map(fn (User $user): string => $user->name())
    |> collect();
```

### Produce several results in one traversal

```php
$stats = $prices
    |> sequence()
    |> aggregate(combine(
        count: counting(),
        sum: sum(),
        average: average(),
    ));
```

The basic Itera flow is: **start from materialized values or an iterable, enter a Sequence only when processing is needed, transform lazily, then materialize or aggregate at the boundary where a result is required.**
