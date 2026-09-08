# itera

## コレクション基盤

PHP で値・対応関係・遅延計算を分離して扱うための、軽量なコレクション基盤です。

| 型 | 役割 | 評価 |
| --- | --- | --- |
| `Collection<T>` | 順序付きの materialized な値集合 | eager |
| `Map<K, V>` | key と value の対応関係 | materialized |
| `Sequence<T>` | `map` や `filter` などの変換パイプライン | lazy |

### Collection

`Collection<T>` は再利用可能な、順序付きの materialized value collection です。`contains()`、`find()`、`indexOf()`、`findIndex()`、`any()`、`all()` による query と、`reverse()`、`slice()` による保持済み順序への操作を提供します。値の変換や遅延処理は `sequence()` から `Sequence<T>` に移します。

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

### Sequence

`Sequence<T>` は mutable で一度だけ消費できる遅延変換パイプラインです。`Collection` の `sequence()` または任意の `iterable` から生成できます。`map`、`filter`、`flatMap`、`until`、`skipUntil`、`take`、`drop` は同じ `Sequence` を更新し、終端処理または `foreach` が始まるまで source や callback を実行しません。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user) => $user->isActive())
    ->map(fn (User $user) => $user->profile())
    ->map(fn (Profile $profile) => $profile->name())
    ->take(10)
    ->toCollection();
```

`map()` と `filter()` の callback は値だけを受け取ります。`filter()` の predicate は実際の `bool` を返す必要があります。`until($predicate)` は最初に predicate が `true` を返した値までを含めて下流へ送り、その後の upstream の読み取りを停止します。一致する値がなければ最後まで値を送り、predicate は値だけを受け取り実際の `bool` を返す必要があります。

`skipUntil($predicate)` は最初に predicate が `true` を返した値を含め、それ以降の値を下流へ送り、最初の一致より前の値を捨てます。一致後は predicate を呼び出さず、一致する値がなければ空になります。両方の predicate は値だけを受け取り、実際の `bool` を返す必要があります。

終端処理は `toArray()`、`toCollection()`、`fold()`、`getIterator()` です。`foreach` でも最終出力を list key で順に取得できます。`getIterator()` は返した iterator を進める前でも、呼び出した時点で `Sequence` を使用済みにします。`fold($initial, $step)` は `step($state, $value)` を順に適用します。

これは破壊的な API 整理です。`Sequence` から `first()`、`last()`、`contains()`、`count()`、`any()`、`all()`、`reduce()` を提供しません。値の query は `Collection` に残し、`Sequence` では pipeline primitive としての変換、境界、materialization、`fold()` を使います。

どの終端処理も呼び出した時点で消費を開始します。source や callback の例外はそのまま伝播し、途中終了や例外の後を含め、消費を開始した `Sequence` は再利用できません。`map()` や `flatMap()` による同一インスタンス上の型変更は静的解析できますが、変更前に保持した別名参照の型には解析上の制限があります。

`toArray()`、`toCollection()`、`fold()` は結果を最後まで消費するため、無限 source では終了しません。`until()` も一致する値がない無限 source では終了せず、`skipUntil()` も一致後に無限の残りがある場合は残りを全消費する終端処理では終了しません。必要に応じて先に `take()` で有限化します。

### 採用範囲

現在の公開モデルは `Collection`、`Map`、`Sequence` です。`Fold<Input, State, Output>` による集約の合成は必要性が確認できた段階で導入します。

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
