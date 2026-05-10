<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * 音声モデルファイル。VVMファイルと対応する。
 */
readonly class VoiceModelFile
{
    private function __construct(
        private CData $handle,
        private FFI $ffi,
    ) {}

    /**
     * VVMファイルを開く。
     *
     * @param  string  $path  VVMファイルへのパス。
     */
    public static function open(string $path): self
    {
        $ffi = VoicevoxFFI::getInstance();

        $ptr = $ffi->new('struct VoicevoxVoiceModelFile*');
        $result = $ffi->voicevox_voice_model_file_open($path, FFI::addr($ptr));

        VoicevoxResultCode::check($result, $ffi);

        return new self($ptr, $ffi);
    }

    /**
     * 音声モデルID（16バイト）をhex文字列で返す。
     */
    public function id(): string
    {
        $idBuf = $this->ffi->new('uint8_t[16]');
        $this->ffi->voicevox_voice_model_file_id($this->handle, $idBuf);

        return bin2hex(FFI::string($idBuf, 16));
    }

    /**
     * メタ情報をJSON文字列で返す。
     */
    public function createMetasJson(): string
    {
        $jsonPtr = $this->ffi->voicevox_voice_model_file_create_metas_json($this->handle);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * VVMファイルを閉じる。
     */
    public function close(): void
    {
        $this->ffi->voicevox_voice_model_file_delete($this->handle);
    }

    public function handle(): CData
    {
        return $this->handle;
    }

    public function __destruct()
    {
        $this->close();
    }
}
