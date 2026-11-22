# Muse 環境で VOICEVOX Core を使う（PHP FFI）

Muse（Metaのパーソナルエージェント）の仮想マシン環境で、このパッケージ（`revolution/voicevox-core`）を使って実際に音声合成を行うまでの手順と検証結果を記録します。Muse とチャットでやり取りしながら VOICEVOX を使いたい場合の参考資料です。

検証した組み合わせ（2026-11-22）:

| 項目 | バージョン等 |
| --- | --- |
| VOICEVOX Core | 0.17.1 |
| revolution/voicevox-core | 0.17.1 |
| 音声モデル（VVM） | voicevox_vvm 0.17.0 の `0.vvm`（CIのインテグレーションテストと同じ組み合わせ） |
| ONNX Runtime | voicevox_onnxruntime 1.23.2（Linux x64、CPU） |
| PHP（FFI用に別途用意） | 8.3.6（Ubuntu 24.04 のパッケージ） |
| 環境 | Muse の Linux VM（x86_64、共有CPUのため処理は遅め） |

Muse の環境は再作成されることがあります。`/etc/apt` のカスタマイズなどシステム側の変更は残らない前提で、必要なものはすべてホームディレクトリ配下（`~/workspace/` など、永続化される領域）に置くのが基本方針です。

## つまずきポイントは2つ

通常の手順（公式ダウンローダーを実行し、手元のPHPで合成する）だけでは動かず、次の2点で工夫が必要でした。

### 1. 標準のPHPに ext-ffi がない

Muse 環境の標準PHPは static-php-cli 製のビルドで、`ext-ffi` が含まれていません。このパッケージは `ext-ffi` が必須なので、そのままでは使えません。

標準PHPは他の作業でも使われているため置き換えず、FFI用に別のPHPを分けて用意します。今回は Ubuntu 24.04（noble）の deb パッケージを展開する方式を取りました。システムへインストールしないので環境を汚さず、展開先がホーム配下なので VM が再作成されても残ります。

```bash
mkdir -p ~/workspace/tools/php83-ffi/debs
cd ~/workspace/tools/php83-ffi/debs

# azure ミラーから必要な deb を取得（php8.3-zip などは不要）
BASE=http://azure.archive.ubuntu.com/ubuntu/pool/main/p/php8.3
VER=8.3.6-0ubuntu0.24.04.14
for p in php8.3-cli php8.3-common php8.3-opcache php8.3-readline \
         php8.3-mbstring php8.3-xml php8.3-curl; do
  curl -fsSL -O "$BASE/${p}_${VER}_amd64.deb"
done

# 展開するだけ（インストールはしない）
cd ~/workspace/tools/php83-ffi
for d in debs/*.deb; do dpkg-deb -x "$d" root; done
```

ラッパースクリプト `~/workspace/tools/bin/php83-ffi` を作り、`ffi.enable=true` で起動できるようにします。

```sh
#!/bin/sh
ROOT="$HOME/workspace/tools/php83-ffi/root"
EXT="$ROOT/usr/lib/php/20230831"
exec "$ROOT/usr/bin/php8.3" -n \
  -d extension_dir="$EXT" \
  -d ffi.enable=true \
  -d extension=ffi.so \
  -d extension=phar.so \
  -d extension=mbstring.so \
  -d extension=curl.so \
  -d extension=dom.so \
  -d extension=simplexml.so \
  -d extension=xml.so \
  -d extension=xmlreader.so \
  -d extension=xmlwriter.so \
  -d extension=tokenizer.so \
  -d extension=ctype.so \
  -d extension=fileinfo.so \
  -d extension=iconv.so \
  -d extension=posix.so \
  -d extension=sockets.so \
  "$@"
```

動作確認（FFI で libc の関数を呼べれば成功です）:

```bash
php83-ffi -v
php83-ffi -r 'var_dump(class_exists("FFI"));'
```

代替案として、static-php-cli（spc）で `ext-ffi` 入りのカスタムビルドを作る方法もあります。ただし静的ビルドのPHPから glibc 向けの `libvoicevox_core.so` を読み込めるかは別途検証が必要です。今回の deb 展開方式は glibc 動的リンクのPHPなので、その心配がありません。

### 2. 公式ダウンローダーがプロキシを通れない

VOICEVOX Core の公式ダウンローダー（`download-linux-x64`）は、Muse 環境の外向きプロキシをうまく通れず、`client error (Connect)` / `received corrupt message of type InvalidContentType` で失敗しました（curl や gh では同じURLにアクセスできます）。

そのため、ダウンローダーが取得する部品を個別にダウンロードして、ダウンローダーと同じディレクトリ構成に手作業で組み立てます。

| 部品 | 取得元 | 配置先 |
| --- | --- | --- |
| C API | `VOICEVOX/voicevox_core` リリース 0.17.1 の `voicevox_core-linux-x64-0.17.1.zip` | `voicevox_core/c_api/lib/libvoicevox_core.so` |
| ONNX Runtime | `VOICEVOX/onnxruntime-builder` リリース `voicevox_onnxruntime-1.23.2` の `voicevox_onnxruntime-linux-x64-1.23.2.tgz` | `voicevox_core/onnxruntime/lib/` 以下 |
| OpenJTalk辞書 | `r9y9/open_jtalk` リリース v1.11.1 の `open_jtalk_dic_utf_8-1.11.tar.gz` | `voicevox_core/dict/open_jtalk_dic_utf_8-1.11/` |
| 音声モデル | `VOICEVOX/voicevox_vvm` リリース 0.17.0 の `0.vvm`（約57MB） | `voicevox_core/models/vvms/0.vvm` |

```bash
mkdir -p voicevox_core/{c_api/lib,onnxruntime/lib,models/vvms,dict}

# C API
curl -fsSL -o /tmp/core.zip \
  https://github.com/VOICEVOX/voicevox_core/releases/download/0.17.1/voicevox_core-linux-x64-0.17.1.zip
unzip -p /tmp/core.zip '*/lib/libvoicevox_core.so' > voicevox_core/c_api/lib/libvoicevox_core.so

# ONNX Runtime
curl -fsSL -o /tmp/onnx.tgz \
  https://github.com/VOICEVOX/onnxruntime-builder/releases/download/voicevox_onnxruntime-1.23.2/voicevox_onnxruntime-linux-x64-1.23.2.tgz
tar -xzf /tmp/onnx.tgz --no-same-owner -C /tmp
cp -r /tmp/voicevox_onnxruntime-linux-x64-1.23.2/lib/. voicevox_core/onnxruntime/lib/

# OpenJTalk辞書
curl -fsSL -o /tmp/dict.tar.gz \
  https://github.com/r9y9/open_jtalk/releases/download/v1.11.1/open_jtalk_dic_utf_8-1.11.tar.gz
tar -xzf /tmp/dict.tar.gz --no-same-owner -C voicevox_core/dict

# 音声モデル（ずんだもん等が入った 0.vvm のみ）
curl -fsSL -o voicevox_core/models/vvms/0.vvm \
  https://github.com/VOICEVOX/voicevox_vvm/releases/download/0.17.0/0.vvm
```

注意:

- Muse 環境では所有者変更（chown）が禁止されているため、tar 展開時は `--no-same-owner` を付けます（付けないと所有者復元で警告・エラーになりますが、ファイル自体は展開されます）。
- VVM のファイル番号は話者と1対1ではありません。1つのVVMに複数話者が含まれ、話者のスタイルが複数のVVMに分かれていることもあります。`0.vvm` には四国めたん・ずんだもん・春日部つむぎ・雨晴はうが含まれます（`1.vvm` は冥鳴ひまり等で、ずんだもんは入っていません）。使いたいスタイルIDは、モデルのメタ情報（`VoiceModelFile::createMetasJson()` や VVM 内の `metas.json`）で必ず確認してください。

ずんだもんのスタイルID（0.17.0）:

| スタイル | ID | 入っているVVM |
| --- | --- | --- |
| ノーマル | 3 | `0.vvm` |
| あまあま | 1 | `0.vvm` |
| ツンツン | 7 | `0.vvm` |
| セクシー | 5 | `0.vvm` |
| ささやき | 22 | `5.vvm` |
| ヒソヒソ | 38 | `5.vvm` |
| ヘロヘロ | 75 | `15.vvm` |
| なみだめ | 76 | `15.vvm` |

## パッケージのインストール

Composer も FFI 用PHPで実行します（`ext-ffi` の要求チェックを通すため）。

```bash
mkdir -p ~/workspace/voicevox-test && cd ~/workspace/voicevox-test
# voicevox_core/ は上記の手順でこのディレクトリに配置済みとする
php83-ffi /path/to/composer.phar require revolution/voicevox-core:0.17.1
```

## 合成の例（ずんだもん）

`example/talk.php` と同じ流れで、モデルを `0.vvm`、スタイルIDを `3`（ずんだもん「ノーマル」）にした例です。

```php
<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Revolution\Voicevox\Core\Enums\AccelerationMode;
use Revolution\Voicevox\Core\Onnxruntime;
use Revolution\Voicevox\Core\OpenJtalk;
use Revolution\Voicevox\Core\Synthesizer;
use Revolution\Voicevox\Core\VoiceModelFile;

$voicevoxCoreDir = __DIR__.'/voicevox_core';
$onnxruntimeFilename = $voicevoxCoreDir.'/onnxruntime/lib/'.Onnxruntime::libRecommendedVersionedFilename();
$dictDir = $voicevoxCoreDir.'/dict/open_jtalk_dic_utf_8-1.11';
$vvmPath = $voicevoxCoreDir.'/models/vvms/0.vvm'; // 四国めたん＋ずんだもん等

// ネイティブモードには英単語のカタカナ変換がないため、「PHP」はカタカナで書く
$text = 'こんにちは、ずんだもんなのだ。ミューズの環境で、ピーエイチピーから音声合成できたのだ。';
$styleId = 3; // ずんだもん「ノーマル」
$outPath = __DIR__.'/zundamon.wav';

$onnxruntime = Onnxruntime::loadOnce($onnxruntimeFilename);
$openJtalk = new OpenJtalk($dictDir);
$synthesizer = new Synthesizer($onnxruntime, $openJtalk, AccelerationMode::Auto);

$model = VoiceModelFile::open($vvmPath);
$synthesizer->loadVoiceModel($model);

$audioQuery = $synthesizer->createAudioQuery($text, $styleId);
$wav = $synthesizer->synthesis($audioQuery, $styleId);

file_put_contents($outPath, $wav);
echo 'Wrote '.$outPath.PHP_EOL;
```

実行にはライブラリの場所を環境変数で伝えます。

```bash
cd ~/workspace/voicevox-test
export VOICEVOX_CORE_LIB_PATH="$PWD/voicevox_core/c_api/lib/libvoicevox_core.so"
export LD_LIBRARY_PATH="$PWD/voicevox_core/c_api/lib:$PWD/voicevox_core/onnxruntime/lib"
php83-ffi zundamon.php
```

## 検証結果

2026-11-22 に上記の手順で合成できました。

- 出力: 7.67秒のWAV（24kHz / 16bit / モノラル、368,172バイト）
- 合成時間: 約9秒（共有1.5CPU相当に制限されたVM、CPU推論のみ）
- 四国めたん（`0.vvm`、スタイルID 0「あまあま」）でも同様に合成できています

## 補足

- このパッケージは CLI 専用です（Webサーバー環境では FFI が無効化されていることが多いため）。Muse 環境でも CLI 実行のみを想定します。
- 生成した音声の利用には VOICEVOX および各音声ライブラリの利用規約が適用されます。ずんだもんの音声を使う場合は「VOICEVOX:ずんだもん」のクレジット表記が必要です。
- バージョンが上がった場合は、まずダウンローダーがそのまま動くか試し、ダメなら部品のURLを新しいバージョンに差し替えて組み立てるのが近道です。対応要否の判断材料として、このリポジトリの `docs/voicevox-core-*-migration-plan.md` も参照してください。
