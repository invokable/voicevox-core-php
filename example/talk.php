<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;

// パス — voicevox_coreのインストール場所に合わせて変更してください
$voicevoxCoreDir = getenv('HOME').'/.local/voicevox_core';
$onnxruntimeFilename = $voicevoxCoreDir.'/onnxruntime/lib/'.Onnxruntime::libVersionedFilename();
$dictDir = $voicevoxCoreDir.'/dict/open_jtalk_dic_utf_8-1.11';
$vvmPath = $voicevoxCoreDir.'/models/vvms/0.vvm';

// 読み上げるテキストとスタイルID
$text = 'この音声は、ボイスボックスを使用して、出力されています。';
$styleId = 0;
$outPath = './output.wav';

// 初期化
$onnxruntime = Onnxruntime::loadOnce($onnxruntimeFilename);
$openJtalk = new OpenJtalk($dictDir);
$synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Auto);

// 音声モデルの読み込み
$model = VoiceModelFile::open($vvmPath);
$synthesizer->loadVoiceModel($model);

// 音声合成
$audioQuery = $synthesizer->createAudioQuery($text, $styleId);
$wav = $synthesizer->synthesis($audioQuery, $styleId);

file_put_contents($outPath, $wav);
echo 'Wrote '.$outPath.PHP_EOL;
