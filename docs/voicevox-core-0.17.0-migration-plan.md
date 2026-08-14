# VOICEVOX CORE 0.17.0 対応計画

## 目的

VOICEVOX CORE 0.17.0 の C API と、PHP FFI ラッパーの宣言・公開 API・利用例を一致させる。
対象は `voicevox_core` サブモジュールの 0.17.0（タグ `0.17.0`）とする。

## 0.17.0 で確認した変更点

| 項目 | 0.16.4 | 0.17.0 | PHP側への影響 |
| --- | --- | --- | --- |
| ONNX Runtimeの推奨ファイル名 | `voicevox_get_onnxruntime_lib_versioned_filename` / `..._unversioned_filename` | `voicevox_get_onnxruntime_lib_recommended_versioned_filename` / `..._recommended_unversioned_filename` | FFI宣言と `Onnxruntime` の呼び出しを変更 |
| ONNX Runtimeのバージョン情報 | 推奨バージョンのみ | 最小マイナー `17`、推奨 `1.23.2`、最大サポート `29` | 定数とヘルパーを追加・更新 |
| 既存モデルの扱い | `load_voice_model(synthesizer, model)` | `VoicevoxLoadVoiceModelOptions` と `on_existing` を追加 | enum、FFI構造体、`Synthesizer::loadVoiceModel()` を変更 |
| ユーザー辞書の優先度 | `uint32_t` | `uint8_t`（有効範囲は 0〜10） | FFI構造体を変更し、代入前に範囲を検証 |
| 結果コード | `VOICEVOX_RESULT_INVALID_MODEL_HEADER_ERROR` | `VOICEVOX_RESULT_INVALID_MODEL_FORMAT_ERROR` | PHP enumのcase名を変更 |
| VVM | 旧形式中心 | `vvm_format_version=2` をサポート | 0.17.0のモデルで統合テストを実行し、利用例を確認 |

## 修正計画

### 1. FFI宣言を0.17.0へ同期

対象: `headers/voicevox_core_ffi.h`

- 0.17.0の `voicevox_core.h` から PHP FFI で利用する宣言を再生成・確認する。
- `VoicevoxOnExistingVoiceModelId` と値 `ERROR=0`、`RELOAD=1`、`SKIP=2` を追加する。
- `VoicevoxLoadVoiceModelOptions` と `on_existing` フィールドを追加する。
- `voicevox_make_default_load_voice_model_options()` を追加する。
- `voicevox_synthesizer_load_voice_model()` に options 引数を追加する。
- ONNX Runtimeの最小・最大マイナーバージョン関数を追加する。
- 推奨ファイル名関数を追加し、0.16系の旧シンボルは削除する。
- `VoicevoxUserDictWord.priority` を `uint8_t` に変更する。
- 0.17.0の結果コード名に合わせて、`INVALID_MODEL_FORMAT` を反映する。
- `const char *filename` など、現行ヘッダーのFFI向け型補正を維持する。

### 2. ONNX Runtimeラッパーを更新

対象: `src/Onnxruntime.php`

- `LIB_MIN_REQUIRED_MINOR_VERSION = 17`、`LIB_MAX_SUPPORTED_MINOR_VERSION = 29`、
  `LIB_RECOMMENDED_NAME = 'voicevox_onnxruntime'`、
  `LIB_RECOMMENDED_VERSION = '1.23.2'` を定義する。
- `libRecommendedVersionedFilename()` と
  `libRecommendedUnversionedFilename()` を追加し、新しいC関数を呼び出す。
- 既存の `LIB_NAME`、`LIB_VERSION`、`libVersionedFilename()`、
  `libUnversionedFilename()` は、既存利用者の破壊を避けるため推奨名への
  deprecated alias として残す。
- `loadOnce('')` は CORE のデフォルト（推奨バージョン付きファイル名）を利用し、
  明示パス指定時はこれまで通り指定値を `filename` に渡す。

### 3. モデル読み込みオプションを公開

対象: `src/Enums/OnExistingVoiceModelId.php`（新規）、
`src/Synthesizer.php`

- C enumに対応する backed enumを追加する。
- `Synthesizer::loadVoiceModel()` に `OnExistingVoiceModelId $onExisting =
  OnExistingVoiceModelId::Error` を追加する。
- `voicevox_make_default_load_voice_model_options()` で初期化し、
  `$options->on_existing` にenum値を設定してから3引数版のC関数を呼び出す。
- README、サンプル、統合テストで `RELOAD` / `SKIP` を指定できることを説明する。

### 4. ユーザー辞書と結果コードを更新

対象: `src/UserDict.php`、`src/Enums/VoicevoxResultCode.php`

- `priority` をC側の `uint8_t` に合わせ、CDataへの代入前にCOREの有効範囲
  `0..10` を検証する。範囲外は既存の例外方針に合わせて明示的に拒否する。
- `InvalidModelHeaderError` を `InvalidModelFormatError` に改名し、値 `28` を維持する。
- enum値を確認するユニットテストを更新する。

### 5. テストを0.17.0仕様へ更新

対象: `tests/Unit/EnumsTest.php`、`tests/Integration/VoicevoxRuntimeTest.php`、
必要に応じて新規ユニットテスト

- 新しいenumの値、ONNX Runtimeの定数、FFI呼び出し名を確認する。
- 0.17.0のランタイムで推奨ファイル名、最小・最大マイナーバージョンを確認する。
- 同一モデルの `ERROR`（既定）、`RELOAD`、`SKIP` の挙動を統合テストする。
- 優先度の有効値と範囲外入力をテストする。
- 既存の音声合成、歌唱、ユーザー辞書の統合テストを0.17.0のVVM・ランタイムで実行する。

### 6. 利用例・仕様ドキュメントを更新

対象: `README.md`、`example/talk.php`、`example/kana.php`、
`example/song.php`、`docs/voicevox-core-php-ffi-ja.md`、
`docs/voicevox-core-php-ffi-en.md`

- 対応要件を `VOICEVOX CORE 0.17+` に更新する。
- `libVersionedFilename()` の例を推奨ファイル名APIへ更新する。
- ONNX Runtimeの例を `1.23.2`、最小 `1.17`、最大 `1.29` に更新する。
- `voicevox_synthesizer_load_voice_model()` のC/PHP例に options を反映する。
- FFI宣言サンプルの構造体、関数、結果コード、`priority` 型を更新する。
- v0.16.4のリリース情報・脚注・API説明を0.17.0に更新する。
- 0.17.0で追加されたVVM v2と、既存の歌唱APIへの影響がないことを明記する。

### 7. サブモジュールと対応範囲を固定

- 親リポジトリの `voicevox_core` gitlink が `0.17.0` のコミットを指すことを確認する。
- 0.17.0未満の共有ライブラリを読み込んだ場合は、新しいシンボル不足で失敗するため、
  対応対象を0.17.0以降としてドキュメントとテスト条件を統一する。
- Composer依存関係に変更はないため、`composer.json` / `composer.lock` は変更しない。

## 実装順序

1. FFIヘッダーとenumを更新する。
2. `Onnxruntime`、`Synthesizer`、`UserDict`、結果コードを更新する。
3. ユニットテストと0.17.0ランタイムの統合テストを更新する。
4. README、サンプル、日英の調査ドキュメントを更新する。
5. `composer run lint`、`composer run test` を実行し、ランタイムが用意できる環境で
   `composer run test:integration` を実行する。

## 完了条件

- PHP FFI宣言に0.17.0の必要なシンボル・構造体・型が揃っている。
- 0.17.0の共有ライブラリで、既存のTTS・歌唱・辞書処理が動作する。
- 推奨ONNX Runtimeのファイル名とバージョン情報が0.17.0と一致する。
- モデル再読み込み・スキップをPHP APIから選択できる。
- ドキュメントとサンプルに0.16系のAPI名・バージョン表記が残っていない。
