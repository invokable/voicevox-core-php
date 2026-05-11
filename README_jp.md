# VOICEVOX Core for PHP

[![tests](https://github.com/invokable/voicevox-core-php/actions/workflows/tests.yml/badge.svg)](https://github.com/invokable/voicevox-core-php/actions/workflows/tests.yml)
[![integration tests](https://github.com/invokable/voicevox-core-php/actions/workflows/integration-tests.yml/badge.svg)](https://github.com/invokable/voicevox-core-php/actions/workflows/integration-tests.yml)
[![linter](https://github.com/invokable/voicevox-core-php/actions/workflows/lint.yml/badge.svg)](https://github.com/invokable/voicevox-core-php/actions/workflows/lint.yml)
[![Maintainability](https://qlty.sh/gh/invokable/projects/voicevox-core-php/maintainability.svg)](https://qlty.sh/gh/invokable/projects/voicevox-core-php)
[![Code Coverage](https://qlty.sh/gh/invokable/projects/voicevox-core-php/coverage.svg)](https://qlty.sh/gh/invokable/projects/voicevox-core-php)

[VOICEVOX CORE](https://github.com/VOICEVOX/voicevox_core)のPHP FFIラッパーです。VOICEVOXプロジェクトのテキスト音声合成エンジンライブラリをPHPから利用できます。

これはPure PHP用のパッケージです。一般的な用途には [Laravel版](https://github.com/invokable/laravel-voicevox) が推奨です。

## 要件

- PHP 8.3以上
- `ext-ffi` 拡張が有効であること
- VOICEVOX CORE 0.16+

> [!NOTE]
> PHP FFIはWebサーバー環境（FPMなど）では無効にされていることが多いため（`ffi.enable=false`）、このライブラリは**ローカルCLIでの利用を想定**しています。

## インストール

```bash
composer require revolution/voicevox-core
```

## ライブラリのセットアップ (Linux / macOS)

本パッケージを使用するには、VOICEVOX COREの動的ライブラリ（`.so` / `.dylib`）、ONNX Runtime、OpenJTalk辞書が必要です。

### 1. voicevox_coreのダウンロード

[voicevox_core releases](https://github.com/VOICEVOX/voicevox_core/releases) から自分のOSとアーキテクチャに合ったダウンローダーをダウンロードして実行してください。カレントディレクトリに `voicevox_core` ディレクトリが作成されます。直下には以下のものが含まれています。

- `dict/open_jtalk_dic_*/` — OpenJTalk辞書
- `onnxruntime/` — ONNX Runtimeライブラリ
- `c_api/lib/` — 動的ライブラリファイル（`.so`、`.dylib`、または `.dll`）
- `models/` — 圧縮されたモデルファイル（`.vvm`）

### 2. 任意のパスに移動する

```bash
mv voicevox_core ~/.local/voicevox_core
```

### 3. シンボリックリンクを張る（推奨）

動的ライブラリが自動的に見つかるようにシンボリックリンクを作成します。

**macOS:**
```bash
# [VOICEVOX_CORE_DIR] を voicevox_core の絶対パスに置き換えてください
ln -s [VOICEVOX_CORE_DIR]/libvoicevox_core.dylib /usr/local/lib/libvoicevox_core.dylib
```

`/usr/local/lib/`から読み込めない時は`.zshrc`などで`DYLD_FALLBACK_LIBRARY_PATH`を設定してください。
```bash
export DYLD_FALLBACK_LIBRARY_PATH="$HOME/lib:/usr/local/lib:/usr/lib"
```

**Linux:**
```bash
ln -s [VOICEVOX_CORE_DIR]/libvoicevox_core.so /usr/local/lib/libvoicevox_core.so
```

> [!WARNING]
> `ln -s` に渡すパスは必ず**絶対パス**にしてください。

### 代替方法: 環境変数を使う

シンボリックリンクが使えない場合は、`VOICEVOX_CORE_LIB_PATH` 環境変数にライブラリファイルのフルパスを設定してください。

```bash
export VOICEVOX_CORE_LIB_PATH=/path/to/libvoicevox_core.dylib
```

## 使用例

以下の `talk.php` はテキスト音声合成のサンプルコードです。

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;

// パス — voicevox_coreのインストール場所に合わせて変更してください
$voicevoxCoreDir = getenv('HOME') . '/.local/voicevox_core';
$onnxruntimeFilename = $voicevoxCoreDir . '/onnxruntime/lib/' . Onnxruntime::libVersionedFilename();
$dictDir = $voicevoxCoreDir . '/dict/open_jtalk_dic_utf_8-1.11';
$vvmPath  = $voicevoxCoreDir . '/models/vvms/0.vvm';

// 読み上げるテキストとスタイルID
$text    = 'この音声は、ボイスボックスを使用して、出力されています。';
$styleId = 0;
$outPath = './output.wav';

// 初期化
$onnxruntime = Onnxruntime::loadOnce($onnxruntimeFilename);
$openJtalk   = new OpenJtalk($dictDir);
$synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Auto);

// 音声モデルの読み込み
$model = VoiceModelFile::open($vvmPath);
$synthesizer->loadVoiceModel($model);

// 音声合成
$audioQuery = $synthesizer->createAudioQuery($text, $styleId);
$wav        = $synthesizer->synthesis($audioQuery, $styleId);

file_put_contents($outPath, $wav);
echo 'Wrote ' . $outPath . PHP_EOL;
```

実行方法:

```bash
php talk.php
```

## テスト

- `composer run test` はデフォルトの `Unit` testsuite だけを実行します。
- 実ランタイムに依存するテストは `tests/Integration` に分離してあり、通常実行には含まれません。
- 実行する場合は `VOICEVOX_CORE_TEST_ROOT` を設定したうえで `vendor/bin/pest --compact --testsuite=Integration`（または `composer run test:integration`）を使ってください。GitHub Actions では専用の `.github/workflows/integration-tests.yml` workflow から実行します。

## APIリファレンス

### `Onnxruntime`

ONNX Runtimeのローダー。プロセスレベルのシングルトンで、インスタンスは高々1つです。

| メソッド | 説明 |
|--------|------|
| `static loadOnce(string $filename = ''): self` | ONNX Runtimeをロードして初期化します。2回目以降の呼び出しでは引数を無視して既存のインスタンスを返します。 |
| `static get(): ?self` | 既存のインスタンスを返します。未初期化の場合は `null` を返します。 |
| `supportedDevices(): string` | 利用可能なデバイス情報をJSON文字列で返します。 |
| `static libVersionedFilename(): string` | バージョン付きONNX Runtimeライブラリのファイル名を返します（例: `libvoicevox_onnxruntime.1.17.3.dylib`）。 |
| `static libUnversionedFilename(): string` | バージョンなしONNX Runtimeライブラリのファイル名を返します。 |

**定数:**

| 定数 | 説明 |
|------|------|
| `LIB_NAME` | ライブラリのベース名 (`voicevox_onnxruntime`) |
| `LIB_VERSION` | 推奨されるONNX Runtimeのバージョン |

---

### `OpenJtalk`

OpenJTalkを使用したテキスト解析器。

| メソッド | 説明 |
|--------|------|
| `__construct(string $openJtalkDictDir)` | OpenJTalk辞書ディレクトリのパスを指定して初期化します。 |
| `useUserDict(UserDict $userDict): void` | ユーザー辞書を設定します。辞書を変更した場合は再度この関数を呼ぶ必要があります。 |

---

### `VoiceModelFile`

音声モデルファイル（`.vvm` ファイル）。

| メソッド | 説明 |
|--------|------|
| `static open(string $path): self` | `.vvm` ファイルを開きます。 |
| `id(): string` | 音声モデルIDをhex文字列（16バイト）で返します。 |
| `createMetasJson(): string` | 話者のメタ情報をJSON文字列で返します。 |
| `close(): void` | ファイルを閉じてリソースを解放します。 |

---

### `Synthesizer`

メインのテキスト音声合成クラス。

| メソッド | 説明 |
|--------|------|
| `__construct(Onnxruntime $onnxruntime, OpenJtalk $openJtalk, AccelerationMode $accelerationMode = Auto, int $cpuNumThreads = 0)` | シンセサイザーを初期化します。 |
| `isGpuMode(): bool` | GPUモードかどうかを返します。 |
| `metas(): string` | 読み込み済み話者のメタ情報をJSON文字列で返します。 |
| `loadVoiceModel(VoiceModelFile $model): void` | 音声モデルを読み込みます。 |
| `unloadVoiceModel(string $voiceModelId): void` | hex IDで指定した音声モデルの読み込みを解除します。 |
| `isLoadedVoiceModel(string $voiceModelId): bool` | 指定した音声モデルが読み込まれているか確認します。 |
| `createAudioQuery(string $text, int $styleId): string` | 日本語テキストからAudioQuery JSONを生成します。 |
| `createAudioQueryFromKana(string $kana, int $styleId): string` | AquesTalk風記法からAudioQuery JSONを生成します。 |
| `createAccentPhrases(string $text, int $styleId): string` | 日本語テキストからアクセント句配列のJSONを生成します。 |
| `createAccentPhrasesFromKana(string $kana, int $styleId): string` | AquesTalk風記法からアクセント句配列のJSONを生成します。 |
| `replaceMoraData(string $accentPhrasesJson, int $styleId): string` | モーラの音高・音素長を変更した新しいアクセント句配列のJSONを返します。 |
| `replacePhonemeLength(string $accentPhrasesJson, int $styleId): string` | 音素長を変更した新しいアクセント句配列のJSONを返します。 |
| `replaceMoraPitch(string $accentPhrasesJson, int $styleId): string` | モーラの音高を変更した新しいアクセント句配列のJSONを返します。 |
| `synthesis(string $audioQueryJson, int $styleId, bool $enableInterrogativeUpspeak = true): string` | AudioQuery JSONから音声合成します。WAVバイナリを返します。 |
| `tts(string $text, int $styleId, bool $enableInterrogativeUpspeak = true): string` | 日本語テキストから1ステップで音声合成します。WAVバイナリを返します。 |
| `ttsFromKana(string $kana, int $styleId, bool $enableInterrogativeUpspeak = true): string` | AquesTalk風記法から音声合成します。WAVバイナリを返します。 |
| `createSingFrameAudioQuery(string $scoreJson, int $styleId): string` | 楽譜JSONから歌唱音声合成用クエリのJSONを生成します。 |
| `frameSynthesis(string $frameAudioQueryJson, int $styleId): string` | 歌唱音声合成用クエリから音声合成します。WAVバイナリを返します。 |

---

### `UserDict`

カスタム単語の読みを登録するユーザー辞書。

| メソッド | 説明 |
|--------|------|
| `__construct()` | 空のユーザー辞書を新規作成します。 |
| `load(string $path): void` | ファイルからユーザー辞書を読み込みます。 |
| `save(string $path): void` | ユーザー辞書をファイルに保存します。 |
| `addWord(string $surface, string $pronunciation, int $accentType, UserDictWordType $wordType = CommonNoun, int $priority = 5): string` | 単語を追加します。単語のUUIDをhex文字列で返します。 |
| `updateWord(string $wordUuid, string $surface, string $pronunciation, int $accentType, UserDictWordType $wordType = CommonNoun, int $priority = 5): void` | UUIDで指定した単語を更新します。 |
| `removeWord(string $wordUuid): void` | UUIDで指定した単語を削除します。 |
| `importDict(UserDict $other): void` | 別の `UserDict` から単語をインポートします。 |
| `toJson(): string` | 全単語をJSON文字列で返します。 |

---

### `AccelerationMode` (enum)

シンセサイザーのハードウェアアクセラレーションモード。

| ケース | 値 | 説明 |
|--------|-----|------|
| `Auto` | `0` | 実行環境に合った最適なモードを自動選択します。 |
| `Cpu` | `1` | CPUモードを強制します。 |
| `Gpu` | `2` | GPUモードを強制します。 |

---

### `UserDictWordType` (enum)

ユーザー辞書エントリの単語種別。

| ケース | 値 | 説明 |
|--------|-----|------|
| `ProperNoun` | `0` | 固有名詞 |
| `CommonNoun` | `1` | 一般名詞 |
| `Verb` | `2` | 動詞 |
| `Adjective` | `3` | 形容詞 |
| `Suffix` | `4` | 接尾辞 |

---

### `VoicevoxException`

VOICEVOX Core C APIの呼び出しがエラーコードを返したときにスローされます。例外メッセージにはライブラリからのエラー説明が含まれます。

## ライセンス

MIT
