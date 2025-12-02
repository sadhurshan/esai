<?php

namespace App\Enums;

enum DigitalTwinAssetType: string
{
    case CAD = 'CAD';
    case STEP = 'STEP';
    case STL = 'STL';
    case PDF = 'PDF';
    case IMAGE = 'IMAGE';
    case DATA = 'DATA';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CAD => 'CAD Model',
            self::STEP => 'STEP Model',
            self::STL => 'STL Mesh',
            self::PDF => 'PDF Document',
            self::IMAGE => 'Image',
            self::DATA => 'Data File',
            self::OTHER => 'Other',
        };
    }
}
