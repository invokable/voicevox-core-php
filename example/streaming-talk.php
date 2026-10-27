<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;
use Revolution\Voicevox\Core\VoicevoxCore;

// php ./example/streaming-talk.php で実行

$voicevoxCoreDir = getenv('HOME').'/.local/voicevox_core';
$onnxruntimeFilename = $voicevoxCoreDir.'/onnxruntime/lib/'.Onnxruntime::libRecommendedVersionedFilename();
$dictDir = $voicevoxCoreDir.'/dict/open_jtalk_dic_utf_8-1.11';
$vvmPath = $voicevoxCoreDir.'/models/vvms/0.vvm';

$text = 'この音声は、ボイスボックスを使用して、出力されています。';
$styleId = 0;
$outPath = './build/talk.pcm';

if (! is_dir('./build') && ! mkdir('./build', 0777, true) && ! is_dir('./build')) {
    throw new RuntimeException('Could not create the build directory.');
}

$onnxruntime = Onnxruntime::loadOnce($onnxruntimeFilename);
$openJtalk = new OpenJtalk($dictDir);
$synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Auto);

$model = VoiceModelFile::open($vvmPath);
$synthesizer->loadVoiceModel($model);

$audioQuery = $synthesizer->createAudioQuery($text, $styleId);
$audioFeature = $synthesizer->createAudioFeature($audioQuery, $styleId);
$frameRate = (new VoicevoxCore)->getFrameRate();
$chunkFrameLength = max(1, (int) round(0.3 * $frameRate));
$totalFrames = $audioFeature->length();

$stream = fopen($outPath, 'wb');

if ($stream === false) {
    throw new RuntimeException('Could not open '.$outPath.' for writing.');
}

try {
    for ($start = 0; $start < $totalFrames; $start += $chunkFrameLength) {
        $end = min($start + $chunkFrameLength, $totalFrames);
        $pcm = $audioFeature->slice($start, $end)->render();

        if ($pcm !== '' && fwrite($stream, $pcm) !== strlen($pcm)) {
            throw new RuntimeException('Could not write the complete PCM chunk.');
        }
    }
} finally {
    fclose($stream);
}

echo 'Wrote raw PCM to '.$outPath.' ('.$frameRate.' frames/second)'.PHP_EOL;
