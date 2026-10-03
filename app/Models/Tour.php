<?php

namespace App\Models;

use Database\Factories\TourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'tickets_left', 'tickets_sold'])]
class Tour extends Model
{
    /** @use HasFactory<TourFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tickets_left' => 'integer',
            'tickets_sold' => 'integer',
        ];
    }

    /**
     * Determine if the tour still has golden tickets to hand out.
     */
    public function hasTicketsLeft(): bool
    {
        return $this->tickets_left > 0;
    }
}
