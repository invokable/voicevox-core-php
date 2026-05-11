<?php

declare(strict_types=1);

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Enums\UserDictWordType;
use Revolution\Voicevox\Core\Enums\VoicevoxResultCode;
use Revolution\Voicevox\Core\Exceptions\VoicevoxException;

describe('AccelerationMode', function () {
    it('has correct values', function () {
        expect(AccelerationMode::Auto->value)->toBe(0);
        expect(AccelerationMode::Cpu->value)->toBe(1);
        expect(AccelerationMode::Gpu->value)->toBe(2);
    });

    it('can be created from int', function () {
        expect(AccelerationMode::from(0))->toBe(AccelerationMode::Auto);
        expect(AccelerationMode::from(1))->toBe(AccelerationMode::Cpu);
        expect(AccelerationMode::from(2))->toBe(AccelerationMode::Gpu);
    });
});

describe('UserDictWordType', function () {
    it('has correct values', function () {
        expect(UserDictWordType::ProperNoun->value)->toBe(0);
        expect(UserDictWordType::CommonNoun->value)->toBe(1);
        expect(UserDictWordType::Verb->value)->toBe(2);
        expect(UserDictWordType::Adjective->value)->toBe(3);
        expect(UserDictWordType::Suffix->value)->toBe(4);
    });
});

describe('VoicevoxResultCode', function () {
    it('has correct OK value', function () {
        expect(VoicevoxResultCode::Ok->value)->toBe(0);
    });

    it('has correct error values', function () {
        expect(VoicevoxResultCode::NotLoadedOpenjtalkDictError->value)->toBe(1);
        expect(VoicevoxResultCode::GetSupportedDevicesError->value)->toBe(3);
        expect(VoicevoxResultCode::GpuSupportError->value)->toBe(4);
        expect(VoicevoxResultCode::InitInferenceRuntimeError->value)->toBe(29);
        expect(VoicevoxResultCode::StyleNotFoundError->value)->toBe(6);
        expect(VoicevoxResultCode::ModelNotFoundError->value)->toBe(7);
        expect(VoicevoxResultCode::RunModelError->value)->toBe(8);
        expect(VoicevoxResultCode::AnalyzeTextError->value)->toBe(11);
        expect(VoicevoxResultCode::InvalidUtf8InputError->value)->toBe(12);
        expect(VoicevoxResultCode::ParseKanaError->value)->toBe(13);
        expect(VoicevoxResultCode::InvalidAudioQueryError->value)->toBe(14);
        expect(VoicevoxResultCode::InvalidAccentPhraseError->value)->toBe(15);
    });
});

describe('VoicevoxException', function () {
    it('is a RuntimeException', function () {
        $e = new VoicevoxException('test', 1);
        expect($e)->toBeInstanceOf(RuntimeException::class);
        expect($e->getMessage())->toBe('test');
        expect($e->getCode())->toBe(1);
    });
});
