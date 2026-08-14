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
- `composer run test:integration` - Run integration tests.
- `composer run lint` - Run pint code formatter.

クラウドエージェントではVOICEVOX動的ライブラリが読み込めないので実際に音声合成を行うことはできない。  
GitHub Actions専用に`test:integration`を用意しているのでテストだけ書いて動的ライブラリ込みのテスト実行はCIで行う。`.github/workflows/integration-tests.yml`

`VOICEVOX_CORE_LIB_PATH`で動的ライブラリを指定すればローカルのCopilot CLIでも読み込めそう。

## ディレクトリ構成

名前空間：`Revolution\Voicevox\Core\`

- src/
- headers/voicevox_core_ffi.h 
- tests/
- example/ サンプルコード。開発時の手動テスト用。
- voicevox_core/ 公式のgit submodule `git submodule update --remote --merge`で更新。

## 資料

PHP版のための調査結果。これを元に実装する。

- docs/voicevox-core-php-ffi-en.md

## コーディングガイドライン

- VOICEVOXコア公式のPython版(blocking.py)を参考にクラス名、メソッド名を揃え忠実に移植する。メソッド名はPythonのスネークケース、PHPはキャメルケースなので一般的な慣習に合わせる。
- 使いやすさは別途 [Laravel版パッケージ](https://github.com/invokable/laravel-voicevox) が担当する。

## 音声モデルファイル(.vvm)とスタイルIDの対応表

https://github.com/VOICEVOX/voicevox_vvm
