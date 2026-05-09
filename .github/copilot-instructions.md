# VOICEVOX Core for PHP Project Guidelines

## Overview

[VOICEVOXコア](https://github.com/VOICEVOX/voicevox_core) のPHP版FFIラッパー。

PHPのFFIはWebサーバーでは無効にされてることが多いのでローカルCLIでの利用を想定。

## VOICEVOXの構成

- [VOICEVOXエディター](https://github.com/VOICEVOX/voicevox)：GUIアプリケーション。Electron、TypeScript、Vue。
- [VOICEVOXエンジン](https://github.com/VOICEVOX/voicevox_engine)：Webサーバーとして提供されるテキスト音声合成 API。Python、FastAPI、OpenJTalk。
- [VOICEVOXコア](https://github.com/VOICEVOX/voicevox_core)：音声合成の動的ライブラリ。Rust、onnxruntime。C APIの動的ライブラリ（.so/.dll/.dylib）がある。

コアの各言語版FFIラッパーがいろいろ作られている。 このプロジェクトはそれのPHP版。

## Technology Stack

- **Language**: PHP 8.3+, FFI
- **Testing**: Pest PHP 4.x
- **Code Quality**: Laravel Pint (PSR-12)

Pure PHP用パッケージなのでフレームワークには依存しない。PestやPintのツールは使う。

## Command
- `composer run test` - Run pest tests.
- `composer run lint` - Run pint code formatter.

## ディレクトリ構成

名前空間：`Revolution\Voicevox\Core\`

- src/
- headers/voicevox_core_ffi.h 
- scripts/ 公式のvoicevox_core.hをPHP用のvoicevox_core_ffi.hに変換するPHPスクリプト
- tests/
- voicevox_core/ 公式のgit submodule

## 資料

PHP版のための調査結果。これを元に実装する。

- docs/voicevox-core-php-ffi-en.md

## コーディングガイドライン

- VOICEVOXコア公式のPython版を参考にクラス名、メソッド名を揃え忠実に移植する。
- 使いやすさは別途 [Laravel版パッケージ](https://github.com/invokable/laravel-voicevox) が担当する。
