<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function assignedUsers(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'model', 'model_has_roles');
    }
}
