<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;

// php ./example/kana.php で実行

// パス — voicevox_coreのインストール場所に合わせて変更してください
$voicevoxCoreDir = getenv('HOME').'/.local/voicevox_core';
$onnxruntimeFilename = $voicevoxCoreDir.'/onnxruntime/lib/'.Onnxruntime::libRecommendedVersionedFilename();
$dictDir = $voicevoxCoreDir.'/dict/open_jtalk_dic_utf_8-1.11';
$vvmPath = $voicevoxCoreDir.'/models/vvms/0.vvm';

// 読み上げるテキストとスタイルID
$text = 'ディープラーニングは万能薬ではありません';
// 「。」などカタカナとAquesTalk風記法用の記号以外を含めると失敗します
$kana = "ディイプラ'アニングワ/バンノ'オヤクデワ/アリマセ'ン";

$styleId = 0;
$outPath = './build/kana.wav';
if (! is_dir('./build')) {
    mkdir('./build', 0777, true);
}

// 初期化
$onnxruntime = Onnxruntime::loadOnce($onnxruntimeFilename);
$openJtalk = new OpenJtalk($dictDir);
$synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Auto);

// 音声モデルの読み込み
$model = VoiceModelFile::open($vvmPath);
$synthesizer->loadVoiceModel($model);

// 音声合成
$audioQuery = $synthesizer->createAudioQuery($text, $styleId);

// Voicevoxエンジンのサンプルコードに合わせてaccent_phrasesを変更していますがコアにはAquesTalk風記法から直接AudioQueryを作るcreateAudioQueryFromKanaもあります
// $kanaAudioQuery = $synthesizer->createAudioQueryFromKana($kana, $styleId);

$accentPhrasesFromKana = $synthesizer->createAccentPhrasesFromKana($kana, 0);

$kanaAudioQuery = json_decode($audioQuery, true);
$kanaAudioQuery['accent_phrases'] = json_decode($accentPhrasesFromKana, true);
$audioQuery = json_encode($kanaAudioQuery);

$wav = $synthesizer->synthesis($audioQuery, $styleId);

file_put_contents($outPath, $wav);
echo 'Wrote '.$outPath.PHP_EOL;
