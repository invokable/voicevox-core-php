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
    /** ONNX Runtimeのライブラリ名。 */
    public const string LIB_NAME = 'voicevox_onnxruntime';

    /** 推奨されるONNX Runtimeのバージョン。 */
    public const string LIB_VERSION = '1.17.3';

    private static ?self $instance = null;

    private function __construct(
        private readonly CData $handle,
        private readonly FFI $ffi,
    ) {}

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
     * ONNX Runtimeの動的ライブラリの、バージョン付きのファイル名。
     */
    public static function libVersionedFilename(): string
    {
        return VoicevoxFFI::cstring(VoicevoxFFI::getInstance()->voicevox_get_onnxruntime_lib_versioned_filename());
    }

    /**
     * ONNX Runtimeの動的ライブラリの、バージョン無しのファイル名。
     */
    public static function libUnversionedFilename(): string
    {
        return VoicevoxFFI::cstring(VoicevoxFFI::getInstance()->voicevox_get_onnxruntime_lib_unversioned_filename());
    }

    public function handle(): CData
    {
        return $this->handle;
    }
}
