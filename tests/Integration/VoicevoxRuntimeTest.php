<?php

declare(strict_types=1);

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\UserDict;
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

/**
 * @return array{
 *     root: string,
 *     libraryPath: string,
 *     onnxruntimePath: string,
 *     openJtalkDir: string,
 *     voiceModelPath: string
 * }
 */
function voicevoxRuntimeFixturePaths(): array
{
    $root = voicevoxRuntimeTestRoot();

    if ($root === null) {
        test()->markTestSkipped('Set VOICEVOX_CORE_TEST_ROOT to run the integration tests.');
    }

    $libraryPath = voicevoxRuntimeTestLibraryPath($root);
    $voiceModelPath = $root.'/models/vvms/0.vvm';
    $onnxruntimePath = voicevoxRuntimeTestOnnxruntimePath($root);
    $openJtalkDir = voicevoxRuntimeTestOpenJtalkDir($root);

    if (! file_exists($libraryPath)) {
        throw new RuntimeException('Could not find the VOICEVOX Core library at '.$libraryPath);
    }

    if (! file_exists($voiceModelPath)) {
        throw new RuntimeException('Could not find the voice model at '.$voiceModelPath);
    }

    putenv("VOICEVOX_CORE_LIB_PATH={$libraryPath}");

    return [
        'root' => $root,
        'libraryPath' => $libraryPath,
        'onnxruntimePath' => $onnxruntimePath,
        'openJtalkDir' => $openJtalkDir,
        'voiceModelPath' => $voiceModelPath,
    ];
}

/**
 * @return array{
 *     onnxruntime: Onnxruntime,
 *     openJtalk: OpenJtalk,
 *     synthesizer: Synthesizer,
 *     model: VoiceModelFile
 * }
 */
function voicevoxRuntimeFixture(): array
{
    $paths = voicevoxRuntimeFixturePaths();

    $onnxruntime = Onnxruntime::loadOnce($paths['onnxruntimePath']);
    $openJtalk = new OpenJtalk($paths['openJtalkDir']);
    $synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Cpu);
    $model = VoiceModelFile::open($paths['voiceModelPath']);
    $synthesizer->loadVoiceModel($model);

    return compact('onnxruntime', 'openJtalk', 'synthesizer', 'model');
}

describe('VOICEVOX runtime integration', function () {
    it('loads runtime assets and exposes onnxruntime metadata', function () {
        $paths = voicevoxRuntimeFixturePaths();
        $onnxruntime = Onnxruntime::loadOnce($paths['onnxruntimePath']);

        $devicesJson = $onnxruntime->supportedDevices();
        $devices = json_decode($devicesJson, true);

        expect($onnxruntime)->toBe(Onnxruntime::get())
            ->and($devices)->toBeArray()
            ->and(Onnxruntime::libVersionedFilename())->not->toBe('')
            ->and(Onnxruntime::libUnversionedFilename())->not->toBe('');
    });

    it('loads and unloads a voice model', function () {
        ['synthesizer' => $synthesizer, 'model' => $model] = voicevoxRuntimeFixture();

        $modelId = $model->id();
        $modelMetas = $model->createMetasJson();
        $synthesizerMetas = $synthesizer->metas();

        expect($modelId)->toHaveLength(32)
            ->and($modelMetas)->toContain('"styles"')
            ->and($synthesizerMetas)->toContain('"styles"')
            ->and($synthesizer->isLoadedVoiceModel($modelId))->toBeTrue();

        $synthesizer->unloadVoiceModel($modelId);

        expect($synthesizer->isLoadedVoiceModel($modelId))->toBeFalse();
    });

    it('creates query variants and synthesizes wav data', function () {
        ['synthesizer' => $synthesizer] = voicevoxRuntimeFixture();

        $textAudioQuery = $synthesizer->createAudioQuery('こんにちは、VOICEVOXです。', 0);
        $kanaAudioQuery = $synthesizer->createAudioQueryFromKana('コンニチワ、ボイスボックスデス。', 0);
        $accentPhrases = $synthesizer->createAccentPhrases('こんにちは、VOICEVOXです。', 0);
        $accentPhrasesFromKana = $synthesizer->createAccentPhrasesFromKana('コンニチワ、ボイスボックスデス。', 0);

        $replacedMoraData = $synthesizer->replaceMoraData($accentPhrases, 0);
        $replacedPhonemeLength = $synthesizer->replacePhonemeLength($accentPhrases, 0);
        $replacedMoraPitch = $synthesizer->replaceMoraPitch($accentPhrases, 0);

        $synthesisWav = $synthesizer->synthesis($textAudioQuery, 0);
        $ttsWav = $synthesizer->tts('こんにちは、VOICEVOXです。', 0);
        $ttsFromKanaWav = $synthesizer->ttsFromKana('コンニチワ、ボイスボックスデス。', 0);

        expect($textAudioQuery)->toContain('"accent_phrases"')
            ->and($kanaAudioQuery)->toContain('"accent_phrases"')
            ->and($accentPhrases)->toContain('"accent"')
            ->and($accentPhrasesFromKana)->toContain('"accent"')
            ->and($replacedMoraData)->toContain('"accent"')
            ->and($replacedPhonemeLength)->toContain('"accent"')
            ->and($replacedMoraPitch)->toContain('"accent"')
            ->and($synthesisWav)->toStartWith('RIFF')
            ->and($ttsWav)->toStartWith('RIFF')
            ->and($ttsFromKanaWav)->toStartWith('RIFF')
            ->and(strlen($synthesisWav))->toBeGreaterThan(44)
            ->and(strlen($ttsWav))->toBeGreaterThan(44)
            ->and(strlen($ttsFromKanaWav))->toBeGreaterThan(44);
    });

    it('supports user dictionary lifecycle and openjtalk integration', function () {
        ['openJtalk' => $openJtalk] = voicevoxRuntimeFixture();

        $userDict = new UserDict;
        $wordUuid = $userDict->addWord('テスト単語', 'テストタンゴ', 2);
        $userDict->updateWord($wordUuid, 'テスト単語', 'テストタンゴ', 2);

        $dictPath = '/tmp/voicevox-user-dict-'.bin2hex(random_bytes(8)).'.json';

        try {
            $userDict->save($dictPath);
            expect(file_exists($dictPath))->toBeTrue();

            $loadedDict = new UserDict;
            $loadedDict->load($dictPath);

            $importedDict = new UserDict;
            $importedDict->importDict($loadedDict);

            $openJtalk->useUserDict($importedDict);

            $json = $importedDict->toJson();
            expect($json)->toContain('テスト単語')
                ->and($wordUuid)->toHaveLength(32);

            $importedDict->removeWord($wordUuid);
        } finally {
            if (file_exists($dictPath)) {
                unlink($dictPath);
            }
        }
    });
});
