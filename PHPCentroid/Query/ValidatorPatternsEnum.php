<?php

namespace PHPCentroid\Query;

enum ValidatorPatternsEnum: string {
    case Default = '([a-zA-Z0-9_]+)';
    case Latin = '([\u0030-\u0039\u0041-\u005A\u0061-\u007A\u005F]+)';
    case LatinExtended = '([\u0030-\u0039\u0041-\u005A\u0061-\u007A\u00A0-\u024F\u005F]+)';
    case Greek = '([\u0030-\u0039\u0041-\u005A\u0061-\u007A\u0386-\u03CE\u005F]+)';
    case Cyrillic = '([\u0030-\u0039\u0041-\u007A\u0061-\u007A\u0400-\u04FF\u005F]+)';
    case Hebrew = '([\u0030-\u0039\u0041-\u005A\u0061-\u007A\u05D0-\u05F2\u005F]+)';
}