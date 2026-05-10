<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * テキスト解析器としてのOpen JTalk。
 */
readonly class OpenJtalk
{
    private CData $handle;

    private FFI $ffi;

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
