<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core\Enums;

enum AccelerationMode: int
{
    /** 実行環境に合った適切なハードウェアアクセラレーションモードを選択する。 */
    case Auto = 0;

    /** ハードウェアアクセラレーションモードを"CPU"に設定する。 */
    case Cpu = 1;

    /** ハードウェアアクセラレーションモードを"GPU"に設定する。 */
    case Gpu = 2;
}
