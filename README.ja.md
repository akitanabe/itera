# Itera

[English](README.md)

Itera は、コレクション・遅延シーケンス・集約を、明確なデータ処理の境界として扱うための PHP 8.5 向け小規模ライブラリです。

一般的な Collection API では混ざりやすい責務を、次の4つに分離します。

| 型 | 役割 |
| --- | --- |
| `Collection<T>` | 再利用可能な、順序付きの materialized value |
| `Map<TKey, TValue>` | 再利用可能な、materialized な key/value 関係 |
| `Sequence<T>` | lazy / mutable / single-use な変換 pipeline |
| `Aggregator<T, R>` | Sequence の出力を結果へ集約する再利用可能な定義 |

## Core types

### Collection

`Collection<T>` は、すでに materialize された値を保持し、何度でも参照できる型です。

`first()`、`find()`、`contains()`、`any()`、`all()` など、値に対する query を提供します。変換を始める場合は `sequence()` で lazy な `Sequence` に移ります。

```php
$activeUsers = $users
    ->sequence()
    ->filter(fn (User $user): bool => $user->isActive())
    ->collect();
```

### Map

`Map<TKey, TValue>` は materialized な key/value の対応関係を表します。

`get()` と `has()` による lookup、`keys()`、`values()`、`entries()` による projection を提供します。

```php
$usersById = Map::from([
    'u1' => $user1,
    'u2' => $user2,
]);

$user = $usersById->get('u1');
```

### Sequence

`Sequence<T>` は iterable に対する lazy な変換 pipeline です。

`map()`、`filter()`、`flatMap()`、`take()`、`until()` などの operator は、登録した時点では source を読みません。`collect()`、`associate()`、`fold()`、`aggregate()` などの terminal operation により消費が始まります。

Sequence は mutable かつ single-use です。一度消費を開始すると再利用できません。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->take(10)
    ->collect();
```

### Aggregator

`Aggregator<T, R>` は、Sequence の出力を消費して結果を生成する再利用可能な定義です。

件数、数値集約、検索、grouping、materialization などの built-in Aggregator があり、`combine()` を使うと複数の集約を一回の走査で実行できます。

```php
$stats = Sequence::from([10, 20, 30])
    ->aggregate(combine(
        count: counting(),
        sum: sum(),
        average: average(),
    ));
```

Sequence と異なり、Aggregator 自体は再利用できます。実行ごとに fresh な集約 state が生成されます。

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

これは次の method chain と同じ処理モデルです。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user): bool => $user->isActive())
    ->map(fn (User $user): string => $user->name())
    ->collect();
```

pipe を使うことで、データの流れを左から右へそのまま記述できます。

```text
iterable
  |> sequence()
  |> transformation
  |> transformation
  |> terminal
```

Sequence の operator と terminal operation には対応する pipe adapter が用意されているため、method chain と pipe expression は同じ semantics を共有します。

## Documentation

詳細な semantics、利用可能な operation、Aggregator の挙動、custom Aggregator、pipe adapter については [Itera Guide](docs/guide.ja.md) を参照してください。

英語版は [README.md](README.md) と [docs/guide.md](docs/guide.md) です。

## Requirements

- PHP 8.5 以上

## License

MIT
