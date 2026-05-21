<?php

declare(strict_types=1);

use Revolution\Voicevox\Core\VoicevoxCore;

describe('VoicevoxCore', function () {
    it('has getVersion method', function () {
        expect(method_exists(VoicevoxCore::class, 'getVersion'))->toBeTrue();
    });

    it('has audioQueryCreateFromAccentPhrases method', function () {
        expect(method_exists(VoicevoxCore::class, 'audioQueryCreateFromAccentPhrases'))->toBeTrue();
    });

    it('has all validate methods', function () {
        expect(method_exists(VoicevoxCore::class, 'audioQueryValidate'))->toBeTrue();
        expect(method_exists(VoicevoxCore::class, 'accentPhraseValidate'))->toBeTrue();
        expect(method_exists(VoicevoxCore::class, 'moraValidate'))->toBeTrue();
        expect(method_exists(VoicevoxCore::class, 'scoreValidate'))->toBeTrue();
        expect(method_exists(VoicevoxCore::class, 'noteValidate'))->toBeTrue();
        expect(method_exists(VoicevoxCore::class, 'frameAudioQueryValidate'))->toBeTrue();
        expect(method_exists(VoicevoxCore::class, 'framePhonemeValidate'))->toBeTrue();
    });

    it('has ensureCompatible method', function () {
        expect(method_exists(VoicevoxCore::class, 'ensureCompatible'))->toBeTrue();
    });
});
