<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use InvalidArgumentException;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * 音声合成用の中間表現。
 */
final class AudioFeature
{
    private function __construct(
        private readonly CData $handle,
        private readonly FFI $ffi,
        private readonly Synthesizer $synthesizer,
    ) {}

    /**
     * 既存のポインターからインスタンスを生成する（内部用）。
     *
     * @internal
     */
    public static function fromPtr(CData $handle, FFI $ffi, Synthesizer $synthesizer): self
    {
        return new self($handle, $ffi, $synthesizer);
    }

    /**
     * 音声合成に必要な総フレーム数を返す。
     */
    public function length(): int
    {
        return (int) $this->ffi->voicevox_audio_feature_len($this->handle);
    }

    /**
     * AudioFeatureが空かどうかを返す。
     */
    public function isEmpty(): bool
    {
        return (bool) $this->ffi->voicevox_audio_feature_is_empty($this->handle);
    }

    /**
     * 全範囲を表すsliceを返す。
     */
    public function whole(): AudioFeatureSlice
    {
        return AudioFeatureSlice::fromStruct(
            $this,
            $this->ffi->voicevox_audio_feature_slice_new($this->handle, false, false),
            $this->ffi,
        );
    }

    /**
     * 指定したフレーム範囲のsliceを返す。
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
    ): AudioFeatureSlice {
        if ($startInclusive < 0 || $end < 0) {
            throw new InvalidArgumentException('Slice frame positions must not be negative.');
        }

        $slice = $this->ffi->new('struct VoicevoxAudioFeatureSlice');
        $result = $this->ffi->voicevox_audio_feature_slice(
            $this->handle,
            $startInclusive,
            $end,
            $endIsInclusive,
            $clamp,
            $allowStartExceedsEnd,
            FFI::addr($slice),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        return AudioFeatureSlice::fromStruct($this, $slice, $this->ffi);
    }

    /**
     * AudioFeature全体をPCMとしてrenderする。
     */
    public function render(): string
    {
        return $this->whole()->render();
    }

    /**
     * AudioFeatureSliceをPCMとしてrenderする（内部用）。
     *
     * @internal
     */
    public function renderSlice(CData $slice): string
    {
        return $this->synthesizer->renderAudioFeatureSlice($slice);
    }

    public function __destruct()
    {
        $this->ffi->voicevox_audio_feature_delete($this->handle);
    }
}
