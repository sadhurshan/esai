<?php

namespace App\Enums;

enum DigitalTwinStatus: string
{
	case Draft = 'draft';
	case Published = 'published';
	case Archived = 'archived';

	public function isPublished(): bool
	{
		return $this === self::Published;
	}

	public function isArchived(): bool
	{
		return $this === self::Archived;
	}
}