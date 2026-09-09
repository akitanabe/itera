# Itera Guide

[English](guide.md)

Itera は、PHP の iterable を扱う処理を、**materialized な値**、**key/value の対応**、**lazy な変換**、**集約**に分けて扱うための小さなライブラリです。

中心となる型は次の4つです。

| 型 | 役割 | 性質 |
| --- | --- | --- |
| `Collection<T>` | 順序付きの値集合 | materialized / reusable |
| `Map<TKey, TValue>` | key と value の対応 | materialized / reusable |
| `Sequence<T>` | iterable に対する変換パイプライン | lazy / mutable / single-use |
| `Aggregator<T, R>` | Sequence の出力を `R` へ集約する定義 | reusable definition |

大まかなデータフローは次のようになります。

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

PHP 8.5 の pipe operator を使う場合も、この流れをそのまま左から右へ記述できます。

---

## Collection

`Collection<T>` は、順序付きの値を materialize して保持するコレクションです。

```php
use Itera\Collection;

$users = Collection::of($user1, $user2, $user3);

$first = $users->first();
$active = $users->find(
    fn (User $user): bool => $user->isActive(),
);
```

`Collection` は一度 materialize された値を保持するため、繰り返し参照できます。値の検索や位置の取得など、配列上で自然に完結する query を担当します。

主な操作は次のとおりです。

| 操作 | 説明 |
| --- | --- |
| `of(...$values)` | 値から Collection を作成する |
| `from($iterable)` | iterable を materialize して Collection を作成する |
| `empty()` | 空の Collection を作成する |
| `at($index)` | index の値を取得する。負数は末尾から数える |
| `first()` / `last()` | 先頭・末尾の値を取得する |
| `count()` / `isEmpty()` | 要素数・空判定 |
| `contains($value)` | strict comparison で値を検索する |
| `find($predicate)` | 条件に一致する最初の値を取得する |
| `indexOf($value)` | strict comparison で値の index を取得する |
| `findIndex($predicate)` | 条件に一致する最初の index を取得する |
| `any($predicate)` / `all($predicate)` | 条件の existential / universal query |
| `associate($keySelector)` | 値から key を生成して `Map` に変換する |
| `values()` | `list<T>` を取得する |
| `sequence()` | lazy な `Sequence<T>` へ移る |

### Collection の責務

Itera では `Collection` に `map()` や `filter()` のような変換 API を持たせていません。

値の変換を始める場合は `sequence()` で `Sequence` に移ります。

```php
$result = $users
    ->sequence()
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

この分離により、`Collection` は「すでに存在する値を扱う型」、`Sequence` は「これから値を処理する型」という役割になります。

### associate

`associate()` は値自身を value とし、selector が返した値を key とする `Map` を作ります。

```php
$usersById = $users->associate(
    fn (User $user): string => $user->id(),
);
```

同じ key が複数回生成された場合は、後から現れた値が残ります。

---

## Map

`Map<TKey, TValue>` は key と value の対応を materialize して保持する型です。

```php
use Itera\Map;

$usersById = Map::from([
    'u1' => $user1,
    'u2' => $user2,
]);

$user = $usersById->get('u1');
```

`Collection` が順序付きの「値」を表すのに対して、`Map` は「対応関係」を表します。

主な操作は次のとおりです。

| 操作 | 説明 |
| --- | --- |
| `from($array)` | PHP array から Map を作成する |
| `empty()` | 空の Map を作成する |
| `get($key)` | key に対応する値を取得する |
| `has($key)` | key の存在を確認する |
| `keys()` | key を `Collection` として取得する |
| `values()` | value を `Collection` として取得する |
| `entries()` | `[key, value]` の組を `Collection` として取得する |
| `count()` / `isEmpty()` | 要素数・空判定 |
| `raw()` | 元の `array<TKey, TValue>` を取得する |

`get()` は、key が存在しない場合と、値として `null` が保存されている場合のどちらでも `null` を返します。

この2つを区別する必要がある場合は `has()` を使います。

```php
if ($map->has($key)) {
    $value = $map->get($key);
}
```

反復時には元の key/value が保持されます。

```php
foreach ($usersById as $id => $user) {
    // $id と $user をそのまま利用できる
}
```

`Map` 自身には変換 API を持たせず、必要に応じて `keys()`、`values()`、`entries()` で `Collection` に投影してから処理します。

---

## Sequence

`Sequence<T>` は iterable に対する **lazy な変換パイプライン**です。

```php
use Itera\Sequence;

$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): Profile => $user->profile())
    ->map(fn (Profile $profile): string => $profile->name())
    ->take(10)
    ->collect();
```

`map()` や `filter()` を登録した時点では source の読み取りや callback の実行は始まりません。`collect()`、`associate()`、`fold()`、`aggregate()`、`getIterator()` などで消費が始まったときに、初めて source が処理されます。

### mutable / single-use

`Sequence` の operator は、新しい Sequence を逐次生成するのではなく、同じ Sequence に pipeline を追加します。

```php
$sequence = Sequence::from($values);

$sequence->filter($predicate);
$sequence->map($mapper);
```

一方で、`Sequence` は **一度だけ消費できる**型です。

```php
$sequence = Sequence::from([1, 2, 3]);

$values = $sequence->collect();

// すでに消費済みなので再利用できない
// $sequence->collect();
```

途中終了や例外が発生した場合も、消費を開始した Sequence は再利用できません。

これは `Collection` のような materialized value container と `Sequence` の大きな違いです。

### Sequence の生成

```php
Sequence::from($iterable);
Sequence::of(1, 2, 3);
Sequence::empty();
```

`Sequence::from()` に既存の `Sequence` を渡した場合は、そのインスタンス自身が返ります。

### 変換 operator

| operator | 説明 |
| --- | --- |
| `map($mapper)` | 各値を別の値へ変換する |
| `filter($predicate)` | predicate が truthy の値だけを通す |
| `flatMap($mapper)` | 各値を iterable へ変換し、その要素を平坦化する |
| `scan($initial, $step)` | state を更新しながら各更新後 state を出力する |
| `tap($effect)` | side effect を実行し、元の値をそのまま通す |
| `chunk($size)` | 連続した値を `Collection` 単位にまとめる |

`scan()` は initial value 自体を出力せず、入力ごとに更新された state を出力します。

```php
$totals = Sequence::from([1, 2, 3])
    ->scan(0, fn (int $state, int $value): int => $state + $value)
    ->collect()
    ->values();

// [1, 3, 6]
```

`tap()` は値を変更しないため、logging や観測などを pipeline の途中へ差し込む用途に使えます。

```php
$result = Sequence::from([1, 2, 3])
    ->tap(function (int $value) use ($logger): void {
        $logger->debug((string) $value);
    })
    ->filter(fn (int $value): bool => $value > 1)
    ->collect();
```

`chunk()` は chunk ごとに新しい `Collection` を生成します。

```php
$chunks = Sequence::from([1, 2, 3, 4, 5])
    ->chunk(2)
    ->collect()
    ->values();

// Collection([1, 2]), Collection([3, 4]), Collection([5])
```

### 範囲・終了 operator

| operator | 説明 |
| --- | --- |
| `take($count)` | 最大 count 件まで出力する |
| `drop($count)` | 先頭 count 件を捨てる |
| `until($predicate)` | 最初に一致した値を含めて出力し、そこで停止する |
| `skipUntil($predicate)` | 最初に一致した値まで捨て、その値を含めて以後を出力する |

`until()` は upstream の読み取り自体を停止できるため、無限 iterable や高コストな source に対しても利用できます。

```php
$result = Sequence::from($stream)
    ->until(fn (Message $message): bool => $message->isLast())
    ->collect();
```

### terminal operation

Sequence の値を実際に消費する操作です。

| terminal | 結果 |
| --- | --- |
| `collect()` | `Collection<T>` |
| `associate($keySelector)` | `Map<TKey, T>` |
| `fold($initial, $step)` | 任意の state |
| `aggregate($aggregator)` | Aggregator が定義する結果 |
| `getIterator()` | `Traversable<int, T>` |

`collect()`、`associate()`、`fold()` と、全入力を必要とする Aggregator は、入力が無限の場合には終了しません。必要に応じて `take()` や `until()` で先に有限化します。

### concat

`Sequence::concat()` は複数の Sequence を宣言順に遅延連結します。

```php
$values = Sequence::concat(
    Sequence::from([1, 2]),
    Sequence::from([3]),
)
    ->collect()
    ->values();

// [1, 2, 3]
```

入力は配列へ materialize されず、前の Sequence が終わった時点で次の Sequence の読み取りが始まります。

連結後に登録した operator は、連結された全体の出力に適用されます。

```php
$result = Sequence::concat($first, $second)
    ->filter($predicate)
    ->map($mapper)
    ->take(10)
    ->collect();
```

先頭に無限 Sequence を置いた場合、その Sequence 自身が終了しない限り後続 Sequence には到達しません。

---

## Aggregator

`Aggregator<T, R>` は `Sequence<T>` の出力を `R` に集約する **再利用可能な定義**です。

```php
use function Itera\Aggregator\sum;

$total = Sequence::from([10, 20, 30])
    ->aggregate(sum());

// 60
```

重要なのは、Aggregator 自体は値や実行状態を保持する結果オブジェクトではないという点です。

```php
$sum = sum();

$a = Sequence::from([1, 2, 3])->aggregate($sum);
$b = Sequence::from([10, 20])->aggregate($sum);
```

同じ Aggregator 定義を複数の Sequence に適用できます。各実行の state は独立して生成されます。

### 基本 Aggregator

| Aggregator | 結果 |
| --- | --- |
| `count()` | 要素数 |
| `any($predicate)` | いずれかが一致するか |
| `all($predicate)` | すべてが一致するか |
| `collect()` | `Collection` |
| `associate($keySelector)` | `Map` |
| `folding($initial, $step)` | fold の最終 state |

`any()` は最初の truthy result、`all()` は最初の falsy result で早期終了できます。

### 変換しながら集約する Aggregator

| Aggregator | 結果 |
| --- | --- |
| `mapping($mapper)` | map 結果の `Collection` |
| `filtering($predicate)` | filter 結果の `Collection` |
| `flatMapping($mapper)` | flatMap 結果の `Collection` |
| `scanning($initial, $step)` | 各更新後 state の `Collection` |

これらは Sequence operator と似ていますが、**pipeline を変更する operator ではなく、Aggregator の結果を作る定義**です。

たとえば `mapping()` は、Sequence の型を map して次の operator へ渡すのではなく、mapping した値を最終結果の `Collection` として返します。

### 数値 Aggregator

| Aggregator | 結果 |
| --- | --- |
| `sum()` | 合計 |
| `min()` | 最小値。空なら `null` |
| `max()` | 最大値。空なら `null` |
| `average()` | 平均。空なら `null` |

### 検索・分類 Aggregator

| Aggregator | 結果 |
| --- | --- |
| `find($predicate)` | 最初に一致した値。なければ `null` |
| `first()` | 最初の値。空なら `null` |
| `unique()` | strict equality で重複を除いた `Collection` |
| `groupBy($keySelector)` | `Map<TKey, Collection<T>>` |
| `partition($predicate)` | `matched` / `unmatched` の2 Collection |
| `countBy($keySelector)` | key ごとの件数を持つ `Map` |
| `join($separator)` | string の連結結果 |

### combine

`combine()` は、複数の Aggregator を **同じ一回の走査**へ適用します。

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

個別に3回 Sequence を走査するのではなく、各入力値をそれぞれの Aggregator execution に渡して結果をまとめます。

`combine()` は named Aggregator のみを受け取り、結果の key にはその名前が使われます。combined Aggregator の nest は行いません。

### custom Aggregator

独自の集約が必要な場合は `Aggregator::custom()` を使います。

custom Aggregator の factory は、実行ごとに新しい `AggregatorExecution<T, R>` を返します。

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

`AggregatorExecution` の責務は次の3つです。

- `advance($value)` — 未完了の間、入力値を受け取る
- `isComplete()` — 早期終了できるかを返す
- `finish()` — 最終結果を返す

factory は Aggregator 定義の作成時には実行されず、実際に Sequence に適用されたとき、実行ごとに一度呼ばれます。

---

## Pipe

Itera は PHP 8.5 の pipe operator から利用できる function adapter を `Itera\Pipe` 名前空間に提供します。

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

これは次の method chain と同じ処理です。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

pipe を使うと、入力から結果までを左から右へ読むことができます。

```text
iterable
  |> sequence()
  |> transformation
  |> transformation
  |> terminal
```

### sequence

`sequence()` は `iterable<T> -> Sequence<T>` の adapter です。

```php
$result = $values
    |> sequence()
    |> map($mapper)
    |> collect();
```

これにより、PHP array や Generator などから pipe を開始できます。

### operator adapter

次の function は対応する Sequence operator を pipe から利用するための Closure を返します。

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

たとえば

```php
$result = $values
    |> sequence()
    |> filter($predicate)
    |> map($mapper)
    |> take(10)
    |> collect();
```

は、Sequence の同じ mutable pipeline に operator を順に追加していきます。

### terminal adapter

次の function は Sequence を消費して結果を返します。

```text
collect()
associate()
fold()
aggregate()
getIterator()
```

Aggregator と組み合わせる場合も同じです。

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

`concat()` だけは Closure factory ではなく、複数の Sequence を受け取って新しい Sequence を返す composer です。

```php
use function Itera\Pipe\concat;

$sequence = concat(
    Sequence::from([1, 2]),
    Sequence::from([3, 4]),
);
```

その結果には通常どおり pipe を続けられます。

```php
$result = concat($first, $second)
    |> map($mapper)
    |> collect();
```

---

## どの型を使うか

### 値がすでに手元にあり、何度も参照したい

`Collection`

```php
$items = Collection::from($values);
```

### key と value の対応として扱いたい

`Map`

```php
$itemsById = Map::from($valuesById);
```

### iterable を lazy に変換したい

`Sequence`

```php
$result = Sequence::from($source)
    ->filter($predicate)
    ->map($mapper)
    ->take(100)
    ->collect();
```

特に Generator、大きな iterable、途中終了可能な処理では、値を先に全件 materialize せずに処理できます。

### Sequence の出力を集約したい

`Aggregator`

```php
$result = $sequence->aggregate($aggregator);
```

同じ集約定義を再利用したい場合や、`combine()` で複数の結果を一回の走査から得たい場合に使います。

---

## 設計上の境界

Itera の API は、次の境界を明示することを重視しています。

### materialized と lazy

`Collection` / `Map` は materialized です。

`Sequence` は lazy です。

値を保持して再利用したいのか、これから処理する iterable を表したいのかを型で分けます。

### reusable value と single-use pipeline

`Collection` / `Map` は何度でも参照できます。

`Sequence` は消費を開始すると再利用できません。

`Aggregator` はその逆で、実行状態ではなく集約の定義なので再利用できます。

### transformation と aggregation

`Sequence::map()` や `Sequence::filter()` は downstream へ渡す値を変えます。

Aggregator の `mapping()` や `filtering()` は downstream を作らず、集約結果を作ります。

```text
Sequence operator
    input -> transformed stream -> next operator

Aggregator
    input -> aggregation state -> final result
```

この区別によって、pipeline の途中処理と terminal な集約を混ぜずに記述できます。

---

## 典型的な使い方

### Collection から処理する

```php
$result = $users
    ->sequence()
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

### PHP array から pipe する

```php
$result = $users
    |> sequence()
    |> filter(fn (User $user): bool => $user->isActive())
    |> map(fn (User $user): string => $user->name())
    |> collect();
```

### 一回の走査から複数の結果を得る

```php
$stats = $prices
    |> sequence()
    |> aggregate(combine(
        count: counting(),
        sum: sum(),
        average: average(),
    ));
```

Itera の基本形は、**materialized な値から必要なときだけ Sequence に入り、lazy に処理し、最後に必要な形へ materialize または aggregate する**、という流れです。
