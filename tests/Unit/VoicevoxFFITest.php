<?php

declare(strict_types=1);

use Revolution\Voicevox\Core\VoicevoxFFI;

describe('VoicevoxFFI', function () {
    it('returns correct library name for current OS', function () {
        $path = VoicevoxFFI::getLibraryPath();

        $expected = match (PHP_OS_FAMILY) {
            'Darwin' => 'libvoicevox_core.dylib',
            'Windows' => 'voicevox_core.dll',
            default => 'libvoicevox_core.so',
        };

        expect($path)->toBe($expected);
    });

    it('uses VOICEVOX_CORE_LIB_PATH env variable when set', function () {
        $original = getenv('VOICEVOX_CORE_LIB_PATH');

        putenv('VOICEVOX_CORE_LIB_PATH=/custom/path/libvoicevox_core.dylib');

        expect(VoicevoxFFI::getLibraryPath())->toBe('/custom/path/libvoicevox_core.dylib');

        if ($original === false) {
            putenv('VOICEVOX_CORE_LIB_PATH');
        } else {
            putenv("VOICEVOX_CORE_LIB_PATH={$original}");
        }
    });
});
