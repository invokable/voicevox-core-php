# VOICEVOX Core PHP FFI 実装可能性調査

> **対象範囲**: Pure PHP（Laravel非依存）。VOICEVOX CoreのCダイナミックライブラリをPHP組み込みのFFI拡張でラップする独立したPHPパッケージについての調査です。

---

## エグゼクティブサマリー

`VOICEVOX/voicevox_core` のPHP FFIラッパー実装は**技術的に実現可能**ですが、いくつかの重要な注意点があります。C APIはコールバックなし・オペーク(不透明)ポインタ基盤のクリーンな設計で、FFIとの相性は良好です。主な課題は次の通りです：

1. **PHP FFI にCプリプロセッサがない**: 公式の `voicevox_core.h` ヘッダは `#ifdef` マクロを使用しており、`FFI::cdef()` に渡す前に手動で解決する必要があります。
2. **ONNXランタイムを先に読み込む必要がある**: 最新のv0.16+ APIでは `libvoicevox_core` より先に `libonnxruntime` を読み込む必要があり、追加の `FFI::cdef()` 呼び出しが必要です。
3. **`ffi.enable` の設定が必要**: WebサーバーSAPIではOPcacheプリロードが必要ですが、CLIはそのまま使えます。
4. **NativePHPとの互換性は限定的**: バンドルされているPHPバイナリにFFIは含まれておらず、`static-php-cli` でカスタムビルドが必要です。

CLIツール・ローカルスクリプト・カスタムデスクトップアプリ（カスタムPHPバイナリを使用したNativePHP経由）での実装は十分可能です。本番Webサーバーへのデプロイはより複雑ですが、OPcacheプリロードを適切に設定すれば実現できます。

---

## 目次

1. [VOICEVOX Coreの概要](#1-voicevox-coreの概要)
2. [C APIのアーキテクチャ](#2-c-apiのアーキテクチャ)
3. [既存の言語別ラッパー](#3-既存の言語別ラッパー)
4. [PHP FFIの機能](#4-php-ffiの機能)
5. [voicevox_core.hとのPHP FFI非互換性](#5-voicevox_corehとのphp-ffi非互換性)
6. [実装ガイド](#6-実装ガイド)
7. [ONNXランタイムの読み込み](#7-onnxランタイムの読み込み)
8. [プラットフォームサポートと配布](#8-プラットフォームサポートと配布)
9. [NativePHPとの互換性](#9-nativephpとの互換性)
10. [推奨PHP FFI宣言文字列](#10-推奨php-ffi宣言文字列)
11. [信頼度評価](#11-信頼度評価)

---

## 1. VOICEVOX Coreの概要

**リポジトリ**: [VOICEVOX/voicevox_core](https://github.com/VOICEVOX/voicevox_core)（MITライセンス）  
**最新リリース**: v0.16.4（2026年2月）  
**言語**: Rust  
**C APIヘッダ**: [`crates/voicevox_core_c_api/include/voicevox_core.h`](https://github.com/VOICEVOX/voicevox_core/blob/main/crates/voicevox_core_c_api/include/voicevox_core.h)（75KB、`cbindgen` 自動生成）

VOICEVOX Coreはテキスト音声合成（TTS）と歌声合成のライブラリです。主要プラットフォーム向けにビルド済みCダイナミックライブラリが配布されています：

| プラットフォーム | アーキテクチャ | ライブラリファイル |
|--------------|--------------|----------------|
| Windows  | x64     | `voicevox_core.dll` |
| Windows  | x86     | `voicevox_core.dll` |
| macOS    | x64 (Intel) | `libvoicevox_core.dylib` |
| macOS    | arm64 (Apple Silicon) | `libvoicevox_core.dylib` |
| Linux    | x64     | `libvoicevox_core.so` |
| Linux    | arm64   | `libvoicevox_core.so` |
| Android  | arm64, x86_64 | `.so` |
| iOS      | xcframework | `.xcframework` |

**ダウンロードURLパターン**:
```
https://github.com/VOICEVOX/voicevox_core/releases/download/{VERSION}/voicevox_core-{OS}-{ARCH}-{VERSION}.zip
```

各ZIPには：ダイナミックライブラリ・`voicevox_core.h`・ONNXランタイムライブラリが含まれます。[^1]

---

## 2. C APIのアーキテクチャ

C APIは5つの論理グループにわたって**63個の関数**を公開しています。[^2]

### オペーク（不透明）ハンドル型

APIはオペークなCstructポインタを使用し、PHPからは内部が見えず、保持して渡すだけです：

```c
typedef struct VoicevoxOnnxruntime    VoicevoxOnnxruntime;    // ONNXランタイムインスタンス
typedef struct OpenJtalkRc            OpenJtalkRc;             // 日本語テキスト解析器
typedef struct VoicevoxSynthesizer    VoicevoxSynthesizer;     // メイン合成器
typedef struct VoicevoxVoiceModelFile VoicevoxVoiceModelFile;  // 音声モデルファイル(.vvm)
typedef struct VoicevoxUserDict       VoicevoxUserDict;         // ユーザー辞書
```

### 初期化シーケンス（最新v0.16+ API）

```
voicevox_onnxruntime_load_once()     → VoicevoxOnnxruntime*
voicevox_open_jtalk_rc_new()         → OpenJtalkRc*
voicevox_synthesizer_new()           → VoicevoxSynthesizer*
voicevox_voice_model_file_open()     → VoicevoxVoiceModelFile*
voicevox_synthesizer_load_voice_model()
```

### TTSパイプライン

```
テキスト → voicevox_synthesizer_create_audio_query() → AudioQuery JSON
                                                              ↓
                       voicevox_synthesizer_synthesis() → WAVバイト列
```

または短縮形：`voicevox_synthesizer_tts()`（テキスト→WAVを1回で）

### メモリ管理

- `char**` / `uint8_t**` で返されるJSONとWAVバッファは**Cヒープ上に確保**される
- 呼び出し側が `voicevox_json_free(char*)` と `voicevox_wav_free(uint8_t*)` で解放必須
- オペークハンドルは各 `_delete()` 関数で解放（v0.16.1以降、nullセーフ）

### C言語での使用例（`example/cpp/unix/talk.cpp` より）[^3]

```c
// 1. ONNXランタイムの読み込み
VoicevoxLoadOnnxruntimeOptions ort_opts = voicevox_make_default_load_onnxruntime_options();
ort_opts.filename = "./voicevox_core/onnxruntime/lib/libvoicevox_onnxruntime.so.1.17.3";
const VoicevoxOnnxruntime *onnxruntime;
voicevox_onnxruntime_load_once(ort_opts, &onnxruntime);

// 2. OpenJTalkの作成
OpenJtalkRc *open_jtalk;
voicevox_open_jtalk_rc_new("./voicevox_core/dict/open_jtalk_dic_utf_8-1.11", &open_jtalk);

// 3. 合成器の作成
VoicevoxInitializeOptions opts = voicevox_make_default_initialize_options();
VoicevoxSynthesizer *synthesizer;
voicevox_synthesizer_new(onnxruntime, open_jtalk, opts, &synthesizer);

// 4. 音声モデルの読み込み
VoicevoxVoiceModelFile *model;
voicevox_voice_model_file_open("models/vvms/0.vvm", &model);
voicevox_synthesizer_load_voice_model(synthesizer, model);

// 5. 音声合成
size_t wav_size;
uint8_t *wav;
voicevox_synthesizer_tts(synthesizer, "テスト", 0,
    voicevox_make_default_tts_options(), &wav_size, &wav);

// 6. クリーンアップ
voicevox_wav_free(wav);
voicevox_synthesizer_delete(synthesizer);
voicevox_voice_model_file_delete(model);
voicevox_open_jtalk_rc_delete(open_jtalk);
```

---

## 3. 既存の言語別ラッパー

VOICEVOX Core READMEに記載されたコミュニティFFIラッパー一覧[^4]：

| 言語 | リポジトリ | アプローチ | 対応APIバージョン |
|------|-----------|----------|----------------|
| Ruby | [`sevenc-nanashi/voicevox.rb`](https://github.com/sevenc-nanashi/voicevox.rb) | `ffi` gem | 旧 (v0.14–v0.15) |
| Go | [`sh1ma/voicevoxcore.go`](https://github.com/sh1ma/voicevoxcore.go) | CGo (`#cgo LDFLAGS`) | 旧フラットAPI |
| Rust | [`tapoh22334/voicevox-core-rs`](https://github.com/tapoh22334/voicevox-core-rs) | `rust-bindgen` | 旧フラットAPI |
| Swift | [`fuziki/voicevox_core.swift`](https://github.com/fuziki/voicevox_core.swift) | SwiftPM Cブリッジ | 新 v0.x |
| Swift | [`yamachu/VoicevoxCoreSwift`](https://github.com/yamachu/VoicevoxCoreSwift) | C++ブリッジ | 新 v0.x |
| Scala/JVM | [`windymelt/voicevoxcore4s`](https://github.com/windymelt/voicevoxcore4s) | JNA | 旧フラットAPI |
| Java | [`Secret-Society-Braid/voicevox4j`](https://github.com/Secret-Society-Braid/voicevox4j) | JNA | 新 v0.x |
| Common Lisp | [`madosuki/cl-unofficial-voicevox-core-wrapper`](https://github.com/madosuki/cl-unofficial-voicevox-core-wrapper) | CFFI | 旧 |
| **PHP** | — | — | **未実装** |

> **注意**: voicevox-client組織（`voicevox-client/python` など）はHTTP APIラッパーであり、Cライブラリの直接ラッパーではありません。

### 全ラッパー共通のパターン

| 関心事 | パターン |
|--------|---------|
| ライブラリ読み込み | ライブラリ名またはフルパス |
| 文字列入力 | PHPの文字列 → C char* に変換 |
| 出力バッファ | ダブルポインタ `**char`/`**uint8_t` + `*size_t` |
| 結果コード | 整数コードを言語のエラー/例外にマッピング |
| メモリ解放 | `voicevox_wav_free()` と `voicevox_json_free()` を必ず呼ぶ |
| AudioQuery | JSON文字列としてやり取り（Cの構造体を解析不要） |

### RubyラッパーパターンとPHP FFIの対応[^5]

RubyのFFI gemの `MemoryPointer` は、PHP FFIのパターンと非常に似ています：

```ruby
# Ruby - ダブルポインタパターン
size_ptr   = FFI::MemoryPointer.new(:int)
return_ptr = FFI::MemoryPointer.new(:pointer)

Voicevox::Core.voicevox_tts(text, speaker_id, opts, size_ptr, return_ptr)

data_ptr = return_ptr.read_pointer
data = data_ptr.read_string(size_ptr.read_int)
Voicevox::Core.voicevox_wav_free(data_ptr)
```

### Common Lisp CFIパターン（PHP FFIに最も近い）[^6]

```lisp
(cffi:with-foreign-objects ((wav-length :uintptr)
                            (out-wav (:pointer :uint8)))
  (vv-tts text-c speaker-id options wav-length out-wav)
  ;; データをコピーしてCポインタを解放
  (cffi:voicevox_wav_free out-wav-ptr))
```

---

## 4. PHP FFIの機能

PHP FFI (`ext-ffi`) は **PHP 7.4** で導入され、PHP 8.x では安定して動作します。

### 読み込み方法[^7]

```php
// 方法1: FFI::cdef() — インラインC宣言 + ライブラリパス（CLI向け）
$ffi = FFI::cdef(
    'int printf(const char *format, ...);',
    'libc.so.6'  // macOSは 'libc.dylib'、Windowsは 'msvcrt.dll'
);

// 方法2: FFI::load() — #define FFI_LIB / #define FFI_SCOPE を含む.hファイルを読む
$ffi = FFI::load('/path/to/mylib.h');

// 方法3: FFI::scope() — OPcacheプリロード済みスコープを取得
$ffi = FFI::scope('MyLib');
```

### コアAPIメソッド

| メソッド | 用途 |
|---------|------|
| `FFI::cdef(string $code, ?string $lib)` | C宣言のパースとライブラリ読み込み |
| `FFI::new(string $type, bool $owned=true)` | Cメモリの確保 |
| `FFI::free(CData $ptr)` | 非管理メモリの解放 |
| `FFI::cast(string $type, $ptr)` | ポインタの型変換 |
| `FFI::addr(CData $ptr)` | ポインタの取得（Cの `&var`） |
| `FFI::string(CData $ptr, ?int $size)` | `char*` → PHP文字列 |
| `FFI::sizeof(CData|CType $ptr)` | `sizeof()` 相当 |

### クロスプラットフォームのライブラリパス検出[^8]

```php
private static function getLibraryPath(): string
{
    return match (PHP_OS_FAMILY) {
        'Darwin'  => 'libvoicevox_core.dylib',
        'Windows' => 'voicevox_core.dll',
        default   => 'libvoicevox_core.so',
    };
}
```

### シングルトン読み込みパターン（`idealo/php-rdkafka-ffi` より）[^9]

```php
private static ?FFI $ffi = null;

public static function getInstance(): FFI
{
    if (self::$ffi === null) {
        self::$ffi = FFI::cdef(
            file_get_contents(__DIR__ . '/voicevox_core_ffi.h'),
            self::getLibraryPath()
        );
    }
    return self::$ffi;
}
```

### ダブルポインタ出力バッファパターン

PHP FFIは他言語ラッパー全般で使われる `**ptr` + `*size` パターンと同等のことができます：

```php
// 出力用スロットの確保
$wav_size = $ffi->new('uint64_t');  // uintptr_t (WAVバイト数)
$wav_ptr  = $ffi->new('uint8_t*'); // WAVバッファへのポインタ

// C関数の呼び出し
$result = $ffi->voicevox_synthesizer_tts(
    $synthesizer,
    "テスト",
    $style_id,
    $tts_options,
    FFI::addr($wav_size),  // uintptr_t*
    FFI::addr($wav_ptr)    // uint8_t**
);

// Cメモリをいったん PHP 文字列にコピー
$wav_data = FFI::string($wav_ptr, (int) $wav_size->cdata);

// Cが確保したメモリを解放
$ffi->voicevox_wav_free($wav_ptr);
```

---

## 5. `voicevox_core.h` とのPHP FFI非互換性

生の `voicevox_core.h` は `FFI::cdef()` にそのまま渡すことは**できません**。事前処理が必要な6つの問題があります：[^10]

### ❌ 問題1: `#ifdef` / 条件付きコンパイル

ヘッダは相互排他のマクロガードを使用しています：
```c
#if !(defined(VOICEVOX_LINK_ONNXRUNTIME) || defined(VOICEVOX_LOAD_ONNXRUNTIME))
#error "either ... must be enabled"
#endif
```

`voicevox_onnxruntime_load_once()` などの関数は `#if defined(VOICEVOX_LOAD_ONNXRUNTIME)` でガードされています。PHP FFIにはプリプロセッサがないため、手動で解決する必要があります。

**対処法**: 非iOSリリースバイナリ（`LOAD`ブランチ）用にプリプロセス済みのPHP FFI宣言文字列を別途用意します。

### ❌ 問題2: `__declspec(dllimport)`（Windows）

エクスポート関数はすべて次のように囲まれています：
```c
#ifdef _WIN32
__declspec(dllimport)
#endif
void voicevox_json_free(char *json);
```

PHP FFIは `__declspec` を解釈できません。Linux/macOS上でも、このコードがあるとパースに失敗します。

**対処法**: FFI宣言文字列から `#ifdef _WIN32 / __declspec(dllimport) / #endif` のトリプレットをすべて除去します。

### ❌ 問題3: `uintptr_t` が未定義

`voicevox_synthesizer_tts()` など6か所で使用されています：
```c
uintptr_t *output_wav_length  // WAVバイト数の出力
```

PHP FFIのパーサーは `<stdint.h>` を読み込まないため、`uintptr_t` が未知の型になります。

**対処法**: FFI宣言文字列の先頭に `typedef uint64_t uintptr_t;` を追加します（64ビットプラットフォーム共通）。

### ❌ 問題4: `#ifdef __cplusplus` 付き型指定enum

```c
enum VoicevoxAccelerationMode
#ifdef __cplusplus
  : int32_t   // C++ 型付きenum — PHP FFIはこれをリテラルで解釈する
#endif
{
```

**対処法**: `typedef int32_t VoicevoxAccelerationMode;` 形式を使い、enum値はPHPのクラス定数として定義します。

### ❌ 問題5: 固定サイズ配列へのポインタtypedef

```c
typedef const uint8_t (*VoicevoxVoiceModelId)[16];  // 配列へのポインタtypedef
```

PHP FFIは `uint8_t[16]` をサポートしますが、配列へのポインタtypedefは問題があります。

**対処法**: このtypedefを省略し、該当関数では `const uint8_t *model_id` を使います（64ビット環境では同一ABI）。

### ⚠️ 問題6: `bool` 型

`<stdbool.h>` からの `bool` 型は明示的な宣言が必要な場合があります。

**対処法**: PHP FFIに `bool` が組み込まれていない場合は `typedef _Bool bool;` を追加します。

---

## 6. 実装ガイド

### 推奨プロジェクト構成

```
voicevox-php-ffi/
├── composer.json          # ext-ffiを必須とする
├── src/
│   ├── Voicevox.php           # メインファサードクラス
│   ├── VoicevoxFFI.php        # FFIローダー（シングルトン）
│   ├── VoicevoxSynthesizer.php # VoicevoxSynthesizer*ラッパー
│   ├── VoicevoxOnnxruntime.php # VoicevoxOnnxruntime*ラッパー
│   ├── AudioQuery.php          # AudioQuery JSONラッパー
│   ├── VoiceResponse.php       # WAV音声レスポンス
│   ├── ResultCode.php          # VoicevoxResultCode定数
│   └── Exception/
│       └── VoicevoxException.php
├── headers/
│   └── voicevox_core_ffi.h    # プリプロセス済みヘッダ（マクロなし）
└── README.md
```

### `composer.json`

```json
{
    "name": "your-org/voicevox-php-ffi",
    "type": "library",
    "require": {
        "php": "^8.1",
        "ext-ffi": "*"
    }
}
```

### `VoicevoxFFI.php` — シングルトンFFIローダー

```php
<?php

namespace YourOrg\Voicevox;

use FFI;

class VoicevoxFFI
{
    private static ?FFI $ffi = null;

    public static function getInstance(): FFI
    {
        return self::$ffi ??= FFI::cdef(
            file_get_contents(__DIR__ . '/../headers/voicevox_core_ffi.h'),
            self::getLibraryPath()
        );
    }

    public static function getLibraryPath(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin'  => 'libvoicevox_core.dylib',
            'Windows' => 'voicevox_core.dll',
            default   => 'libvoicevox_core.so',
        };
    }
}
```

### `VoicevoxSynthesizer.php` — リソースラッパー

```php
<?php

namespace YourOrg\Voicevox;

use FFI;
use FFI\CData;

class VoicevoxSynthesizer
{
    private CData $handle;  // VoicevoxSynthesizer*
    private FFI $ffi;

    private function __construct(CData $handle, FFI $ffi)
    {
        $this->handle = $handle;
        $this->ffi = $ffi;
    }

    public static function create(
        VoicevoxOnnxruntime $onnxruntime,
        CData $openJtalk,
        int $cpuNumThreads = 0,
    ): self {
        $ffi = VoicevoxFFI::getInstance();

        $options = $ffi->voicevox_make_default_initialize_options();
        $options->acceleration_mode = AccelerationMode::AUTO;
        $options->cpu_num_threads = $cpuNumThreads;

        $synthPtr = $ffi->new('struct VoicevoxSynthesizer*');
        $result = $ffi->voicevox_synthesizer_new(
            $onnxruntime->handle(),
            $openJtalk,
            $options,
            FFI::addr($synthPtr)
        );

        ResultCode::check($result, $ffi);

        return new self($synthPtr, $ffi);
    }

    /**
     * テキスト → WAVバイト列（1ステップ）
     */
    public function tts(string $text, int $styleId): string
    {
        $options = $this->ffi->voicevox_make_default_tts_options();

        $wavSize = $this->ffi->new('uint64_t');
        $wavPtr  = $this->ffi->new('uint8_t*');

        $result = $this->ffi->voicevox_synthesizer_tts(
            $this->handle,
            $text,
            $styleId,
            $options,
            FFI::addr($wavSize),
            FFI::addr($wavPtr)
        );

        ResultCode::check($result, $this->ffi);

        $wav = FFI::string($wavPtr, (int) $wavSize->cdata);
        $this->ffi->voicevox_wav_free($wavPtr);

        return $wav;
    }

    /**
     * テキスト → AudioQuery JSON（2ステップの前半）
     */
    public function audioQuery(string $text, int $styleId): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_synthesizer_create_audio_query(
            $this->handle,
            $text,
            $styleId,
            FFI::addr($jsonPtr)
        );

        ResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * AudioQuery JSON → WAVバイト列（2ステップの後半）
     */
    public function synthesis(string $audioQueryJson, int $styleId): string
    {
        $options = $this->ffi->voicevox_make_default_synthesis_options();

        $wavSize = $this->ffi->new('uint64_t');
        $wavPtr  = $this->ffi->new('uint8_t*');

        $result = $this->ffi->voicevox_synthesizer_synthesis(
            $this->handle,
            $audioQueryJson,
            $styleId,
            $options,
            FFI::addr($wavSize),
            FFI::addr($wavPtr)
        );

        ResultCode::check($result, $this->ffi);

        $wav = FFI::string($wavPtr, (int) $wavSize->cdata);
        $this->ffi->voicevox_wav_free($wavPtr);

        return $wav;
    }

    public function __destruct()
    {
        $this->ffi->voicevox_synthesizer_delete($this->handle);
    }
}
```

### 高レベルファサードの使用例

```php
<?php

use YourOrg\Voicevox\VoicevoxFFI;
use YourOrg\Voicevox\VoicevoxOnnxruntime;
use YourOrg\Voicevox\VoicevoxSynthesizer;

$ffi = VoicevoxFFI::getInstance();

// ONNXランタイムの読み込み
$onnxruntime = VoicevoxOnnxruntime::loadOnce(
    './voicevox_core/onnxruntime/lib/libvoicevox_onnxruntime.so.1.17.3'
);

// OpenJTalk解析器の作成
$openJtalkPtr = $ffi->new('struct OpenJtalkRc*');
$ffi->voicevox_open_jtalk_rc_new(
    './voicevox_core/dict/open_jtalk_dic_utf_8-1.11',
    FFI::addr($openJtalkPtr)
);

// 合成器の作成
$synthesizer = VoicevoxSynthesizer::create($onnxruntime, $openJtalkPtr);

// 音声モデルの読み込み
$modelPtr = $ffi->new('struct VoicevoxVoiceModelFile*');
$ffi->voicevox_voice_model_file_open('./models/vvms/0.vvm', FFI::addr($modelPtr));
$ffi->voicevox_synthesizer_load_voice_model($synthesizer->handle(), $modelPtr);

// 音声合成（ずんだもん あまあま = style_id: 1）
$wav = $synthesizer->tts('こんにちは、世界！', styleId: 1);
file_put_contents('output.wav', $wav);
```

---

## 7. ONNXランタイムの読み込み

最新v0.16+ APIでは、他の操作より先に**明示的に**ONNXランタイムを読み込む必要があります。3つの戦略があります：[^11]

### 戦略1: `voicevox_onnxruntime_load_once()` （推奨）

最もクリーンなアプローチ — C API自身の読み込みメカニズムを使用：

```php
$ffi = VoicevoxFFI::getInstance();

// バージョン付きファイル名を取得（例: "libvoicevox_onnxruntime.so.1.17.3"）
$versionedName = FFI::string($ffi->voicevox_get_onnxruntime_lib_versioned_filename());

$opts = $ffi->voicevox_make_default_load_onnxruntime_options();
$opts->filename = './voicevox_core/onnxruntime/lib/' . $versionedName;

$ortPtr = $ffi->new('struct VoicevoxOnnxruntime*');
$result = $ffi->voicevox_onnxruntime_load_once(
    $opts,
    FFI::addr($ortPtr)
);
```

### 戦略2: 空の `FFI::cdef('')` による事前読み込み（ScalaのSystem.load()相当）

```php
// まずONNXランタイムをプロセスメモリに読み込む（関数宣言なし）
// これは内部でdlopen()を呼び出し、voicevox_coreのリンカ依存を解決する
FFI::cdef('', '/path/to/voicevox_core/onnxruntime/lib/libvoicevox_onnxruntime.so.1.17.3');

// voicevox_coreの読み込み（onnxruntimeのシンボルは解決済み）
$ffi = FFI::cdef(/* 宣言 */, '/path/to/libvoicevox_core.so');
```

### 戦略3: 環境変数 `LD_LIBRARY_PATH`（最もシンプル）

```sh
export LD_LIBRARY_PATH=/path/to/voicevox_core/onnxruntime/lib:$LD_LIBRARY_PATH
php your_script.php
```

---

## 8. プラットフォームサポートと配布

### PHP.ini の設定要件

| 設定 | CLI | Webサーバー |
|------|-----|-----------|
| `ffi.enable=true` | デフォルト ✅ | 明示的な設定が必要 |
| `ffi.enable=preload` | 不要 | 本番モード；プリロードスクリプトが必要 |
| `opcache.enable=1` | 不要 | `preload` モードに必要 |
| `zend.max_allowed_stack_size=-1` | コールバック使用時 | PHP 8.3以降で必要 |

**CLIでの使用**: PHPにFFIがコンパイル済みであれば設定変更不要。`php -r "var_dump(extension_loaded('ffi'));"` で確認できます。

### ライブラリ検索パス

| OS      | デフォルト検索場所                            | 上書き方法                                             |
|---------|--------------------------------------|---------------------------------------------------|
| Linux   | `/usr/lib`、`/usr/local/lib`          | `LD_LIBRARY_PATH`                                 |
| macOS   | `/usr/local/lib`、`/opt/homebrew/lib` | `DYLD_LIBRARY_PATH`, `DYLD_FALLBACK_LIBRARY_PATH` |
| Windows | `PATH` のディレクトリ                       | `FFI::cdef()` に絶対パスを指定                            |

**推奨**: `FFI::cdef()` に絶対パスを使用してサーチパス問題を回避する：
```php
$libPath = '/path/to/voicevox_core/libvoicevox_core.so';
$ffi = FFI::cdef($declarations, $libPath);
```

### パッケージ配布のパターン

ネイティブの `.so`/`.dll` をPackagistにバンドルしているパッケージはありません。コミュニティのベストプラクティスは：[^12]

1. **`composer.json` に `ext-ffi` を必須指定** — FFI無効環境でのインストールが即座に失敗
2. **`.h` ファイルをバンドル**（マクロなしの前処理済み）: `src/headers/` 内に配置
3. **ネイティブライブラリのインストール方法を README に記載**
4. **ユーザーが設定できるパス**を環境変数またはコンストラクタ引数でサポート

```php
// ライブラリパスをユーザーが指定できるようにする
$voicevox = new Voicevox(
    libraryPath: getenv('VOICEVOX_CORE_LIB_PATH') ?: 'libvoicevox_core.so',
    onnxruntimePath: getenv('VOICEVOX_ONNXRUNTIME_PATH') ?: '',
    openJtalkDictDir: getenv('VOICEVOX_DICT_DIR') ?: '',
);
```

---

## 9. NativePHPとの互換性

### デフォルトバイナリ: FFIは含まれていない[^13]

NativePHPのバンドルPHPバイナリ（`NativePHP/php-bin`）には `ext-ffi` 拡張が含まれて**いません**。

### 回避策: カスタムPHPバイナリ

NativePHPはカスタムバイナリパスをサポートしています：

```php
// config/nativephp.php
'binary_path' => env('NATIVEPHP_PHP_BINARY_PATH', null),
```

**NativePHPでFFIを有効にする手順**:
1. `static-php-cli` で `ffi` を含むカスタムPHPバイナリをビルド
2. `NATIVEPHP_PHP_BINARY_PATH` をカスタムバイナリのパスに設定
3. VOICEVOX Coreのライブラリをアプリにバンドル

### Linuxでの注意点

musl libc（Alpine系）の静的PHPビルドでは、静的リンクの制限により **FFIは動的ライブラリを読み込めません**。Linuxで動作させるにはglibcターゲットでのビルドが必要です。macOSとWindowsはこの制限がありません。

### NativePHPでのFFI実用的な推奨

| プラットフォーム | 実現可能性 | 備考 |
|--------------|---------|------|
| macOS (arm64/x64) | ✅ 最も簡単 | dylib読み込みが素直に動作 |
| Windows x64 | ✅ 動作する | DLL読み込みは信頼性あり |
| Linux (glibc) | ⚠️ 要注意 | glibc対応static-php-cliビルドが必要 |
| Linux (musl) | ❌ 困難 | 動的ライブラリ読み込みの制限 |

---

## 10. 推奨PHP FFI宣言文字列

以下は `voicevox_core.h` から `VOICEVOX_LOAD_ONNXRUNTIME` ビルド用にプリプロセスしたPHP FFI互換サブセットです。`headers/voicevox_core_ffi.h` として保存してください：[^10]

```c
/* PHP FFI向け VOICEVOX Core v0.16+ 宣言（LOAD_ONNXRUNTIMEモード）
 * voicevox_core.h から前処理済み:
 *   - 全 #ifdef / #if / #define / #endif ブロックを削除
 *   - __declspec(dllimport) を削除
 *   - 不足している型定義を追加
 *   - VoicevoxVoiceModelId typedef を uint8_t* に置換
 */

/* ---- 型プリアンブル ---- */
typedef unsigned char      uint8_t;
typedef unsigned short     uint16_t;
typedef unsigned int       uint32_t;
typedef unsigned long long uint64_t;
typedef signed int         int32_t;
typedef signed long long   int64_t;
typedef uint64_t           uintptr_t;  /* 64ビットプラットフォーム用 */
typedef _Bool              bool;

/* ---- オペークハンドル ---- */
typedef struct OpenJtalkRc OpenJtalkRc;
typedef struct VoicevoxOnnxruntime VoicevoxOnnxruntime;
typedef struct VoicevoxSynthesizer VoicevoxSynthesizer;
typedef struct VoicevoxUserDict VoicevoxUserDict;
typedef struct VoicevoxVoiceModelFile VoicevoxVoiceModelFile;

/* ---- enum（int32_tとして） ---- */
typedef int32_t VoicevoxAccelerationMode;
typedef int32_t VoicevoxResultCode;
typedef int32_t VoicevoxUserDictWordType;
typedef uint32_t VoicevoxStyleId;

/* ---- 具体的な構造体 ---- */
typedef struct VoicevoxLoadOnnxruntimeOptions {
    char *filename;
} VoicevoxLoadOnnxruntimeOptions;

typedef struct VoicevoxInitializeOptions {
    int32_t  acceleration_mode;
    uint16_t cpu_num_threads;
} VoicevoxInitializeOptions;

typedef struct VoicevoxSynthesisOptions {
    bool enable_interrogative_upspeak;
} VoicevoxSynthesisOptions;

typedef struct VoicevoxTtsOptions {
    bool enable_interrogative_upspeak;
} VoicevoxTtsOptions;

typedef struct VoicevoxUserDictWord {
    const char *surface;
    const char *pronunciation;
    uintptr_t   accent_type;
    int32_t     word_type;
    uint32_t    priority;
} VoicevoxUserDictWord;

/* ---- ONNXランタイム（LOADモード） ---- */
const char *voicevox_get_onnxruntime_lib_versioned_filename(void);
const char *voicevox_get_onnxruntime_lib_unversioned_filename(void);
struct VoicevoxLoadOnnxruntimeOptions voicevox_make_default_load_onnxruntime_options(void);
const struct VoicevoxOnnxruntime *voicevox_onnxruntime_get(void);
int32_t voicevox_onnxruntime_load_once(
    struct VoicevoxLoadOnnxruntimeOptions options,
    const struct VoicevoxOnnxruntime **out_onnxruntime
);

/* ---- OpenJTalk ---- */
int32_t voicevox_open_jtalk_rc_new(
    const char *open_jtalk_dic_dir,
    struct OpenJtalkRc **out_open_jtalk
);
int32_t voicevox_open_jtalk_rc_use_user_dict(
    const struct OpenJtalkRc *open_jtalk,
    const struct VoicevoxUserDict *user_dict
);
void voicevox_open_jtalk_rc_delete(struct OpenJtalkRc *open_jtalk);

/* ---- コア ---- */
struct VoicevoxInitializeOptions voicevox_make_default_initialize_options(void);
const char *voicevox_get_version(void);

/* ---- 音声モデルファイル ---- */
int32_t voicevox_voice_model_file_open(
    const char *path,
    struct VoicevoxVoiceModelFile **out_model
);
void voicevox_voice_model_file_id(
    const struct VoicevoxVoiceModelFile *model,
    uint8_t *output_voice_model_id
);
char *voicevox_voice_model_file_create_metas_json(
    const struct VoicevoxVoiceModelFile *model
);
void voicevox_voice_model_file_delete(struct VoicevoxVoiceModelFile *model);

/* ---- 合成器 ---- */
int32_t voicevox_synthesizer_new(
    const struct VoicevoxOnnxruntime *onnxruntime,
    const struct OpenJtalkRc *open_jtalk,
    struct VoicevoxInitializeOptions options,
    struct VoicevoxSynthesizer **out_synthesizer
);
void voicevox_synthesizer_delete(struct VoicevoxSynthesizer *synthesizer);
int32_t voicevox_synthesizer_load_voice_model(
    const struct VoicevoxSynthesizer *synthesizer,
    const struct VoicevoxVoiceModelFile *model
);
int32_t voicevox_synthesizer_unload_voice_model(
    const struct VoicevoxSynthesizer *synthesizer,
    const uint8_t *model_id
);
bool voicevox_synthesizer_is_gpu_mode(const struct VoicevoxSynthesizer *synthesizer);
bool voicevox_synthesizer_is_loaded_voice_model(
    const struct VoicevoxSynthesizer *synthesizer,
    const uint8_t *model_id
);
char *voicevox_synthesizer_create_metas_json(
    const struct VoicevoxSynthesizer *synthesizer
);
int32_t voicevox_onnxruntime_create_supported_devices_json(
    const struct VoicevoxOnnxruntime *onnxruntime,
    char **output_supported_devices_json
);

/* ---- AudioQuery ---- */
int32_t voicevox_synthesizer_create_audio_query(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *text,
    uint32_t style_id,
    char **output_audio_query_json
);
int32_t voicevox_synthesizer_create_accent_phrases(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *text,
    uint32_t style_id,
    char **output_accent_phrases_json
);
int32_t voicevox_synthesizer_replace_mora_data(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *accent_phrases_json,
    uint32_t style_id,
    char **output_accent_phrases_json
);
int32_t voicevox_synthesizer_replace_phoneme_length(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *accent_phrases_json,
    uint32_t style_id,
    char **output_accent_phrases_json
);
int32_t voicevox_synthesizer_replace_mora_pitch(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *accent_phrases_json,
    uint32_t style_id,
    char **output_accent_phrases_json
);

/* ---- 音声合成 ---- */
struct VoicevoxSynthesisOptions voicevox_make_default_synthesis_options(void);
int32_t voicevox_synthesizer_synthesis(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *audio_query_json,
    uint32_t style_id,
    struct VoicevoxSynthesisOptions options,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);

struct VoicevoxTtsOptions voicevox_make_default_tts_options(void);
int32_t voicevox_synthesizer_tts(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *text,
    uint32_t style_id,
    struct VoicevoxTtsOptions options,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);
int32_t voicevox_synthesizer_tts_from_kana(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *kana,
    uint32_t style_id,
    struct VoicevoxTtsOptions options,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);

/* ---- 歌声合成 ---- */
int32_t voicevox_synthesizer_create_sing_frame_audio_query(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *score_json,
    uint32_t style_id,
    char **output_frame_audio_query_json
);
int32_t voicevox_synthesizer_frame_synthesis(
    const struct VoicevoxSynthesizer *synthesizer,
    const char *frame_audio_query_json,
    uint32_t style_id,
    uintptr_t *output_wav_length,
    uint8_t **output_wav
);

/* ---- メモリ管理 ---- */
void voicevox_json_free(char *json);
void voicevox_wav_free(uint8_t *wav);
const char *voicevox_error_result_to_message(int32_t result_code);

/* ---- ユーザー辞書 ---- */
struct VoicevoxUserDictWord voicevox_user_dict_word_make(
    const char *surface,
    const char *pronunciation,
    uintptr_t accent_type
);
struct VoicevoxUserDict *voicevox_user_dict_new(void);
int32_t voicevox_user_dict_load(
    const struct VoicevoxUserDict *user_dict,
    const char *dict_path
);
int32_t voicevox_user_dict_add_word(
    const struct VoicevoxUserDict *user_dict,
    const struct VoicevoxUserDictWord *word,
    uint8_t *output_word_uuid
);
int32_t voicevox_user_dict_update_word(
    const struct VoicevoxUserDict *user_dict,
    const uint8_t *word_uuid,
    const struct VoicevoxUserDictWord *word
);
int32_t voicevox_user_dict_remove_word(
    const struct VoicevoxUserDict *user_dict,
    const uint8_t *word_uuid
);
int32_t voicevox_user_dict_to_json(
    const struct VoicevoxUserDict *user_dict,
    char **output_json
);
int32_t voicevox_user_dict_save(
    const struct VoicevoxUserDict *user_dict,
    const char *path
);
void voicevox_user_dict_delete(struct VoicevoxUserDict *user_dict);

/* ---- バリデーション ---- */
int32_t voicevox_audio_query_validate(const char *audio_query_json);
int32_t voicevox_accent_phrase_validate(const char *accent_phrase_json);
int32_t voicevox_mora_validate(const char *mora_json);
```

### PHPでのenum定数定義

```php
<?php

namespace YourOrg\Voicevox;

class ResultCode
{
    public const OK = 0;
    public const NOT_LOADED_OPENJTALK_DICT_ERROR = 1;
    public const GET_SUPPORTED_DEVICES_ERROR = 3;
    public const GPU_SUPPORT_ERROR = 4;
    public const INIT_INFERENCE_RUNTIME_ERROR = 29;
    public const STYLE_NOT_FOUND_ERROR = 6;
    public const MODEL_NOT_FOUND_ERROR = 7;
    public const RUN_MODEL_ERROR = 8;
    public const ANALYZE_TEXT_ERROR = 11;
    public const INVALID_UTF8_INPUT_ERROR = 12;
    public const PARSE_KANA_ERROR = 13;
    public const INVALID_AUDIO_QUERY_ERROR = 14;
    public const INVALID_ACCENT_PHRASE_ERROR = 15;

    public static function check(int $code, \FFI $ffi): void
    {
        if ($code !== self::OK) {
            $message = FFI::string($ffi->voicevox_error_result_to_message($code));
            throw new VoicevoxException($message, $code);
        }
    }
}

class AccelerationMode
{
    public const AUTO = 0;
    public const CPU  = 1;
    public const GPU  = 2;
}
```

---

## 11. 信頼度評価

| 知見 | 信頼度 | ソース |
|------|-------|--------|
| C APIは63個の関数を持つ | 高 | ヘッダ直接調査 |
| APIにコールバックなし | 高 | ヘッダ分析 — 関数ポインタtypedefなし |
| PHP FFI非互換が6点ある | 高 | ヘッダソース + PHP FFIドキュメント |
| `uintptr_t` → `uint64_t` のfix | 高 | bindgenレイアウトテストで確認済み |
| `FFI::cdef('')` でライブラリを事前読み込み可能 | 中 | PHP内部からの推測；VOICEVOX実機テストは未実施 |
| NativePHPのデフォルトバイナリにFFI非搭載 | 高 | `NativePHP/php-bin:php-extensions.txt` で確認 |
| musl libcでのLinuxFFIは困難 | 高 | `static-php-cli` ドキュメントより |
| Rubyラッパーは旧API（v0.14–v0.15）対応 | 高 | ソースコード分析 |
| PHP 8.x FFIに`bool`組み込み済み | 中 | 未確認；`typedef _Bool bool;` を安全策として推奨 |

---

## 脚注

[^1]: [VOICEVOX/voicevox_core リリース](https://github.com/VOICEVOX/voicevox_core/releases) — v0.16.4 リリースアセット
[^2]: [crates/voicevox_core_c_api/include/voicevox_core.h](https://github.com/VOICEVOX/voicevox_core/blob/main/crates/voicevox_core_c_api/include/voicevox_core.h) — SHA `81a2d8e`、cbindgen 0.28.0
[^3]: [example/cpp/unix/talk.cpp](https://github.com/VOICEVOX/voicevox_core/blob/main/example/cpp/unix/talk.cpp)
[^4]: [VOICEVOX/voicevox_core README.md](https://github.com/VOICEVOX/voicevox_core/blob/main/README.md) — コミュニティラッパーセクション
[^5]: [sevenc-nanashi/voicevox.rb:examples/repl_core.rb:22-36](https://github.com/sevenc-nanashi/voicevox.rb/blob/main/examples/repl_core.rb)
[^6]: [madosuki/cl-unofficial-voicevox-core-wrapper](https://github.com/madosuki/cl-unofficial-voicevox-core-wrapper)
[^7]: [JetBrains/phpstorm-stubs:FFI/FFI.php](https://github.com/JetBrains/phpstorm-stubs/blob/master/FFI/FFI.php) — PHP FFI型スタブ正典
[^8]: [idealo/php-rdkafka-ffi:src/RdKafka/FFI/Library.php:87-103](https://github.com/idealo/php-rdkafka-ffi/blob/main/src/RdKafka/FFI/Library.php)
[^9]: [idealo/php-rdkafka-ffi:src/RdKafka/FFI/Library.php:60-85](https://github.com/idealo/php-rdkafka-ffi/blob/main/src/RdKafka/FFI/Library.php)
[^10]: [crates/voicevox_core_c_api/include/voicevox_core.h](https://github.com/VOICEVOX/voicevox_core/blob/main/crates/voicevox_core_c_api/include/voicevox_core.h) — 問題1〜6の分析
[^11]: [example/cpp/unix/talk.cpp:27-37](https://github.com/VOICEVOX/voicevox_core/blob/main/example/cpp/unix/talk.cpp)；[windymelt/voicevoxcore4s:Main.scala:14-18](https://github.com/windymelt/voicevoxcore4s)
[^12]: [skoro/php-tkui](https://github.com/skoro/php-tkui)；[reliforp/reli-prof](https://github.com/reliforp/reli-prof) — パッケージ構成パターン
[^13]: [NativePHP/php-bin:php-extensions.txt](https://github.com/NativePHP/php-bin) — バンドルPHP拡張一覧
