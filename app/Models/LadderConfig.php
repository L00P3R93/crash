<?php

namespace App\Models;

use Database\Factories\LadderConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property float $safe_ratio
 * @property float $risky_ratio
 * @property bool $is_active
 */
#[Fillable(['name', 'safe_ratio', 'risky_ratio', 'is_active'])]
class LadderConfig extends Model
{
    /** @use HasFactory<LadderConfigFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'safe_ratio' => 'decimal:2',
            'risky_ratio' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
