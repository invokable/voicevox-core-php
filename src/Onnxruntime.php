<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * ONNX Runtime。
 *
 * シングルトンであり、インスタンスは高々一つ。
 */
class Onnxruntime
{
    /** 必要なONNX Runtime 1.xの最小マイナーバージョン。 */
    public const int LIB_MIN_REQUIRED_MINOR_VERSION = 17;

    /** サポートされるONNX Runtime 1.xの最大マイナーバージョン。 */
    public const int LIB_MAX_SUPPORTED_MINOR_VERSION = 29;

    /** 推奨されるONNX Runtimeのライブラリ名。 */
    public const string LIB_RECOMMENDED_NAME = 'voicevox_onnxruntime';

    /** 推奨されるONNX Runtimeのバージョン。 */
    public const string LIB_RECOMMENDED_VERSION = '1.23.2';

    /** @deprecated Use LIB_RECOMMENDED_NAME instead. */
    public const string LIB_NAME = self::LIB_RECOMMENDED_NAME;

    /** @deprecated Use LIB_RECOMMENDED_VERSION instead. */
    public const string LIB_VERSION = self::LIB_RECOMMENDED_VERSION;

    private static ?self $instance = null;

    private function __construct(
        private readonly CData $handle,
        private readonly FFI $ffi,
    ) {}

    /**
     * 既存のポインタからインスタンスを生成する（内部用）。
     *
     * @internal
     */
    public static function fromPtr(CData $ptr, FFI $ffi): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new self($ptr, $ffi);

        return self::$instance;
    }

    /**
     * ONNX Runtimeをロードして初期化する。
     *
     * 一度成功したら、以後は引数を無視して同じインスタンスを返す。
     *
     * @param  string  $filename  ONNX Runtimeのファイル名もしくはファイルパス。空文字の場合はバージョン付きファイル名を使用。
     */
    public static function loadOnce(string $filename = ''): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $ffi = VoicevoxFFI::getInstance();

        $opts = $ffi->voicevox_make_default_load_onnxruntime_options();

        $filenameBuf = null;
        if ($filename !== '') {
            $len = strlen($filename);
            $filenameBuf = $ffi->new('char['.($len + 1).']', false);
            FFI::memcpy($filenameBuf, $filename, $len);
            $opts->filename = $ffi->cast('char*', $filenameBuf);
        }

        $ortPtr = $ffi->new('struct VoicevoxOnnxruntime*');
        $result = $ffi->voicevox_onnxruntime_load_once($opts, FFI::addr($ortPtr));

        if ($filenameBuf !== null) {
            FFI::free($filenameBuf);
        }

        VoicevoxResultCode::check($result, $ffi);

        self::$instance = new self($ortPtr, $ffi);

        return self::$instance;
    }

    /**
     * インスタンスが既に作られているならそれを得る。作られていなければnullを返す。
     */
    public static function get(): ?self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $ffi = VoicevoxFFI::getInstance();
        $ptr = $ffi->voicevox_onnxruntime_get();

        if ($ptr === null) {
            return null;
        }

        self::$instance = new self($ptr, $ffi);

        return self::$instance;
    }

    /**
     * このライブラリで利用可能なデバイスの情報をJSON文字列で取得する。
     */
    public function supportedDevices(): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_onnxruntime_create_supported_devices_json(
            $this->handle,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * 推奨されるONNX Runtimeの動的ライブラリの、バージョン付きのファイル名。
     */
    public static function libRecommendedVersionedFilename(): string
    {
        return VoicevoxFFI::getInstance()->voicevox_get_onnxruntime_lib_recommended_versioned_filename();
    }

    /**
     * 推奨されるONNX Runtimeの動的ライブラリの、バージョン無しのファイル名。
     */
    public static function libRecommendedUnversionedFilename(): string
    {
        return VoicevoxFFI::getInstance()->voicevox_get_onnxruntime_lib_recommended_unversioned_filename();
    }

    /**
     * ONNX Runtimeの必要な最小マイナーバージョン。
     */
    public static function libMinRequiredMinorVersion(): int
    {
        return VoicevoxFFI::getInstance()->voicevox_get_onnxruntime_lib_min_required_minor_version();
    }

    /**
     * ONNX Runtimeのサポートされる最大マイナーバージョン。
     */
    public static function libMaxSupportedMinorVersion(): int
    {
        return VoicevoxFFI::getInstance()->voicevox_get_onnxruntime_lib_max_supported_minor_version();
    }

    /** @deprecated Use libRecommendedVersionedFilename instead. */
    public static function libVersionedFilename(): string
    {
        return self::libRecommendedVersionedFilename();
    }

    /** @deprecated Use libRecommendedUnversionedFilename instead. */
    public static function libUnversionedFilename(): string
    {
        return self::libRecommendedUnversionedFilename();
    }

    public function handle(): CData
    {
        return $this->handle;
    }
}
