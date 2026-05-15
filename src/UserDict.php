<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;
use Revolution\Voicevox\Core\Enums\UserDictWordType;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;

/**
 * ユーザー辞書。
 */
class UserDict
{
    private readonly CData $handle;

    private readonly FFI $ffi;

    public function __construct()
    {
        $this->ffi = VoicevoxFFI::getInstance();
        $this->handle = $this->ffi->voicevox_user_dict_new();
    }

    /**
     * ファイルに保存されたユーザー辞書を読み込む。
     *
     * @param  string  $path  ユーザー辞書のパス。
     */
    public function load(string $path): void
    {
        $result = $this->ffi->voicevox_user_dict_load($this->handle, $path);

        VoicevoxResultCode::check($result, $this->ffi);
    }

    /**
     * ユーザー辞書をファイルに保存する。
     *
     * @param  string  $path  ユーザー辞書のパス。
     */
    public function save(string $path): void
    {
        $result = $this->ffi->voicevox_user_dict_save($this->handle, $path);

        VoicevoxResultCode::check($result, $this->ffi);
    }

    /**
     * 単語を追加する。
     *
     * @param  string  $surface  表記。
     * @param  string  $pronunciation  読み（カタカナ）。
     * @param  int  $accentType  アクセント型（音高が下がる直前のモーラのインデックス）。
     * @param  UserDictWordType  $wordType  単語の種類。
     * @param  int  $priority  優先度（0〜10）。
     * @return string 単語のUUID（hex文字列）。
     */
    public function addWord(
        string $surface,
        string $pronunciation,
        int $accentType,
        UserDictWordType $wordType = UserDictWordType::CommonNoun,
        int $priority = 5,
    ): string {
        $word = $this->makeWord($surface, $pronunciation, $accentType, $wordType, $priority);

        $uuidBuf = $this->ffi->new('uint8_t[16]');
        $result = $this->ffi->voicevox_user_dict_add_word(
            $this->handle,
            FFI::addr($word),
            $uuidBuf,
        );

        VoicevoxResultCode::check($result, $this->ffi);

        return bin2hex(FFI::string($uuidBuf, 16));
    }

    /**
     * 単語を更新する。
     *
     * @param  string  $wordUuid  更新する単語のUUID（hex文字列）。
     * @param  string  $surface  表記。
     * @param  string  $pronunciation  読み（カタカナ）。
     * @param  int  $accentType  アクセント型。
     * @param  UserDictWordType  $wordType  単語の種類。
     * @param  int  $priority  優先度（0〜10）。
     */
    public function updateWord(
        string $wordUuid,
        string $surface,
        string $pronunciation,
        int $accentType,
        UserDictWordType $wordType = UserDictWordType::CommonNoun,
        int $priority = 5,
    ): void {
        $word = $this->makeWord($surface, $pronunciation, $accentType, $wordType, $priority);
        $uuidBuf = $this->hexToUuid($wordUuid);

        $result = $this->ffi->voicevox_user_dict_update_word(
            $this->handle,
            $uuidBuf,
            FFI::addr($word),
        );

        VoicevoxResultCode::check($result, $this->ffi);
    }

    /**
     * 単語を削除する。
     *
     * @param  string  $wordUuid  削除する単語のUUID（hex文字列）。
     */
    public function removeWord(string $wordUuid): void
    {
        $uuidBuf = $this->hexToUuid($wordUuid);

        $result = $this->ffi->voicevox_user_dict_remove_word($this->handle, $uuidBuf);

        VoicevoxResultCode::check($result, $this->ffi);
    }

    /**
     * 他のユーザー辞書をインポートする。
     *
     * @param  UserDict  $other  インポートするユーザー辞書。
     */
    public function importDict(UserDict $other): void
    {
        $result = $this->ffi->voicevox_user_dict_import($this->handle, $other->handle());

        VoicevoxResultCode::check($result, $this->ffi);
    }

    /**
     * ユーザー辞書の内容をJSON文字列で返す。
     */
    public function toJson(): string
    {
        $jsonPtr = $this->ffi->new('char*');
        $result = $this->ffi->voicevox_user_dict_to_json(
            $this->handle,
            FFI::addr($jsonPtr),
        );

        VoicevoxResultCode::check($result, $this->ffi);

        $json = FFI::string($jsonPtr);
        $this->ffi->voicevox_json_free($jsonPtr);

        return $json;
    }

    public function handle(): CData
    {
        return $this->handle;
    }

    public function __destruct()
    {
        $this->ffi->voicevox_user_dict_delete($this->handle);
    }

    private function makeWord(
        string $surface,
        string $pronunciation,
        int $accentType,
        UserDictWordType $wordType,
        int $priority,
    ): CData {
        $word = $this->ffi->voicevox_user_dict_word_make($surface, $pronunciation, $accentType);
        $word->word_type = $wordType->value;
        $word->priority = $priority;

        return $word;
    }

    private function hexToUuid(string $hexUuid): CData
    {
        $bytes = hex2bin($hexUuid);
        $buf = $this->ffi->new('uint8_t[16]');
        for ($i = 0; $i < 16; $i++) {
            $buf[$i] = ord($bytes[$i]);
        }

        return $buf;
    }
}
