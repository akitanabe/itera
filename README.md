# itera

## コレクション基盤

PHP で値・対応関係・遅延計算を分離して扱うための、軽量なコレクション基盤です。

| 型 | 役割 | 評価 |
| --- | --- | --- |
| `Collection<T>` | 順序付きの materialized な値集合 | eager |
| `Map<K, V>` | key と value の対応関係 | materialized |
| `Sequence<T>` | `map` や `filter` などの変換パイプライン | lazy |

### Collection

`Collection<T>` は再利用可能な、順序付きの materialized value collection です。`contains()`、`find()`、`indexOf()`、`findIndex()`、`any()`、`all()` による query を提供します。値の変換や遅延処理は `sequence()` から `Sequence<T>` に移します。

```php
$users = Collection::of($user1, $user2, $user3);
$firstActiveUser = $users->find(fn (User $user): bool => $user->isActive());
```

### Map

`Map<K, V>` は key と value の対応を表す materialized なコレクションです。順序付きの値集合を表す `Collection<T>` とは別の型として扱います。

```php
$usersById = Map::from([
    'u1' => $user1,
    'u2' => $user2,
]);

$user = $usersById->get('u1');
```

`Map::from()` は連想配列を受け取り、`get()` と `has()` による lookup、`keys()`、`values()`、`entries()` による materialized な `Collection` 投影、`raw()` による配列取得を提供します。存在しない key と保存された `null` value に対して `get()` はどちらも `null` を返すため、区別が必要な場合は `has()` を使います。反復では元の key/value を保持します。Map に位置検索、値検索、変換、`ArrayAccess` は追加しません。

`Collection::associate()` と `Sequence::associate()` は key selector だけを受け取ります。Collection はコレクションの値、Sequence は pipeline の出力値を value とする `Map` を生成します。重複 key は後から現れた値で上書きされます。`Sequence::associate()` は終端処理として source と pipeline を消費します。

### Sequence

`Sequence<T>` は mutable で一度だけ消費できる遅延変換パイプラインです。`Collection` の `sequence()` または任意の `iterable` から生成できます。`map`、`filter`、`flatMap`、`until`、`skipUntil`、`take`、`drop` は同じ `Sequence` を更新し、終端処理または `foreach` が始まるまで source や callback を実行しません。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user) => $user->isActive())
    ->map(fn (User $user) => $user->profile())
    ->map(fn (Profile $profile) => $profile->name())
    ->take(10)
    ->collect();
```

`map()` と `filter()` の callback は値だけを受け取ります。`filter()`、`until()`、`skipUntil()` の predicate は静的には `bool` を返す契約ですが、実行時の判定は PHP の truthiness に従います。`until($predicate)` は最初に predicate が truthy と判定された値までを含めて下流へ送り、その後の upstream の読み取りを停止します。一致する値がなければ最後まで値を送ります。

`skipUntil($predicate)` は最初に predicate が truthy と判定された値を含め、それ以降の値を下流へ送り、最初の一致より前の値を捨てます。一致後は predicate を呼び出さず、一致する値がなければ空になります。predicate は値だけを受け取ります。

終端処理は `collect()`、`associate()`、`fold()`、`aggregate()`、`getIterator()` です。`collect()` は値を新しい `Collection` に materialize し、空の結果でも新しい `Collection` を返します。`foreach` でも最終出力を list key で順に取得できます。`getIterator()` は返した iterator を進める前でも、呼び出した時点で `Sequence` を使用済みにします。`fold($initial, $step)` は `step($state, $value)` を順に適用します。

これは破壊的な API 整理です。旧 `toArray()` と `toCollection()` は削除し、配列が必要な場合は `collect()->values()` を使います。`Sequence` から `first()`、`last()`、`contains()`、`count()`、`any()`、`all()`、`reduce()` も提供しません。値の query は `Collection` に残し、`Sequence` では pipeline primitive としての変換、境界、materialization、`fold()` を使います。

どの終端処理も呼び出した時点で消費を開始します。source や callback の例外はそのまま伝播し、途中終了や例外の後を含め、消費を開始した `Sequence` は再利用できません。`map()` や `flatMap()` による同一インスタンス上の型変更は静的解析できますが、変更前に保持した別名参照の型には解析上の制限があります。

`collect()`、`associate()`、`fold()` と全件を読む Aggregator は、無限 source では終了しません。`until()` も一致する値がない無限 source では終了せず、`skipUntil()` も一致後に無限の残りがある場合は残りを全消費する終端処理では終了しません。必要に応じて先に `take()` で有限化します。

### Aggregator

`Aggregator<T, R>` は `Sequence<T>` の出力を `R` に集約する、再利用可能な定義です。`Itera\Aggregator` 名前空間には `count()`、`any($predicate)`、`all($predicate)`、`collect()`、`associate($keySelector)` があります。定義を作った時点では source、predicate、key selector を実行せず、`Sequence::aggregate()` に渡した時点でその Sequence を消費します。同じ定義を別の Sequence に再利用でき、実行ごとの状態や materialized な結果は共有されません。

`count()` は空で `0`、`any()` は空で `false`、`all()` は空で `true` を返します。`any()` は最初の truthy、`all()` は最初の falsy で読み取りを止めます。`collect()` は毎回新しい `Collection` を作り、`associate()` は毎回新しい `Map` を作ります。`associate()` の重複 key は後勝ちです。`count()`、`collect()`、`associate()` は有限な出力を必要とします。

```php
use function Itera\Aggregator\{
    any,
    associate as associateWith,
    collect as collectWith,
    count as countWith,
};

$collectedUsers = $users->sequence()->aggregate(collectWith());
$hasActiveUser = $users->sequence()->aggregate(any(fn (User $user): bool => $user->isActive()));
$usersById = $users->sequence()->aggregate(associateWith(fn (User $user): int => $user->id));
$userCount = $users->sequence()->aggregate(countWith());
$arrayCount = count($sourceArray);
```

`fold($initial, $step)` は呼び出しごとに初期値を渡す単純な左 fold で、Aggregator の定義ではありません。集約の合成と利用者定義の Aggregator は現在の採用範囲に含みません。

### Pipe facade

PHP 8.5 の pipe operator では、`Itera\Pipe` の factory を使って `Sequence` と同じ pipeline API を関数として組み立てられます。関数は Composer の通常の production autoload で読み込まれます。

```php
use function Itera\Aggregator\collect as collectWith;
use function Itera\Pipe\{aggregate, collect as collectPipe, filter, map, sequence, take};

$names = $users
    |> sequence()
    |> filter(fn (User $user): bool => $user->isActive())
    |> map(fn (User $user): string => $user->profile()->name())
    |> take(10)
    |> collectPipe();

$usersCollection = $users
    |> sequence()
    |> aggregate(collectWith());
```

`sequence()`、`map($mapper)`、`filter($predicate)`、`flatMap($mapper)`、`until($predicate)`、`skipUntil($predicate)`、`take($count)`、`drop($count)`、`collect()`、`fold($initial, $step)`、`aggregate($aggregator)`、`associate($keySelector)`、`getIterator()` は、それぞれ単一の入力を受け取る `Closure` を返します。factory の作成時には source や callback を実行せず、`take()` と `drop()` の負数検証も行いません。返した Closure を `Sequence` に適用した時点で対応するメソッドを直接呼ぶため、負数の拒否や消費済み入力の拒否はその適用時に発生します。

中間操作は同じ mutable な `Sequence` を返し、source と callback の実行は終端処理まで遅延されます。`collect()`、`fold()`、`aggregate()`、`associate()` は適用時に消費し、`getIterator()` も適用した時点で Sequence を使用済みにします。各 Sequence は一度だけ消費できます。全件を読む終端処理には有限な出力が必要です。

factory が返した Closure は複数の Sequence に適用できます。`take()` や `drop()` の進行状態が Sequence 間で共有されることはありません。一方、factory に渡した callback、その callback が捕捉した値、`fold()` の初期オブジェクトは複製されず、同じ値が再利用されます。

### 採用範囲

現在の公開モデルは `Collection`、`Map`、`Sequence`、組み込み集約を表す `Aggregator` です。`Fold<Input, State, Output>` による集約の合成は現在の採用範囲に含みません。

Transducer の公開 API、Effect、Interpreter、Free structure は現時点では導入しません。pipeline fusion や Fold composition を後から追加できる構造を保ちつつ、API の分かりやすさを優先します。

## 開発環境

VS Code の Dev Containers 拡張機能と Docker を使って開発できます。

1. Docker を起動します。
2. VS Code でこのリポジトリを開き、`Dev Containers: Reopen in Container` を実行します。
3. コンテナ内で PHP と Composer のバージョンを確認します。

```sh
php --version
composer --version
```

コンテナは PHP 8.5 系を使用します。

## 開発コマンド

依存関係をインストールします。

```sh
composer install
```

テスト、静的解析、Lint、フォーマット確認をまとめて実行します。

```sh
composer check
```

個別に実行する場合は、次のコマンドを使用します。

```sh
composer test
composer analyse
composer lint
composer format
composer format:check
```

### PHPStan 拡張

Pipe 演算子を含む Aggregator の型推論を利用する下流プロジェクトは、PHPStan の設定に配布パッケージの拡張を追加します。

```neon
includes:
    - vendor/akitanabe/itera/phpstan-extension.neon
```

この拡張は解析時だけ読み込み、実行時の autoload には PHPStan を要求しません。下流プロジェクト側では PHPStan を開発依存として導入してください。callback の文脈型と保存した polymorphic Closure のため、PHPStan の互換性保証外の解析 API を限定的に使用しています。現在は PHPStan 2.2.13 で検証しているため、PHPStan を更新した場合は型 fixture を含む解析を再実行してください。
