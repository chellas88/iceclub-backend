<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlfaGroups extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function listAges()
    {
        $age_list = null;
        $list = null;
        $ages = $this->select("age")->distinct()->get()->toArray();
        foreach ($ages as $age) {
            $age_list[] = $age["age"];
        }
        natcasesort($age_list);
        foreach ($age_list as $age) {
            $list[] = $age;
        }
        return $list;
    }
}
