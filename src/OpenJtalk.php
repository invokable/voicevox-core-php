<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * テキスト解析器としてのOpen JTalk。
 */
class OpenJtalk
{
    private readonly CData $handle;

    private readonly FFI $ffi;

    /**
     * @param  string  $openJtalkDictDir  Open JTalkの辞書ディレクトリ。
     */
    public function __construct(string $openJtalkDictDir)
    {
        $this->ffi = VoicevoxFFI::getInstance();

        $ptr = $this->ffi->new('struct OpenJtalkRc*');
        $result = $this->ffi->voicevox_open_jtalk_rc_new(
            $openJtalkDictDir,
            FFI::addr($ptr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $this->handle = $ptr;
    }

    /**
     * 日本語のテキストを解析してAccentPhrase配列をJSON文字列で返す。
     *
     * @param  string  $text  UTF-8の日本語テキスト。
     */
    public function analyze(string $text): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_open_jtalk_rc_analyze(
            $this->handle,
            $text,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    /**
     * ユーザー辞書を設定する。
     *
     * この関数を呼び出した後にユーザー辞書を変更した場合は、再度この関数を呼ぶ必要がある。
     */
    public function useUserDict(UserDict $userDict): void
    {
        $result = $this->ffi->voicevox_open_jtalk_rc_use_user_dict(
            $this->handle,
            $userDict->handle(),
        );

        VoicevoxResultCode::check($result, $this->ffi);
    }

    public function handle(): CData
    {
        return $this->handle;
    }

    public function __destruct()
    {
        $this->ffi->voicevox_open_jtalk_rc_delete($this->handle);
    }
}
