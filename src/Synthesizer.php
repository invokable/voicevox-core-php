<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * 音声シンセサイザ。
 */
readonly class Synthesizer
{
    private CData $handle;

    private FFI $ffi;

    /**
     * @param  Onnxruntime  $onnxruntime  ONNX Runtime。
     * @param  OpenJtalk  $openJtalk  Open JTalk。
     * @param  AccelerationMode  $accelerationMode  ハードウェアアクセラレーションモード。
     * @param  int  $cpuNumThreads  CPU利用数。0を指定すると環境に合わせたCPUが利用される。
     */
    public function __construct(
        Onnxruntime $onnxruntime,
        OpenJtalk $openJtalk,
        AccelerationMode $accelerationMode = AccelerationMode::Auto,
        int $cpuNumThreads = 0,
    ) {
        $this->ffi = VoicevoxFFI::getInstance();

        $options = $this->ffi->voicevox_make_default_initialize_options();
        $options->acceleration_mode = $accelerationMode->value;
        $options->cpu_num_threads = $cpuNumThreads;

        $ptr = $this->ffi->new('struct VoicevoxSynthesizer*');
        $result = $this->ffi->voicevox_synthesizer_new(
            $onnxruntime->handle(),
            $openJtalk->handle(),
            $options,
            FFI::addr($ptr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $this->handle = $ptr;
    }

    /**
     * ハードウェアアクセラレーションがGPUモードかどうか。
     */
    public function isGpuMode(): bool
    {
        return (bool) $this->ffi->voicevox_synthesizer_is_gpu_mode($this->handle);
    }

    /**
     * メタ情報をJSON文字列で返す。
     */
    public function metas(): string
    {
        $jsonPtr = $this->ffi->voicevox_synthesizer_create_metas_json($this->handle);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * モデルを読み込む。
     */
    public function loadVoiceModel(VoiceModelFile $model): void
    {
        $result = $this->ffi->voicevox_synthesizer_load_voice_model(
            $this->handle,
            $model->handle(),
        );

        VoicevoxResultCode::check($result, $this->ffi);
    }

    /**
     * 音声モデルの読み込みを解除する。
     *
     * @param  string  $voiceModelId  音声モデルID（hex文字列）。
     */
    public function unloadVoiceModel(string $voiceModelId): void
    {
        $idBuf = $this->hexToModelId($voiceModelId);
        $result = $this->ffi->voicevox_synthesizer_unload_voice_model(
            $this->handle,
            $idBuf,
        );

        VoicevoxResultCode::check($result, $this->ffi);
    }

    /**
     * 指定したvoice_model_idのモデルが読み込まれているか判定する。
     *
     * @param  string  $voiceModelId  音声モデルID（hex文字列）。
     */
    public function isLoadedVoiceModel(string $voiceModelId): bool
    {
        $idBuf = $this->hexToModelId($voiceModelId);

        return (bool) $this->ffi->voicevox_synthesizer_is_loaded_voice_model(
            $this->handle,
            $idBuf,
        );
    }

    /**
     * 日本語のテキストからAudioQueryをJSON文字列で生成する。
     *
     * @param  string  $text  UTF-8の日本語テキスト。
     * @param  int  $styleId  スタイルID。
     */
    public function createAudioQuery(string $text, int $styleId): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_synthesizer_create_audio_query(
            $this->handle,
            $text,
            $styleId,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * AquesTalk風記法からAudioQueryをJSON文字列で生成する。
     *
     * @param  string  $kana  AquesTalk風記法。
     * @param  int  $styleId  スタイルID。
     */
    public function createAudioQueryFromKana(string $kana, int $styleId): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_synthesizer_create_audio_query_from_kana(
            $this->handle,
            $kana,
            $styleId,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * 日本語のテキストからAccentPhrase（アクセント句）の配列をJSON文字列で生成する。
     *
     * @param  string  $text  UTF-8の日本語テキスト。
     * @param  int  $styleId  スタイルID。
     */
    public function createAccentPhrases(string $text, int $styleId): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_synthesizer_create_accent_phrases(
            $this->handle,
            $text,
            $styleId,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * AquesTalk風記法からAccentPhrase（アクセント句）の配列をJSON文字列で生成する。
     *
     * @param  string  $kana  AquesTalk風記法。
     * @param  int  $styleId  スタイルID。
     */
    public function createAccentPhrasesFromKana(string $kana, int $styleId): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_synthesizer_create_accent_phrases_from_kana(
            $this->handle,
            $kana,
            $styleId,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * アクセント句の音高・音素長を変更した新しいアクセント句の配列をJSON文字列で返す。
     *
     * @param  string  $accentPhrasesJson  変更元のアクセント句（JSON文字列）。
     * @param  int  $styleId  スタイルID。
     */
    public function replaceMoraData(string $accentPhrasesJson, int $styleId): string
    {
        return $this->replaceAccentPhrases(
            'voicevox_synthesizer_replace_mora_data',
            $accentPhrasesJson,
            $styleId,
        );
    }

    /**
     * アクセント句の音素長を変更した新しいアクセント句の配列をJSON文字列で返す。
     *
     * @param  string  $accentPhrasesJson  変更元のアクセント句（JSON文字列）。
     * @param  int  $styleId  スタイルID。
     */
    public function replacePhonemeLength(string $accentPhrasesJson, int $styleId): string
    {
        return $this->replaceAccentPhrases(
            'voicevox_synthesizer_replace_phoneme_length',
            $accentPhrasesJson,
            $styleId,
        );
    }

    /**
     * アクセント句の音高を変更した新しいアクセント句の配列をJSON文字列で返す。
     *
     * @param  string  $accentPhrasesJson  変更元のアクセント句（JSON文字列）。
     * @param  int  $styleId  スタイルID。
     */
    public function replaceMoraPitch(string $accentPhrasesJson, int $styleId): string
    {
        return $this->replaceAccentPhrases(
            'voicevox_synthesizer_replace_mora_pitch',
            $accentPhrasesJson,
            $styleId,
        );
    }

    /**
     * AudioQueryから音声合成する。WAVデータをPHP文字列（バイナリ）で返す。
     *
     * @param  string  $audioQueryJson  AudioQuery（JSON文字列）。
     * @param  int  $styleId  スタイルID。
     * @param  bool  $enableInterrogativeUpspeak  疑問文の調整を有効にするかどうか。
     */
    public function synthesis(
        string $audioQueryJson,
        int $styleId,
        bool $enableInterrogativeUpspeak = true,
    ): string {
        $options = $this->ffi->voicevox_make_default_synthesis_options();
        $options->enable_interrogative_upspeak = $enableInterrogativeUpspeak;

        $wavSize = $this->ffi->new('uint64_t');
        $wavPtr = $this->ffi->new('uint8_t*');

        $result = $this->ffi->voicevox_synthesizer_synthesis(
            $this->handle,
            $audioQueryJson,
            $styleId,
            $options,
            FFI::addr($wavSize),
            FFI::addr($wavPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $wav = FFI::string($wavPtr, (int) $wavSize->cdata);
        $this->ffi->voicevox_wav_free($wavPtr);

        return $wav;
    }

    /**
     * 日本語のテキストから音声合成を行う。WAVデータをPHP文字列（バイナリ）で返す。
     *
     * @param  string  $text  UTF-8の日本語テキスト。
     * @param  int  $styleId  スタイルID。
     * @param  bool  $enableInterrogativeUpspeak  疑問文の調整を有効にするかどうか。
     */
    public function tts(
        string $text,
        int $styleId,
        bool $enableInterrogativeUpspeak = true,
    ): string {
        $options = $this->ffi->voicevox_make_default_tts_options();
        $options->enable_interrogative_upspeak = $enableInterrogativeUpspeak;

        $wavSize = $this->ffi->new('uint64_t');
        $wavPtr = $this->ffi->new('uint8_t*');

        $result = $this->ffi->voicevox_synthesizer_tts(
            $this->handle,
            $text,
            $styleId,
            $options,
            FFI::addr($wavSize),
            FFI::addr($wavPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $wav = FFI::string($wavPtr, (int) $wavSize->cdata);
        $this->ffi->voicevox_wav_free($wavPtr);

        return $wav;
    }

    /**
     * AquesTalk風記法から音声合成を行う。WAVデータをPHP文字列（バイナリ）で返す。
     *
     * @param  string  $kana  AquesTalk風記法。
     * @param  int  $styleId  スタイルID。
     * @param  bool  $enableInterrogativeUpspeak  疑問文の調整を有効にするかどうか。
     */
    public function ttsFromKana(
        string $kana,
        int $styleId,
        bool $enableInterrogativeUpspeak = true,
    ): string {
        $options = $this->ffi->voicevox_make_default_tts_options();
        $options->enable_interrogative_upspeak = $enableInterrogativeUpspeak;

        $wavSize = $this->ffi->new('uint64_t');
        $wavPtr = $this->ffi->new('uint8_t*');

        $result = $this->ffi->voicevox_synthesizer_tts_from_kana(
            $this->handle,
            $kana,
            $styleId,
            $options,
            FFI::addr($wavSize),
            FFI::addr($wavPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $wav = FFI::string($wavPtr, (int) $wavSize->cdata);
        $this->ffi->voicevox_wav_free($wavPtr);

        return $wav;
    }

    /**
     * 楽譜から歌唱音声合成用のクエリをJSON文字列で作成する。
     *
     * @param  string  $scoreJson  楽譜（JSON文字列）。
     * @param  int  $styleId  スタイルID。
     */
    public function createSingFrameAudioQuery(string $scoreJson, int $styleId): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_synthesizer_create_sing_frame_audio_query(
            $this->handle,
            $scoreJson,
            $styleId,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * 歌唱音声合成用のクエリから歌唱音声合成する。WAVデータをPHP文字列（バイナリ）で返す。
     *
     * @param  string  $frameAudioQueryJson  歌唱音声合成用のクエリ（JSON文字列）。
     * @param  int  $styleId  スタイルID。
     */
    public function frameSynthesis(string $frameAudioQueryJson, int $styleId): string
    {
        $wavSize = $this->ffi->new('uint64_t');
        $wavPtr = $this->ffi->new('uint8_t*');

        $result = $this->ffi->voicevox_synthesizer_frame_synthesis(
            $this->handle,
            $frameAudioQueryJson,
            $styleId,
            FFI::addr($wavSize),
            FFI::addr($wavPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $wav = FFI::string($wavPtr, (int) $wavSize->cdata);
        $this->ffi->voicevox_wav_free($wavPtr);

        return $wav;
    }

    public function __destruct()
    {
        $this->ffi->voicevox_synthesizer_delete($this->handle);
    }

    /**
     * アクセント句操作系の共通処理。
     */
    private function replaceAccentPhrases(
        string $funcName,
        string $accentPhrasesJson,
        int $styleId,
    ): string {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->$funcName(
            $this->handle,
            $accentPhrasesJson,
            $styleId,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * hex文字列の音声モデルIDを16バイトのCData配列に変換する。
     */
    private function hexToModelId(string $hexId): CData
    {
        $bytes = hex2bin($hexId);
        $buf = $this->ffi->new('uint8_t[16]');
        for ($i = 0; $i < 16; $i++) {
            $buf[$i] = ord($bytes[$i]);
        }

        return $buf;
    }
}
