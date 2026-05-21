# VOICEVOX Core for PHP

[![tests](https://github.com/invokable/voicevox-core-php/actions/workflows/tests.yml/badge.svg)](https://github.com/invokable/voicevox-core-php/actions/workflows/tests.yml)
[![integration tests](https://github.com/invokable/voicevox-core-php/actions/workflows/integration-tests.yml/badge.svg)](https://github.com/invokable/voicevox-core-php/actions/workflows/integration-tests.yml)
[![linter](https://github.com/invokable/voicevox-core-php/actions/workflows/lint.yml/badge.svg)](https://github.com/invokable/voicevox-core-php/actions/workflows/lint.yml)
[![Maintainability](https://qlty.sh/gh/invokable/projects/voicevox-core-php/maintainability.svg)](https://qlty.sh/gh/invokable/projects/voicevox-core-php)
[![Code Coverage](https://qlty.sh/gh/invokable/projects/voicevox-core-php/coverage.svg)](https://qlty.sh/gh/invokable/projects/voicevox-core-php)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/invokable/voicevox-core-php)

PHP FFI wrapper for [VOICEVOX CORE](https://github.com/VOICEVOX/voicevox_core) — the text-to-speech engine library from the VOICEVOX project.

This is a package for pure PHP. For general use, the [Laravel version](https://github.com/invokable/laravel-voicevox) is recommended.

## Requirements

- PHP 8.3+
- `ext-ffi` extension enabled
- VOICEVOX CORE 0.16+

> [!NOTE]
> PHP FFI is typically disabled in web server environments (e.g., FPM with `ffi.enable=false`). This library is intended for **local CLI use only**.

## Installation

```bash
composer require revolution/voicevox-core
```

## Library Setup (Linux / macOS)

This package requires the VOICEVOX CORE dynamic library (`.so` / `.dylib`), the ONNX Runtime library, and the OpenJTalk dictionary.

### 1. Download voicevox_core

Download the appropriate downloader for your OS and architecture from [voicevox_core releases](https://github.com/VOICEVOX/voicevox_core/releases) and run it. This creates a `voicevox_core` directory in the current directory containing:

- `dict/open_jtalk_dic_*/` — OpenJTalk dictionary
- `c_api/lib/` — Dynamic library file (`.so`, `.dylib`, or `.dll`)
- `models/` — compressed model files (`.vvm`)
- `onnxruntime/` — ONNX Runtime library

### 2. Move to a permanent location

```bash
mv voicevox_core ~/.local/voicevox_core
```

### 3. Create a symlink (Recommended)

Create a symlink so the library can be found automatically:

**macOS:**
```bash
# Replace [VOICEVOX_CORE_DIR] with the absolute path to voicevox_core
ln -s [VOICEVOX_CORE_DIR]/libvoicevox_core.dylib /usr/local/lib/libvoicevox_core.dylib
```

If you cannot load from `/usr/local/lib/`, set `DYLD_FALLBACK_LIBRARY_PATH` in your `.zshrc` file or similar.
```bash
export DYLD_FALLBACK_LIBRARY_PATH="$HOME/lib:/usr/local/lib:/usr/lib"
```

**Linux:**
```bash
ln -s [VOICEVOX_CORE_DIR]/libvoicevox_core.so /usr/local/lib/libvoicevox_core.so
```

> [!WARNING]
> Always use **absolute paths** when using `ln -s`.

### Alternative: Environment variable

If you cannot create a symlink, set the `VOICEVOX_CORE_LIB_PATH` environment variable to the full path of the library file:

```bash
export VOICEVOX_CORE_LIB_PATH=/path/to/libvoicevox_core.dylib
```

```bash
export VOICEVOX_CORE_LIB_PATH="$HOME/.local/voicevox_core/c_api/lib/libvoicevox_core.dylib"
```

## Usage Example

The following `talk.php` demonstrates text-to-speech synthesis:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;

// Paths — adjust to your voicevox_core installation
$voicevoxCoreDir = getenv('HOME') . '/.local/voicevox_core';
$onnxruntimeFilename = $voicevoxCoreDir . '/onnxruntime/lib/' . Onnxruntime::libVersionedFilename();
$dictDir = $voicevoxCoreDir . '/dict/open_jtalk_dic_utf_8-1.11';
$vvmPath  = $voicevoxCoreDir . '/models/vvms/0.vvm';

// Text and style to synthesize
$text    = 'この音声は、ボイスボックスを使用して、出力されています。';
$styleId = 0;
$outPath = './output.wav';

// Initialize
$onnxruntime = Onnxruntime::loadOnce($onnxruntimeFilename);
$openJtalk   = new OpenJtalk($dictDir);
$synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Auto);

// Load voice model
$model = VoiceModelFile::open($vvmPath);
$synthesizer->loadVoiceModel($model);

// Synthesize
$audioQuery = $synthesizer->createAudioQuery($text, $styleId);
$wav        = $synthesizer->synthesis($audioQuery, $styleId);

file_put_contents($outPath, $wav);
echo 'Wrote ' . $outPath . PHP_EOL;
```

Run with:

```bash
php talk.php
```

## Testing

- `composer run test` runs the default `Unit` testsuite only.
- Runtime-backed tests live in `tests/Integration` and are excluded from the default run.
- Run them explicitly with `vendor/bin/pest --compact --testsuite=Integration` (or `composer run test:integration`) after setting `VOICEVOX_CORE_TEST_ROOT`. GitHub Actions uses the dedicated `.github/workflows/integration-tests.yml` workflow for this suite.

## API Reference

### `Onnxruntime`

ONNX Runtime loader. A process-level singleton — only one instance exists per process.

| Method | Description |
|--------|-------------|
| `static loadOnce(string $filename = ''): self` | Load and initialize ONNX Runtime. On subsequent calls, ignores the argument and returns the existing instance. |
| `static get(): ?self` | Return the existing instance, or `null` if not yet initialized. |
| `supportedDevices(): string` | Return available device information as a JSON string. |
| `static libVersionedFilename(): string` | Return the versioned filename of the ONNX Runtime library (e.g., `libvoicevox_onnxruntime.1.17.3.dylib`). |
| `static libUnversionedFilename(): string` | Return the unversioned filename of the ONNX Runtime library. |

**Constants:**

| Constant | Description |
|----------|-------------|
| `LIB_NAME` | Library base name (`voicevox_onnxruntime`) |
| `LIB_VERSION` | Recommended ONNX Runtime version |

---

### `OpenJtalk`

Text analyzer using OpenJTalk.

| Method | Description |
|--------|-------------|
| `__construct(string $openJtalkDictDir)` | Initialize with the OpenJTalk dictionary directory path. |
| `analyze(string $text): string` | Analyze Japanese text and return an accent phrase array as a JSON string. |
| `useUserDict(UserDict $userDict): void` | Attach a user dictionary. Must be called again if the dictionary changes. |

---

### `VoiceModelFile`

Voice model file (`.vvm` file).

| Method | Description |
|--------|-------------|
| `static open(string $path): self` | Open a `.vvm` file. |
| `id(): string` | Return the voice model ID as a hex string (16 bytes). |
| `createMetasJson(): string` | Return speaker metadata as a JSON string. |
| `close(): void` | Close the file and release resources. |

---

### `Synthesizer`

Main text-to-speech synthesizer.

| Method | Description |
|--------|-------------|
| `__construct(Onnxruntime $onnxruntime, OpenJtalk $openJtalk, AccelerationMode $accelerationMode = Auto, int $cpuNumThreads = 0)` | Initialize the synthesizer. |
| `onnxruntime(): Onnxruntime` | Return the `Onnxruntime` instance held by this synthesizer. |
| `isGpuMode(): bool` | Return whether GPU mode is active. |
| `metas(): string` | Return loaded speaker metadata as a JSON string. |
| `loadVoiceModel(VoiceModelFile $model): void` | Load a voice model. |
| `unloadVoiceModel(string $voiceModelId): void` | Unload a voice model by its hex ID. |
| `isLoadedVoiceModel(string $voiceModelId): bool` | Check whether a voice model is loaded. |
| `createAudioQuery(string $text, int $styleId): string` | Generate an AudioQuery JSON from Japanese text. |
| `createAudioQueryFromKana(string $kana, int $styleId): string` | Generate an AudioQuery JSON from AquesTalk-style kana notation. |
| `createAccentPhrases(string $text, int $styleId): string` | Generate an accent phrase array JSON from Japanese text. |
| `createAccentPhrasesFromKana(string $kana, int $styleId): string` | Generate an accent phrase array JSON from kana notation. |
| `replaceMoraData(string $accentPhrasesJson, int $styleId): string` | Return new accent phrases with updated mora pitch and phoneme length. |
| `replacePhonemeLength(string $accentPhrasesJson, int $styleId): string` | Return new accent phrases with updated phoneme length. |
| `replaceMoraPitch(string $accentPhrasesJson, int $styleId): string` | Return new accent phrases with updated mora pitch. |
| `synthesis(string $audioQueryJson, int $styleId, bool $enableInterrogativeUpspeak = true): string` | Synthesize speech from an AudioQuery JSON. Returns WAV binary. |
| `tts(string $text, int $styleId, bool $enableInterrogativeUpspeak = true): string` | Synthesize speech from Japanese text in one step. Returns WAV binary. |
| `ttsFromKana(string $kana, int $styleId, bool $enableInterrogativeUpspeak = true): string` | Synthesize speech from kana notation. Returns WAV binary. |
| `createSingFrameAudioQuery(string $scoreJson, int $styleId): string` | Generate a singing synthesis query JSON from a musical score. |
| `frameSynthesis(string $frameAudioQueryJson, int $styleId): string` | Synthesize singing audio from a frame audio query. Returns WAV binary. |
| `createSingFrameF0(string $scoreJson, string $frameAudioQueryJson, int $styleId): string` | Generate per-frame F0 (fundamental frequency) values as a JSON float array. |
| `createSingFrameVolume(string $scoreJson, string $frameAudioQueryJson, int $styleId): string` | Generate per-frame volume values as a JSON float array. |

---

### `VoicevoxCore`

Global utility functions for VOICEVOX Core.

| Method | Description |
|--------|-------------|
| `getVersion(): string` | Return the VOICEVOX Core version as a SemVer string. |
| `audioQueryCreateFromAccentPhrases(string $accentPhrasesJson): string` | Generate an AudioQuery JSON from an accent phrase array JSON. |
| `audioQueryValidate(string $audioQueryJson): void` | Validate an `AudioQuery` JSON. Throws `VoicevoxException` if invalid. |
| `accentPhraseValidate(string $accentPhraseJson): void` | Validate an `AccentPhrase` JSON. Throws `VoicevoxException` if invalid. |
| `moraValidate(string $moraJson): void` | Validate a `Mora` JSON. Throws `VoicevoxException` if invalid. |
| `scoreValidate(string $scoreJson): void` | Validate a `Score` JSON. Throws `VoicevoxException` if invalid. |
| `noteValidate(string $noteJson): void` | Validate a `Note` JSON. Throws `VoicevoxException` if invalid. |
| `frameAudioQueryValidate(string $frameAudioQueryJson): void` | Validate a `FrameAudioQuery` JSON. Throws `VoicevoxException` if invalid. |
| `framePhonemeValidate(string $framePhonemeJson): void` | Validate a `FramePhoneme` JSON. Throws `VoicevoxException` if invalid. |
| `ensureCompatible(string $scoreJson, string $frameAudioQueryJson): void` | Check that a score and frame audio query are compatible. Throws `VoicevoxException` if not. |

---

### `UserDict`

User dictionary for custom word pronunciation.

| Method | Description |
|--------|-------------|
| `__construct()` | Create a new empty user dictionary. |
| `load(string $path): void` | Load a user dictionary from a file. |
| `save(string $path): void` | Save the user dictionary to a file. |
| `addWord(string $surface, string $pronunciation, int $accentType, UserDictWordType $wordType = CommonNoun, int $priority = 5): string` | Add a word. Returns the word UUID as a hex string. |
| `updateWord(string $wordUuid, string $surface, string $pronunciation, int $accentType, UserDictWordType $wordType = CommonNoun, int $priority = 5): void` | Update an existing word by UUID. |
| `removeWord(string $wordUuid): void` | Remove a word by UUID. |
| `importDict(UserDict $other): void` | Import words from another `UserDict`. |
| `toJson(): string` | Return all words as a JSON string. |

---

### `AccelerationMode` (enum)

Hardware acceleration mode for the synthesizer.

| Case | Value | Description |
|------|-------|-------------|
| `Auto` | `0` | Automatically select the best available mode. |
| `Cpu` | `1` | Force CPU mode. |
| `Gpu` | `2` | Force GPU mode. |

---

### `UserDictWordType` (enum)

Word type for user dictionary entries.

| Case         | Value | Description |
|--------------|-------|-------------|
| `ProperNoun` | `0`   | Proper noun |
| `CommonNoun` | `1`   | Common noun |
| `Verb`       | `2`   | Verb        |
| `Adjective`  | `3`   | Adjective   |
| `Suffix`     | `4`   | Suffix      |

---

### `VoicevoxException`

Thrown when a VOICEVOX Core C API call returns an error code. The exception message contains the error description from the library.

## License

MIT
