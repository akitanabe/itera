# itera

## コレクション基盤

PHP で値・対応関係・遅延計算を分離して扱うための、軽量なコレクション基盤です。

| 型 | 役割 | 評価 |
| --- | --- | --- |
| `Collection<T>` | 順序付きの materialized な値集合 | eager |
| `Map<K, V>` | key と value の対応関係 | materialized |
| `Sequence<T>` | `map` や `filter` などの変換パイプライン | lazy |

### Collection

`Collection<T>` は integer index を持つ順序付きコレクションです。`map` や `filter` はその場で評価され、`Collection` として結果を返します。

```php
$names = Collection::of($user1, $user2, $user3)
    ->map(fn (User $user) => $user->name())
    ->filter(fn (string $name) => $name !== '');
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

`Sequence<T>` は遅延評価される変換パイプラインです。`Collection` の `sequence()` または任意の `iterable` から生成できます。`map`、`filter`、`flatMap`、`take`、`drop` などの intermediate operator は、terminal operator が呼ばれるまで実行されません。

```php
$result = Sequence::from($users)
    ->filter(fn (User $user) => $user->isActive())
    ->map(fn (User $user) => $user->profile())
    ->map(fn (Profile $profile) => $profile->name())
    ->take(10)
    ->toCollection();
```

`toCollection`、`toMap`、`first`、`count`、`any`、`all`、`reduce`、`fold` などが terminal operator です。評価時は中間配列を作らず、各要素をパイプラインの末尾まで流して可能な限り single-pass で処理します。`first` や `any` のように途中で結果が確定する操作は早期終了できます。

無限 `Sequence` を終端処理する場合は終了しない可能性があるため、必要に応じて `take` などで有限化します。

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
