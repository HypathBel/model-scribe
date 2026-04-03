<?php

namespace HypathBel\ModelScribe\Enums;

enum ScribeEvent: string
{
    case Retrieved = 'retrieved';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Custom = 'custom';
}
