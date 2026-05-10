<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core;

use FFI;
use FFI\CData;

class VoicevoxFFI
{
    private static ?FFI $ffi = null;

    /**
     * FFIシングルトンインスタンスを取得する。
     */
    public static function getInstance(): FFI
    {
        return self::$ffi ??= FFI::cdef(
            file_get_contents(__DIR__.'/../headers/voicevox_core_ffi.h'),
            self::getLibraryPath(),
        );
    }

    /**
     * PHP 8.5+ は const char* の戻り値を自動的に PHP string に変換する。
     * それ以前のバージョンでは FFI\CData が返る。両方に対応するヘルパー。
     */
    public static function cstring(CData|string $ptr): string
    {
        return is_string($ptr) ? $ptr : FFI::string($ptr);
    }

    /**
     * VOICEVOXコアライブラリのパスを返す。
     *
     * 環境変数 VOICEVOX_CORE_LIB_PATH が設定されている場合はその値を使用する。
     */
    public static function getLibraryPath(): string
    {
        if ($path = getenv('VOICEVOX_CORE_LIB_PATH')) {
            return $path;
        }

        return match (PHP_OS_FAMILY) {
            'Darwin' => 'libvoicevox_core.dylib',
            'Windows' => 'voicevox_core.dll',
            default => 'libvoicevox_core.so',
        };
    }
}
