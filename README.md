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

`Sequence<T>` は mutable で一度だけ消費できる遅延変換パイプラインです。`Collection` の `sequence()` または任意の `iterable` から生成できます。`map`、`filter`、`flatMap`、`take`、`drop` は同じ `Sequence` を更新し、終端処理または `foreach` が始まるまで source や callback を実行しません。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user) => $user->isActive())
    ->map(fn (User $user) => $user->profile())
    ->map(fn (Profile $profile) => $profile->name())
    ->take(10)
    ->toCollection();
```

終端処理は `toArray()`、`toCollection()`、`first()`、`count()`、`any()`、`all()`、`fold()`、`getIterator()` です。`foreach` でも最終出力を list key で順に取得できます。`getIterator()` は返した iterator を進める前でも、呼び出した時点で `Sequence` を使用済みにします。`first()`、`any()`、`all()` は結果が確定すると source の読み取りを停止し、`any()` と `all()` の predicate は実際の `bool` を返す必要があります。`fold($initial, $step)` は `step($state, $value)` を順に適用します。

どの終端処理も呼び出した時点で消費を開始します。source や callback の例外はそのまま伝播し、途中終了や例外の後を含め、消費を開始した `Sequence` は再利用できません。`map()` や `flatMap()` による同一インスタンス上の型変更は静的解析できますが、変更前に保持した別名参照の型には解析上の制限があります。

`toArray()`、`toCollection()`、`count()`、`fold()` は結果を最後まで消費するため、無限 source では終了しません。必要に応じて先に `take()` で有限化します。

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
