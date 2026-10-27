<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use InvalidArgumentException;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * AudioFeature上のフレーム範囲。
 */
final class AudioFeatureSlice
{
    private function __construct(
        private readonly AudioFeature $audioFeature,
        private readonly CData $handle,
        private readonly FFI $ffi,
    ) {}

    /**
     * 既存のslice構造体からインスタンスを生成する（内部用）。
     *
     * @internal
     */
    public static function fromStruct(AudioFeature $audioFeature, CData $handle, FFI $ffi): self
    {
        return new self($audioFeature, $handle, $ffi);
    }

    /**
     * sliceが表すフレーム数を返す。
     */
    public function length(): int
    {
        $length = $this->ffi->new('uintptr_t');
        $result = $this->ffi->voicevox_audio_feature_slice_len(
            $this->handle,
            FFI::addr($length),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        return (int) $length->cdata;
    }

    /**
     * sliceが空かどうかを返す。
     */
    public function isEmpty(): bool
    {
        $isEmpty = $this->ffi->new('bool');
        $result = $this->ffi->voicevox_audio_feature_slice_is_empty(
            $this->handle,
            FFI::addr($isEmpty),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        return (bool) $isEmpty->cdata;
    }

    /**
     * このslice内のフレーム範囲を表す部分sliceを返す。
     *
     * @param  int  $startInclusive  開始フレーム。
     * @param  int  $end  終了フレーム。
     * @param  bool  $endIsInclusive  終了フレームを範囲に含めるかどうか。
     * @param  bool  $clamp  範囲外の端点を有効範囲に丸めるかどうか。
     * @param  bool  $allowStartExceedsEnd  開始位置が終了位置を超える場合に空範囲を許可するかどうか。
     */
    public function slice(
        int $startInclusive,
        int $end,
        bool $endIsInclusive = false,
        bool $clamp = false,
        bool $allowStartExceedsEnd = false,
    ): self {
        if ($startInclusive < 0 || $end < 0) {
            throw new InvalidArgumentException('Slice frame positions must not be negative.');
        }

        $slice = $this->ffi->new('struct VoicevoxAudioFeatureSlice');
        $result = $this->ffi->voicevox_audio_feature_slice_slice(
            $this->handle,
            $startInclusive,
            $end,
            $endIsInclusive,
            $clamp,
            $allowStartExceedsEnd,
            FFI::addr($slice),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        return self::fromStruct($this->audioFeature, $slice, $this->ffi);
    }

    /**
     * sliceを16-bit PCMとしてrenderする。
     */
    public function render(): string
    {
        return $this->audioFeature->renderSlice($this->handle);
    }
}
