<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgeGroup extends Model
{
    use HasFactory;

    public const DEFAULT_ROLE_ID = 1;

    protected $fillable = ['role', 'name', 'from', 'to', 'description', 'is_active'];

    public static function defaultGroups(): array
    {
        return [
            [
                'name' => 'ADOLESCENT EXPLORER',
                'from' => 13,
                'to' => 17,
                'description' => 'Explore your emerging talents and build a strong foundation for growth.',
            ],
            [
                'name' => 'EMERGING ADULT',
                'from' => 18,
                'to' => 25,
                'description' => 'Discover Your Growing Strengths',
            ],
            [
                'name' => 'CAREER BUILDER',
                'from' => 26,
                'to' => 40,
                'description' => 'Build on Your Strengths for Career Success',
            ],
            [
                'name' => 'EXPERIENCED PROFESSIONAL',
                'from' => 41,
                'to' => 120,
                'description' => 'Leverage Your Experience for Continued Growth and Impact.',
            ],
        ];
    }

    public static function createDefaultGroupsForRole(int $roleId = self::DEFAULT_ROLE_ID): array
    {
        $ageGroups = [];

        foreach (self::defaultGroups() as $group) {
            $ageGroups[] = self::firstOrCreate(
                [
                    'role' => $roleId,
                    'name' => $group['name'],
                    'from' => $group['from'],
                    'to' => $group['to'],
                ],
                [
                    'description' => $group['description'],
                    'is_active' => true,
                ]
            );
        }

        return $ageGroups;
    }

    /**
     * Get clusters for this age group
     */
    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    /**
     * Get constructs for this age group
     */
    public function constructs()
    {
        return $this->hasMany(Construct::class);
    }

    /**
     * Get questions for this age group
     */
    public function questions()
    {
        return $this->hasMany(QuestionsModel::class);
    }

    /**
     * Get tests for this age group
     */
    public function tests()
    {
        return $this->hasMany(Test::class);
    }
}
