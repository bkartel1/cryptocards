<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BattleGroup extends Model
{
    public function group_cards()
    {
        return $this->hasMany(BattleGroupCard::class, 'group_id');
    }
}
