<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core\Enums;

enum OnExistingVoiceModelId: int
{
    /** エラーにする。デフォルトのふるまい。 */
    case Error = 0;

    /** 既存の音声モデルを再読み込みする。 */
    case Reload = 1;

    /** 何もしない。 */
    case Skip = 2;
}
