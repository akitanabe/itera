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

`Sequence<T>` は mutable で一度だけ消費できる遅延変換パイプラインです。`Collection` の `sequence()` または任意の `iterable` から生成できます。`map`、`scan`、`tap`、`chunk`、`filter`、`flatMap`、`until`、`skipUntil`、`take`、`drop` は同じ `Sequence` を更新し、終端処理または `foreach` が始まるまで source や callback を実行しません。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user) => $user->isActive())
    ->map(fn (User $user) => $user->profile())
    ->map(fn (Profile $profile) => $profile->name())
    ->take(10)
    ->collect();
```

`Sequence::concat($first, ...$rest)` は、入力を宣言した順に遅延して連結した新しい `Sequence` を作ります。入力の iterator 解決、値の読み取り、pipeline は消費時まで始まりません。合成の `getIterator()` を取得するだけでは入力を開始せず、`take(0)->collect()` も入力を読みません（どちらも合成自身は使用済みになります）。入力は配列へコピーされず、各入力に登録した操作と値の参照を保ったまま、前の入力が終わった時点で次の入力を開始します。入力が一つの場合も新しい `Sequence` を返します。

```php
$values = Sequence::concat(
    Sequence::from([1, 2]),
    Sequence::from([3]),
)->collect()->values(); // [1, 2, 3]
```

連結後の `map()`、`until()`、`flatMap()`、`chunk()` は連結された出力へ適用されます。入力側の `chunk()` は各入力の境界を保ち、連結後の `chunk()` は入力境界をまたいで値をまとめます。入力は構築後に登録された操作や独立した消費の影響を受け、合成がその入力へ到達した時点の状態が使われます。`take()` や `until()` で途中停止した場合、開始済みの連結と入力は使用済みになり、まだ到達していない入力は独立して消費できます。無限入力を先頭に置く場合、その入力自身を `take()` や `until()` で有限化しない限り後続入力へ到達せず、合成の下流停止でも後続入力は開始しません。

`Itera\Pipe\concat($first, ...$rest)` は Closure factory ではなく、`Sequence::concat()` へ直接委譲する composer です。

`scan($initial, $step)` は seed を出力せず、入力ごとに `step($state, $value)` を一度実行して、その戻り値を次の state と出力にします。state の型が Sequence の要素型になり、登録時には source や step を実行しません。

```php
$totals = Sequence::from([1, 2, 3])
    ->scan(0, fn (int $state, int $value): int => $state + $value)
    ->collect()
    ->values(); // [1, 3, 6]
```

`scan()` は state を複製せず、object state には通常の PHP の参照共有が適用されます。入力が無限の場合は、`take()`、`until()` などで下流を停止できるようにします。

`tap($effect)` は入力値ごとに `effect($value)` を一度実行し、同じ値をそのまま下流へ渡します。effect の戻り値は無視され、object の同一性と effect による変更は保持されます。effect は宣言位置まで届いた値にだけ適用されるため、`filter()` の前後で対象となる値が異なります。登録時や空入力では実行されず、無限入力では `take()`、`until()` などで下流を停止できるようにします。

```php
$result = Sequence::from([1, 2, 3])
    ->tap(function (int $value) use ($logger): void {
        $logger->debug((string) $value);
    })
    ->filter(fn (int $value): bool => $value > 1)
    ->collect()
    ->values(); // [2, 3]
```

`chunk($size)` は値を順に正の `size` 個ずつ、新しい `Collection` に materialize します。正常に入力が終了した場合は非空の端数も出力し、入力の key は保持しません。登録時には値を読まず、0 以下の size は登録時に拒否します。

```php
$chunks = Sequence::from([1, 2, 3, 4, 5])
    ->chunk(2)
    ->collect()
    ->values(); // Collection([1, 2]), Collection([3, 4]), Collection([5])
```

`chunk()` の各登録箇所は、chunk の形成中に現在の一塊だけを保持します。複数の `chunk()` や利用側が保持する出力を含む pipeline 全体のメモリ量を保証するものではありません。入力が無限の場合も塊ごとの出力は可能ですが、全件を読む終端処理には下流の `take()` や `until()` による停止が必要です。同一インスタンス上の型変更には、`map()` や `scan()` と同じ別名参照の静的解析上の制限があります。

`map()` と `filter()` の callback は値だけを受け取ります。`filter()`、`until()`、`skipUntil()` の predicate は静的には `bool` を返す契約ですが、実行時の判定は PHP の truthiness に従います。`until($predicate)` は最初に predicate が truthy と判定された値までを含めて下流へ送り、その後の upstream の読み取りを停止します。一致する値がなければ最後まで値を送ります。

`skipUntil($predicate)` は最初に predicate が truthy と判定された値を含め、それ以降の値を下流へ送り、最初の一致より前の値を捨てます。一致後は predicate を呼び出さず、一致する値がなければ空になります。predicate は値だけを受け取ります。

終端処理は `collect()`、`associate()`、`fold()`、`aggregate()`、`getIterator()` です。`collect()` は値を新しい `Collection` に materialize し、空の結果でも新しい `Collection` を返します。`foreach` でも最終出力を list key で順に取得できます。`getIterator()` は返した iterator を進める前でも、呼び出した時点で `Sequence` を使用済みにします。`fold($initial, $step)` は `step($state, $value)` を順に適用します。

これは破壊的な API 整理です。旧 `toArray()` と `toCollection()` は削除し、配列が必要な場合は `collect()->values()` を使います。`Sequence` から `first()`、`last()`、`contains()`、`count()`、`any()`、`all()`、`reduce()` も提供しません。値の query は `Collection` に残し、`Sequence` では pipeline primitive としての変換、境界、materialization、`fold()` を使います。

どの終端処理も呼び出した時点で消費を開始します。source や callback の例外はそのまま伝播し、途中終了や例外の後を含め、消費を開始した `Sequence` は再利用できません。`map()`、`flatMap()`、`scan()` による同一インスタンス上の型変更は静的解析できますが、変更前に保持した別名参照の型には解析上の制限があります。

`collect()`、`associate()`、`fold()` と全件を読む Aggregator は、無限 source では終了しません。`until()` も一致する値がない無限 source では終了せず、`skipUntil()` も一致後に無限の残りがある場合は残りを全消費する終端処理では終了しません。必要に応じて先に `take()` で有限化します。

### Aggregator

`Aggregator<T, R>` は `Sequence<T>` の出力を `R` に集約する、再利用可能な定義です。`Itera\Aggregator` 名前空間には `count()`、`any($predicate)`、`all($predicate)`、`collect()`、`associate($keySelector)`、`mapping($mapper)`、`filtering($predicate)`、`flatMapping($mapper)`、`scanning($initial, $step)`、`folding($initial, $step)`、`sum()`、`min()`、`max()`、`average()`、`find($predicate)`、`first()`、`unique()`、`groupBy($keySelector)`、`partition($predicate)`、`countBy($keySelector)`、`join($separator)`、`combine(...$aggregators)` があります。公開された `AggregatorExecution<T, R>` を実装する実行 factory は `Aggregator::custom()` で定義できます。定義を作った時点では source、callback、custom factory を実行せず、`Sequence::aggregate()` に渡した時点でその Sequence を消費します。同じ定義を別の Sequence に再利用でき、実行ごとの状態や materialized な結果は共有されません。

custom execution は一回の集約の可変状態を保持し、未完了の間だけ `advance()` で値を受け取り、`isComplete()` で早期終了を示し、`finish()` で結果を返します。`isComplete()` は繰り返し問い合わせられ、完了後に未完了へ戻してはいけません。正常経路では `finish()` が一度呼ばれますが、例外時の cleanup hook ではありません。

Aggregator 定義は factory を保持しますが、factory が生成した execution は保持しません。factory は Sequence の消費開始と source の解決に成功して集約実行へ到達した後、実行ごとに一度呼ばれ、毎回 fresh な execution を返す必要があります。同じ execution インスタンスを複数回返してはいけません。外部 mutable state を capture した場合の実行間の状態分離は利用者の責任です。

初期状態が完了していても IteratorAggregate の source 解決は先に行われます。その後は source の値、pipeline callback、`advance()` を実行せず、`finish()` だけを呼びます。factory、execution、source、pipeline の例外はそのまま伝播し、完了した値の直後で読み取りを止めます。失敗した Sequence は消費済みのままですが、定義は別の Sequence で再利用できます。`count()`、`collect()`、`associate()` と同様、全件を読む custom execution には有限な入力が必要です。

```php
use Itera\Aggregator;
use Itera\AggregatorExecution;
use Itera\Sequence;
use function Itera\Aggregator\combine;
use function Itera\Aggregator\count as countWith;
use function Itera\Pipe\{aggregate, sequence};

/** @implements AggregatorExecution<int, float> */
final class AverageExecution implements AggregatorExecution
{
    private int $count = 0;
    private int $sum = 0;

    public function advance(mixed $value): void
    {
        $this->sum += $value;
        ++$this->count;
    }

    public function isComplete(): bool
    {
        return false;
    }

    public function finish(): float
    {
        return $this->count === 0 ? 0.0 : $this->sum / $this->count;
    }
}

$average = Aggregator::custom(static fn() => new AverageExecution());
$result = Sequence::from([1, 2, 3])->aggregate($average);
$pipeResult = [1, 2, 3] |> sequence() |> aggregate($average);
$summary = Sequence::from([1, 2, 3])->aggregate(combine(average: $average, count: countWith()));
```

| Aggregator | 結果 | 空入力 |
| --- | --- | --- |
| `mapping($mapper)` | 変換値を入力順に持つ `Collection` | 空の `Collection` |
| `filtering($predicate)` | predicate が truthy となる元の値を入力順に持つ `Collection` | 空の `Collection` |
| `flatMapping($mapper)` | 各 iterable の値を外側・内側の順に持つ `Collection` | 空の `Collection` |
| `scanning($initial, $step)` | 入力ごとの更新後 state を持つ `Collection` | 空の `Collection` |
| `folding($initial, $step)` | 最終 state | `initial` |
| `sum()` | PHP 加算による `int\|float` | `0` |
| `min()` | 最小の `int\|float` | `null` |
| `max()` | 最大の `int\|float` | `null` |
| `average()` | float の平均 | `null` |
| `find($predicate)` | predicate が最初に truthy となる値 | `null` |
| `first()` | `null` を含む最初の値 | `null` |
| `unique()` | strict に重複を除いた `Collection` | 空の `Collection` |
| `groupBy($keySelector)` | key ごとの `Collection` を持つ `Map` | 空の `Map` |
| `partition($predicate)` | `matched` と `unmatched` の `Collection` | 両方が空の `Collection` |
| `countBy($keySelector)` | key ごとの件数を持つ `Map` | 空の `Map` |
| `join($separator)` | separator で結合した string | 空文字列 |

`count()` は空で `0`、`any()` は空で `false`、`all()` は空で `true` を返します。`any()` は最初の truthy、`all()` は最初の falsy で読み取りを止めます。`collect()` は毎回新しい `Collection` を作り、`associate()` は毎回新しい `Map` を作ります。`associate()` の重複 key は後勝ちです。`count()`、`collect()`、`associate()` は有限な出力を必要とします。

`mapping()` は変換結果、`filtering()` は predicate が truthy になった元の値、`flatMapping()` は各 mapper が返す iterable の値を、入力順に新しい `Collection` へ格納します。`flatMapping()` は内側 iterable の key を捨て、外側と内側の順序を保ちます。いずれも空入力では空の `Collection` を返します。内側 iterable が無限の場合、`flatMapping()` はその入力値の処理から戻りません。

`scanning()` は入力ごとの更新後 state を `Collection` に格納し、seed 自体は出力しません。空入力では空の `Collection` を返します。`folding()` は最終 state を返し、空入力では initial を返します。initial は複製されないため、object seed と各 scan 結果には通常の PHP の参照共有が適用されます。各 Aggregator の進行状態と結果コンテナは実行ごと、また `combine()` の枝ごとに新しく作られ、変換は指定した枝の結果にだけ適用されます。

これら5関数はすべて入力を最後まで消費するため、無限入力では完了しません。

`sum()` は数値を PHP の加算で集約し、空入力では整数の `0` を返します。`min()` と `max()` は空入力では `null`、それ以外では PHP の `<` と `>` で更新した値を返し、同値なら最初の値とその数値型を保ちます。`average()` は空入力では `null`、それ以外では float の平均を返します。4関数は `int|float` だけを受け付け、NaN、無限大、整数オーバーフローを含む演算結果は PHP のネイティブ動作に従います。いずれも有限な入力を最後まで消費します。

`find($predicate)` は predicate が最初に truthy になった値を返し、`first()` は `null` を含む最初の値を返します。一致または先頭値を得た直後に source の読み取りを止め、結果がない場合は `null` を返します。`combine()` では完了した枝への配信だけが止まり、全件集約する兄弟枝があれば source の終端まで処理を続けます。すべての枝が完了すれば合成全体も停止します。

`join($separator)` は string 値の間だけ separator を挿入し、空入力では空文字列を返します。空文字列の要素も1要素として扱うため、`['', 'a', '']` を `'|'` で結合した結果は `'|a|'` です。入力を最後まで消費するため、無限入力では完了しません。

`unique()` は `Collection::contains()` と同じ strict な PHP 比較を使い、最初の出現順を保ちます。`1`、`'1'`、`1.0` は別の値です。同じ object は重複しますが、同じプロパティを持つ別 object は重複せず、NaN は自身とも一致しません。既採用値を順に探索するため、最悪計算量は O(n²)、保持領域は一意な値の数に対して O(u) です。

`groupBy()` はグループの初出順と各グループ内の入力順を保ちます。`countBy()` は値を保持せず key ごとの件数を返します。両方とも PHP 配列 key の正規化に従うため、PHP が整数 key に正規化する形式の文字列（例: `'1'`）と整数 `1` は同じ Map の key になります。`partition()` は PHP truthiness により各値を `matched` または `unmatched` の一方へ入力順に格納します。これら4関数は入力を最後まで消費するため、無限入力では完了しません。

```php
use function Itera\Aggregator\{
    any,
    associate as associateWith,
    collect as collectWith,
    combine,
    count as countWith,
    folding,
    groupBy,
    mapping,
    scanning,
};

$collectedUsers = $users->sequence()->aggregate(collectWith());
$hasActiveUser = $users->sequence()->aggregate(any(fn (User $user): bool => $user->isActive()));
$usersById = $users->sequence()->aggregate(associateWith(fn (User $user): int => $user->id));
$userCount = $users->sequence()->aggregate(countWith());
$arrayCount = count($sourceArray);

$summary = $users->sequence()->aggregate(combine(
    count: countWith(),
    active: any(fn (User $user): bool => $user->isActive()),
    items: collectWith(),
    byId: associateWith(fn (User $user): int => $user->id),
));
// array{count: int, active: bool, items: Collection<User>, byId: Map<int, User>}

$independent = Sequence::from([1, 2, 3])->aggregate(combine(
    mapped: mapping(fn (int $value): int => $value * 10),
    running: scanning(0, fn (int $state, int $value): int => $state + $value),
    total: folding(0, fn (int $state, int $value): int => $state + $value),
    groups: groupBy(fn (int $value): string => $value % 2 === 0 ? 'even' : 'odd'),
));
$independent['mapped']->values(); // [10, 20, 30]
$independent['running']->values(); // [1, 3, 6]
$independent['total']; // 6
$independent['groups']->get('odd')?->values(); // [1, 3]
$independent['groups']->get('even')?->values(); // [2]
```

`combine()` の各枝は同じ pipeline 出力を独立して集約します。上の `mapping()` は `mapped` だけを変換し、他の枝は元の `1`、`2`、`3` を受け取ります。

`combine()` は一個以上の名前付き Aggregator を受け取る Aggregator 定義を返します。各子 Aggregator は対象 `Sequence` の要素型を受け取れる必要があり、合成後の入力型には子が共有する制約が残ります。custom Aggregator も組み込み Aggregator と同じ平坦な named combine に指定できます。静的解析では PHPStan 拡張が、`combine()` の広い PHPDoc 入力・結果型を、子ごとの callback 入力契約と名前付き結果型へ具体化します。`aggregate()` で実行すると、子の名前と指定順を保つ結果配列を返します。位置引数と `combine()` の入れ子は受け付けません。入力は一度だけ走査され、各値は未完了の子へ指定順に渡されます。完了した子の callback は以後呼ばれず、すべての子が完了すれば source の読み取りも止まります。`count()`、`collect()`、`associate()` のように全件を読む子を含む場合、合成全体も source の終端まで読みます。

`fold($initial, $step)` は呼び出しごとに初期値を渡す単純な左 fold で、Aggregator の定義ではありません。

### Pipe facade

PHP 8.5 の pipe operator では、`Itera\Pipe` の factory を使って `Sequence` と同じ pipeline API を関数として組み立てられます。関数は Composer の通常の production autoload で読み込まれます。

```php
use function Itera\Aggregator\collect as collectWith;
use function Itera\Pipe\{aggregate, collect as collectPipe, concat, filter, map, sequence, take};

$names = $users
    |> sequence()
    |> filter(fn (User $user): bool => $user->isActive())
    |> map(fn (User $user): string => $user->profile()->name())
    |> take(10)
    |> collectPipe();

$usersCollection = $users
    |> sequence()
    |> aggregate(collectWith());

$combined = concat(
    [1, 2] |> sequence(),
    [3] |> sequence(),
)
    |> map(fn (int $value): int => $value * 10)
    |> collectPipe();

$combined->values(); // [10, 20, 30]
```

`sequence()`、`map($mapper)`、`scan($initial, $step)`、`tap($effect)`、`chunk($size)`、`filter($predicate)`、`flatMap($mapper)`、`until($predicate)`、`skipUntil($predicate)`、`take($count)`、`drop($count)`、`collect()`、`fold($initial, $step)`、`aggregate($aggregator)`、`associate($keySelector)`、`getIterator()` は、それぞれ単一の入力を受け取る `Closure` を返します。factory の作成時には source や callback を実行せず、`chunk()`、`take()`、`drop()` の引数検証も行いません。返した Closure を `Sequence` に適用した時点で対応するメソッドを直接呼ぶため、引数や消費済み入力の拒否はその適用時に発生します。

`concat($first, ...$rest)` は入力 `Sequence` を受け取って直接 `Sequence` を返すため、返された値へ通常の `|>` で `map()` や `collect()` を続けられます。

中間操作は同じ mutable な `Sequence` を返し、source と callback の実行は終端処理まで遅延されます。`collect()`、`fold()`、`aggregate()`、`associate()` は適用時に消費し、`getIterator()` も適用した時点で Sequence を使用済みにします。各 Sequence は一度だけ消費できます。全件を読む終端処理には有限な出力が必要です。

factory が返した Closure は複数の Sequence に適用できます。`take()`、`drop()`、`scan()` の scalar な進行状態は Sequence ごとに独立します。一方、factory に渡した callback、その callback が捕捉した値、`fold()` の初期オブジェクト、`scan()` の object seed は複製されず、通常の PHP の参照共有に従って同じ値が再利用されます。

`tap($effect)` の factory も再利用でき、各 Sequence の宣言位置で値を観測します。effect の戻り値は変換に使われず、適用時にも Sequence の消費開始までは実行されません。

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
