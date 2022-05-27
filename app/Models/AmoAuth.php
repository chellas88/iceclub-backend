<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmoAuth extends Model
{
    use HasFactory;

    public function getAuthData($app_id)
    {
        $amo_app = $this->find($app_id);
        return [
            "auth_id" => $amo_app["id"],
            "client_id" => $amo_app["client_id"],
            "client_secret" => $amo_app["client_secret"],
            "base_domain" => $amo_app["base_domain"],
            "redirect_url" => $amo_app["redirect_url"]
        ];
    }

    public function getApiData($app_id)
    {
        $amo_app = $this->find($app_id);
        return [
            "auth_id" => $amo_app["id"],
            "client_id" => $amo_app["client_id"],
            "client_secret" => $amo_app["client_secret"],
            "base_domain" => $amo_app["base_domain"],
            "access_token" => $amo_app["access_token"],
            "refresh_token" => $amo_app["refresh_token"],
            "redirect_url" => $amo_app["redirect_url"]
        ];
    }

}
