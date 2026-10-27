# VOICEVOX CORE 0.17.1 対応計画

## 目的

VOICEVOX CORE 0.17.1 の C API にPHP FFIラッパーを対応させる。0.17.0で導入されたAPI変更を前提とし、0.17.1で追加されたフレーム長計算・AudioFeature生成・範囲renderをPHPから利用できるようにする。

対象は `voicevox_core` のタグ `0.17.1`。本計画書は既存の [`voicevox-core-0.17.0-migration-plan.md`](voicevox-core-0.17.0-migration-plan.md) を引き継ぐものであり、同計画書は変更しない。

## 0.17.1で確認した変更点

| 項目 | 0.17.0 | 0.17.1 | PHP側への影響 |
| --- | --- | --- | --- |
| ストリーミング音声合成 | C APIに分割render用のAPIなし | AudioFeatureを生成し、範囲ごとにPCMをrenderするAPIを追加 | AudioFeatureの所有・解放、範囲指定、PCM出力のラッパーを追加 |
| 高レベルstreaming API | C APIに未実装 | 引き続きC APIに未実装 | Pythonの`streaming_synthesis`と同名のPHP APIは追加せず、C APIプリミティブを公開 |
| フレームレート | 定数・取得APIなし | `VOICEVOX_FRAME_RATE=93.75`、`voicevox_get_frame_rate()`を追加 | フレームレート取得APIを追加 |
| AudioQueryのフレーム長 | 直接取得するC APIなし | `voicevox_audio_query_frame_length()`とオプション構造体を追加 | AudioQueryの所要フレーム数を取得するAPIを追加 |
| メモリ解放関数 | `voicevox_json_free` / `voicevox_wav_free` | `voicevox_string_free` / `voicevox_bytes_free`に改名。旧名はdeprecatedとして残る | PHPの解放呼び出しを新名称に更新 |
| 空のPCM出力 | 該当APIなし | `voicevox_synthesizer_render()`は0バイト時に`voicevox_empty_bytes`を返す | 長さ0のバッファを解放しない |
| AudioFeature範囲エラー | 該当APIなし | `START_OUT_OF_BOUNDS=36`、`END_OUT_OF_BOUNDS=37`、`START_EXCEEDS_END=38`を追加 | 結果コードenumと範囲エラーのテストを追加 |

ストリーミングトークで利用するVVMは新形式（`vvm_format_version=2`）が必要。0.17.1のC APIは、AudioFeatureの生成・フレーム範囲のスライス・renderを提供するが、高レベルの`streaming_synthesis`は提供しない。`voicevox_synthesizer_render()`が返すデータは16-bit PCMであり、WAVではない。

## 修正計画

### 1. FFI宣言を0.17.1へ同期

対象: `headers/voicevox_core_ffi.h`

- 0.17.1の`voicevox_core.h`からPHP FFIに必要な宣言を反映する。
- `VOICEVOX_FRAME_RATE`相当の取得関数`voicevox_get_frame_rate()`を追加する。
- `VoicevoxAudioQueryFrameLengthOptions`、デフォルトオプション生成関数、`voicevox_audio_query_frame_length()`を追加する。
- opaque型`VoicevoxAudioFeature`と、範囲を表す`VoicevoxAudioFeatureSlice`構造体を追加する。
- AudioFeature生成、長さ・空判定、全体slice生成、範囲slice・部分slice、slice長さ・空判定、render、AudioFeature解放関数を追加する。
- 新しい結果コード36〜38を宣言・記録する。
- `voicevox_string_free()`と`voicevox_bytes_free()`を宣言し、利用側のPHPコードを新しい解放関数へ移行する。C APIでは旧関数もdeprecated扱いで残るが、新規コードでは使用しない。
- `voicevox_empty_bytes`は読み書き・解放を行わない静的な空バッファであることを反映する。

### 2. PHPのAudioFeature APIを追加

対象候補: `src/Synthesizer.php`、新規`src/AudioFeature.php`、必要に応じて新規`src/AudioFeatureSlice.php`、`src/VoicevoxCore.php`

- `Synthesizer`からAudioQuery JSONとスタイルIDを使ってAudioFeatureを作成できるようにする。既存の`SynthesisOptions`に相当する疑問文語尾上げ設定を維持する。
- AudioFeatureの所有権をPHPオブジェクトに持たせ、破棄時に`voicevox_audio_feature_delete()`を呼ぶ。PHPで保持するsliceは元のAudioFeatureを参照し続け、feature解放後に無効なsliceを利用できない設計にする。
- AudioFeatureのフレーム長と空判定、開始・終了フレームを指定した範囲取得を提供する。開始・終了の包含規則、範囲外処理はC APIの仕様に合わせる。
- `Synthesizer`からAudioFeatureSliceをrenderし、PCMバイナリ文字列を返すAPIを追加する。PCMが空のときは長さ0を返し、`voicevox_empty_bytes`を解放しない。
- `VoicevoxCore`には`voicevox_audio_query_frame_length()`と`voicevox_get_frame_rate()`のラッパーを追加する。AudioQueryのフレーム長とAudioFeatureのフレーム長は、疑問文語尾上げ設定を揃えて比較できるようにする。
- C APIにない高レベル`streamingSynthesis()`や、WAVチャンクを直接返すAPIは追加しない。チャンクの連結・再生用WAV化は呼び出し側の責務とし、サンプルとドキュメントでPCMであることを明記する。

### 3. メモリ解放と結果コードを更新

対象候補: `src/OpenJtalk.php`、`src/Onnxruntime.php`、`src/Synthesizer.php`、`src/UserDict.php`、`src/VoiceModelFile.php`、`src/VoicevoxCore.php`、`src/Enums/VoicevoxResultCode.php`

- JSON文字列の解放を`voicevox_string_free()`に、音声バイト列の解放を`voicevox_bytes_free()`に統一する。
- 既存のWAV生成APIの出力も`voicevox_bytes_free()`で解放する。
- 0バイト出力ではC API所有の空バッファを解放しないようにする。
- `VoicevoxResultCode`に範囲エラー3件を値36〜38で追加する。既存の`check()`による例外処理を利用する。

### 4. テストを0.17.1仕様へ更新

対象候補: `tests/Unit/EnumsTest.php`、`tests/Unit/VoicevoxFFITest.php`、`tests/Integration/VoicevoxRuntimeTest.php`、必要に応じて新規ユニットテスト

- 結果コード36〜38、フレームレート93.75、フレーム長APIの宣言を確認する。
- 0.17.1のランタイムでAudioFeatureを生成し、同じ疑問文語尾上げ設定を使ったAudioQueryのフレーム長と一致することを確認する。
- 有効範囲のsliceをrenderしてPCMが取得できること、境界外・開始が終了を超える範囲で該当する結果コードが返ることを確認する。
- 空の範囲のrenderが空バイト列となり、空バッファを解放しないことを確認する。
- AudioFeature破棄後の利用を行わず、sliceのライフサイクルが所有元featureに従うことをテストで確認する。
- 既存のTTS・歌唱・辞書処理が継続動作することを、0.17.1のランタイムで統合テストする。

### 5. 利用例・仕様ドキュメントを更新

対象候補: `README.md`、新規`example/streaming-talk.php`、`docs/voicevox-core-php-ffi-ja.md`、`docs/voicevox-core-php-ffi-en.md`

- 対応要件をVOICEVOX CORE 0.17.1以降に更新し、0.17.0の共有ライブラリでは新規シンボルを利用できないことを明記する。
- フレームレート93.75からチャンク長を決め、AudioFeatureを範囲ごとにrenderするPHP CLIサンプルを追加する。
- 出力が16-bit PCMであってWAVではないこと、高レベルのC API streaming関数がないこと、VVM v2が必要なことを記載する。
- 調査ドキュメント内の旧メモリ解放関数名を新名称に更新し、0.17.1のC APIヘッダーを参照する。

### 6. サブモジュールと対応範囲を固定

- 親リポジトリの`voicevox_core` gitlinkがタグ`0.17.1`のコミットを指すよう更新する。
- 新しいC APIシンボルを利用するため、対応する共有ライブラリの最低バージョンを0.17.1に揃える。
- 0.17.1でC APIに影響しない変更（macOSバイナリのdeployment target修正、Python APIの例外公開、Rust依存関係など）はPHPラッパーの対応範囲外とする。
- Composer依存関係に変更はないため、`composer.json` / `composer.lock`は変更しない。

## 実装順序

1. 0.17.1のFFI宣言と結果コードを追加する。
2. 文字列・バイト列の解放呼び出しを移行し、AudioFeatureとフレーム長のPHP APIを実装する。
3. ユニットテストと0.17.1ランタイム向け統合テストを追加・更新する。
4. README、分割合成サンプル、日英の調査ドキュメントを更新する。
5. `voicevox_core`サブモジュールを0.17.1に更新し、`composer run lint`、`composer run test`を実行する。ランタイムが用意できる環境では`composer run test:integration`も実行する。

## 完了条件

- PHP FFI宣言が0.17.1のAudioFeature、範囲render、フレーム長、解放APIを正しく含む。
- PHPからAudioFeatureを作成し、有効な範囲をPCMとしてrenderできる。
- AudioFeatureとsliceの所有期間、範囲エラー、空バイト列の扱いが安全である。
- PHPコードがdeprecatedな`voicevox_json_free()` / `voicevox_wav_free()`を呼ばない。
- ドキュメントとサンプルがPCM出力、高レベルCストリーミングAPIの未提供、VVM v2要件を正しく説明する。
- 対応するサブモジュールと共有ライブラリ要件が0.17.1以降で統一されている。
