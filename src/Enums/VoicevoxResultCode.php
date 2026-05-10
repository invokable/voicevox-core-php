<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core\Enums;

use FFI;
use Revolution\Voicevox\Core\Exception\VoicevoxException;

enum VoicevoxResultCode: int
{
    /** 成功。 */
    case Ok = 0;

    /** open_jtalk辞書ファイルが読み込まれていない。 */
    case NotLoadedOpenjtalkDictError = 1;

    /** サポートされているデバイス情報取得に失敗した。 */
    case GetSupportedDevicesError = 3;

    /** GPUモードがサポートされていない。 */
    case GpuSupportError = 4;

    /** 推論ライブラリのロードまたは初期化ができなかった。 */
    case InitInferenceRuntimeError = 29;

    /** スタイルIDに対するスタイルが見つからなかった。 */
    case StyleNotFoundError = 6;

    /** 音声モデルIDに対する音声モデルが見つからなかった。 */
    case ModelNotFoundError = 7;

    /** 推論に失敗した、もしくは推論結果が異常。 */
    case RunModelError = 8;

    /** 入力テキストの解析に失敗した。 */
    case AnalyzeTextError = 11;

    /** 無効なutf8文字列が入力された。 */
    case InvalidUtf8InputError = 12;

    /** AquesTalk風記法のテキストの解析に失敗した。 */
    case ParseKanaError = 13;

    /** 無効なAudioQuery。 */
    case InvalidAudioQueryError = 14;

    /** 無効なAccentPhrase。 */
    case InvalidAccentPhraseError = 15;

    /** ZIPファイルを開くことに失敗した。 */
    case OpenZipFileError = 16;

    /** ZIP内のファイルが読めなかった。 */
    case ReadZipEntryError = 17;

    /** モデルの形式が不正。 */
    case InvalidModelHeaderError = 28;

    /** すでに読み込まれている音声モデルを読み込もうとした。 */
    case ModelAlreadyLoadedError = 18;

    /** すでに読み込まれているスタイルを読み込もうとした。 */
    case StyleAlreadyLoadedError = 26;

    /** 無効なモデルデータ。 */
    case InvalidModelDataError = 27;

    /** ユーザー辞書を読み込めなかった。 */
    case LoadUserDictError = 20;

    /** ユーザー辞書を書き込めなかった。 */
    case SaveUserDictError = 21;

    /** ユーザー辞書に単語が見つからなかった。 */
    case UserDictWordNotFoundError = 22;

    /** OpenJTalkのユーザー辞書の設定に失敗した。 */
    case UseUserDictError = 23;

    /** ユーザー辞書の単語のバリデーションに失敗した。 */
    case InvalidUserDictWordError = 24;

    /** UUIDの変換に失敗した。 */
    case InvalidUuidError = 25;

    /** 無効なMora。 */
    case InvalidMoraError = 30;

    /** 無効な楽譜。 */
    case InvalidScoreError = 31;

    /** 無効なノート。 */
    case InvalidNoteError = 32;

    /** 無効なFrameAudioQuery。 */
    case InvalidFrameAudioQueryError = 33;

    /** 無効なFramePhoneme。 */
    case InvalidFramePhonemeError = 34;

    /** 楽譜とFrameAudioQueryの組み合わせが不正。 */
    case IncompatibleQueriesError = 35;

    /**
     * 結果コードを確認し、OK以外の場合はVoicevoxExceptionをスローする。
     *
     * @throws VoicevoxException
     */
    public static function check(int $code, FFI $ffi): void
    {
        if ($code !== self::Ok->value) {
            $message = FFI::string($ffi->voicevox_error_result_to_message($code));
            throw new VoicevoxException($message, $code);
        }
    }
}
