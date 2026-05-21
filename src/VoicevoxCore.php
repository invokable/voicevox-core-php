<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;
use Revolution\Voicevox\Core\Exceptions\VoicevoxException;

/**
 * VOICEVOXコアのグローバルユーティリティ関数。
 */
class VoicevoxCore
{
    /**
     * voicevoxのバージョンを取得する。
     *
     * @return string SemVerでフォーマットされたバージョン。
     */
    public function getVersion(): string
    {
        return VoicevoxFFI::getInstance()->voicevox_get_version();
    }

    /**
     * AccentPhrase配列のJSON文字列からAudioQueryをJSON文字列で生成する。
     *
     * @param  string  $accentPhrasesJson  AccentPhrase配列のJSON文字列。
     */
    public function audioQueryCreateFromAccentPhrases(string $accentPhrasesJson): string
    {
        $ffi = VoicevoxFFI::getInstance();
        $jsonPtr = $ffi->new('char*');
        $result = $ffi->voicevox_audio_query_create_from_accent_phrases(
            $accentPhrasesJson,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $ffi);

        $json = FFI::string($jsonPtr);
        $ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * AudioQuery JSONのバリデーションを行う。不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $audioQueryJson  `AudioQuery`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function audioQueryValidate(string $audioQueryJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_audio_query_validate($audioQueryJson);
        VoicevoxResultCode::check($result, $ffi);
    }

    /**
     * AccentPhrase JSONのバリデーションを行う。不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $accentPhraseJson  `AccentPhrase`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function accentPhraseValidate(string $accentPhraseJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_accent_phrase_validate($accentPhraseJson);
        VoicevoxResultCode::check($result, $ffi);
    }

    /**
     * Mora JSONのバリデーションを行う。不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $moraJson  `Mora`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function moraValidate(string $moraJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_mora_validate($moraJson);
        VoicevoxResultCode::check($result, $ffi);
    }

    /**
     * Score JSONのバリデーションを行う。不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $scoreJson  `Score`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function scoreValidate(string $scoreJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_score_validate($scoreJson);
        VoicevoxResultCode::check($result, $ffi);
    }

    /**
     * Note JSONのバリデーションを行う。不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $noteJson  `Note`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function noteValidate(string $noteJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_note_validate($noteJson);
        VoicevoxResultCode::check($result, $ffi);
    }

    /**
     * FrameAudioQuery JSONのバリデーションを行う。不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $frameAudioQueryJson  `FrameAudioQuery`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function frameAudioQueryValidate(string $frameAudioQueryJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_frame_audio_query_validate($frameAudioQueryJson);
        VoicevoxResultCode::check($result, $ffi);
    }

    /**
     * FramePhoneme JSONのバリデーションを行う。不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $framePhonemeJson  `FramePhoneme`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function framePhonemeValidate(string $framePhonemeJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_frame_phoneme_validate($framePhonemeJson);
        VoicevoxResultCode::check($result, $ffi);
    }

    /**
     * 楽譜と歌唱音声合成用のクエリの組み合わせが有効かどうかを確認する。
     * 不正な場合はVoicevoxExceptionをスローする。
     *
     * @param  string  $scoreJson  `Score`型のJSON。
     * @param  string  $frameAudioQueryJson  `FrameAudioQuery`型のJSON。
     *
     * @throws VoicevoxException
     */
    public function ensureCompatible(string $scoreJson, string $frameAudioQueryJson): void
    {
        $ffi = VoicevoxFFI::getInstance();
        $result = $ffi->voicevox_ensure_compatible($scoreJson, $frameAudioQueryJson);
        VoicevoxResultCode::check($result, $ffi);
    }
}
