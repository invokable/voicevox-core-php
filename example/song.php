<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;

// php ./example/song.php で実行

// パス — voicevox_coreのインストール場所に合わせて変更してください
$voicevoxCoreDir = getenv('HOME').'/.local/voicevox_core';
$onnxruntimeFilename = $voicevoxCoreDir.'/onnxruntime/lib/'.Onnxruntime::libVersionedFilename();
$dictDir = $voicevoxCoreDir.'/dict/open_jtalk_dic_utf_8-1.11';
$vvmPath = $voicevoxCoreDir.'/models/vvms/s0.vvm';

// 歌唱用スタイルID
$singingTeacherStyleId = 6000;
$singerStyleId = 3000;
$outPath = './build/song.wav';
if (! is_dir('./build')) {
    mkdir('./build', 0777, true);
}

// song.py の SCORE 相当
$scoreJson = json_encode([
    'notes' => [
        ['key' => null, 'frame_length' => 15, 'lyric' => ''],
        ['key' => 60, 'frame_length' => 45, 'lyric' => 'ド'],
        ['key' => 62, 'frame_length' => 45, 'lyric' => 'レ'],
        ['key' => 64, 'frame_length' => 45, 'lyric' => 'ミ'],
        ['key' => null, 'frame_length' => 15, 'lyric' => ''],
    ],
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

// 初期化
$onnxruntime = Onnxruntime::loadOnce($onnxruntimeFilename);
$openJtalk = new OpenJtalk($dictDir);
$synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Auto);

// 音声モデルの読み込み
$model = VoiceModelFile::open($vvmPath);
$synthesizer->loadVoiceModel($model);

// 歌唱音声合成
$frameAudioQueryJson = $synthesizer->createSingFrameAudioQuery(
    $scoreJson,
    $singingTeacherStyleId,
);
$wav = $synthesizer->frameSynthesis($frameAudioQueryJson, $singerStyleId);

file_put_contents($outPath, $wav);
echo 'Wrote '.$outPath.PHP_EOL;
