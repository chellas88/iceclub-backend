<?php

namespace App\Http\Controllers;

use Adminka\AmoCRM\AmoCRM;
use App\AdminkaLibs\AlfaCRM;
use App\Models\AmoAuth;
use App\Models\AmoLogs;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $amo;
    public $alfa;

    public function __construct()
    {
        $alfa_conf = config("ice.alfa");
        $this->alfa = new AlfaCRM($alfa_conf, "WidgetController");
        $amo_app = new AmoAuth();
        $api_data = $amo_app->getApiData(1);
        $this->amo = new AmoCRM($api_data);
        $this->amo->setExternalUpdateToken(function ($token_data) {
            $this->saveToken($token_data);
        });
    }

    public function addAmoLog($source, $data)
    {
        $db_log = new AmoLogs();
        $log['source'] = $source;
        $log['data'] = json_encode($data);
        return $db_log->insertGetId($log);
    }

    public function listAmoLogs()
    {
        $db_log = new AmoLogs();
        return $db_log->get()->toArray();
    }

    public
    function saveToken($token_data)
    {
        $amo_app = new AmoAuth();
        $update_param = [
            "access_token" => $token_data["access_token"],
            "refresh_token" => $token_data["refresh_token"]
        ];
        if (isset($token_data["expires_in"])) {
            $update_param["expires"] = date("Y-m-d H:i:s", time() + $token_data["expires_in"]);
        } elseif (isset($token_data["expired_in"])) {
            $update_param["expires"] = date("Y-m-d H:i:s", $token_data["expired_in"]);
        }
        $amo_app->where("id", $token_data["auth_id"])->update($update_param);
    }
}
