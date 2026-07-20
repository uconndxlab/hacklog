<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    protected $casts = [
        'name' => 'string',
        'slug' => 'string',
        'color' => 'string',
    ];

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            $tag->name = self::normalizeName($tag->name);
            $tag->slug = self::slugifyName($tag->name);
        });
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    public static function slugifyName(string $name): string
    {
        return Str::slug(Str::lower(self::normalizeName($name)));
    }

    public static function normalizeNames(string $rawNames): array
    {
        $parts = preg_split('/[,\n]/', $rawNames) ?: [];

        $normalized = array_map(function (string $part) {
            return self::normalizeName($part);
        }, $parts);

        $filtered = array_values(array_filter($normalized, fn (string $name) => $name !== ''));

        return array_values(array_unique($filtered));
    }
}
