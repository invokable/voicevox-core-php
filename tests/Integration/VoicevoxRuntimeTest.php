<?php

declare(strict_types=1);

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;

function voicevoxRuntimeTestRoot(): ?string
{
    $root = getenv('VOICEVOX_CORE_TEST_ROOT');

    if (! is_string($root) || $root === '') {
        return null;
    }

    return rtrim($root, DIRECTORY_SEPARATOR);
}

function voicevoxRuntimeTestLibraryPath(string $root): string
{
    return $root.'/c_api/lib/'.match (PHP_OS_FAMILY) {
        'Darwin' => 'libvoicevox_core.dylib',
        'Windows' => 'voicevox_core.dll',
        default => 'libvoicevox_core.so',
    };
}

function voicevoxRuntimeTestOnnxruntimePath(string $root): string
{
    $pattern = match (PHP_OS_FAMILY) {
        'Darwin' => $root.'/onnxruntime/lib/*voicevox_onnxruntime*.dylib',
        'Windows' => $root.'/onnxruntime/lib/*voicevox_onnxruntime*.dll',
        default => $root.'/onnxruntime/lib/*voicevox_onnxruntime*.so*',
    };

    $matches = glob($pattern) ?: [];
    sort($matches);

    if ($matches === []) {
        throw new RuntimeException('Could not find the ONNX Runtime library in '.$root.'/onnxruntime/lib.');
    }

    return $matches[0];
}

function voicevoxRuntimeTestOpenJtalkDir(string $root): string
{
    $matches = glob($root.'/dict/open_jtalk_dic_*', GLOB_ONLYDIR) ?: [];
    sort($matches);

    if ($matches === []) {
        throw new RuntimeException('Could not find the OpenJTalk dictionary directory in '.$root.'/dict.');
    }

    return $matches[0];
}

describe('VOICEVOX runtime integration', function () {
    it('loads the runtime assets and synthesizes audio', function () {
        $root = voicevoxRuntimeTestRoot();

        if ($root === null) {
            $this->markTestSkipped('Set VOICEVOX_CORE_TEST_ROOT to run the integration tests.');
        }

        $libraryPath = voicevoxRuntimeTestLibraryPath($root);
        $voiceModelPath = $root.'/models/vvms/0.vvm';

        if (! file_exists($libraryPath)) {
            throw new RuntimeException('Could not find the VOICEVOX Core library at '.$libraryPath);
        }

        if (! file_exists($voiceModelPath)) {
            throw new RuntimeException('Could not find the voice model at '.$voiceModelPath);
        }

        putenv("VOICEVOX_CORE_LIB_PATH={$libraryPath}");

        $onnxruntime = Onnxruntime::loadOnce(voicevoxRuntimeTestOnnxruntimePath($root));
        $openJtalk = new OpenJtalk(voicevoxRuntimeTestOpenJtalkDir($root));
        $synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Cpu);
        $model = VoiceModelFile::open($voiceModelPath);

        $synthesizer->loadVoiceModel($model);

        expect($synthesizer->isLoadedVoiceModel($model->id()))->toBeTrue();

        $audioQuery = $synthesizer->createAudioQuery('こんにちは、VOICEVOXです。', 0);
        $wav = $synthesizer->synthesis($audioQuery, 0);

        expect($audioQuery)->toContain('"accent_phrases"')
            ->and($wav)->toStartWith('RIFF')
            ->and(strlen($wav))->toBeGreaterThan(44);
    });
});
