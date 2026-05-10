<?php

declare(strict_types=1);

namespace Revolution\Voicevox\Core\Enums;

enum UserDictWordType: int
{
    /** 固有名詞。 */
    case ProperNoun = 0;

    /** 一般名詞。 */
    case CommonNoun = 1;

    /** 動詞。 */
    case Verb = 2;

    /** 形容詞。 */
    case Adjective = 3;

    /** 接尾辞。 */
    case Suffix = 4;
}
