<?php

namespace App\Enums;

/**
 * The 22 jobs of Final Fantasy XI (ADR-013 §2.8).
 *
 * Jobs are game rules, not administrative data, so they live in a PHP enum
 * instead of a database catalogue. The value is the uppercase code, matching
 * `users.main_job` ("PLD"), and codes are not translated.
 */
enum GameJob: string
{
    case WAR = 'WAR';
    case MNK = 'MNK';
    case WHM = 'WHM';
    case BLM = 'BLM';
    case RDM = 'RDM';
    case THF = 'THF';
    case PLD = 'PLD';
    case DRK = 'DRK';
    case BST = 'BST';
    case BRD = 'BRD';
    case RNG = 'RNG';
    case SAM = 'SAM';
    case NIN = 'NIN';
    case DRG = 'DRG';
    case SMN = 'SMN';
    case BLU = 'BLU';
    case COR = 'COR';
    case PUP = 'PUP';
    case DNC = 'DNC';
    case SCH = 'SCH';
    case GEO = 'GEO';
    case RUN = 'RUN';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
