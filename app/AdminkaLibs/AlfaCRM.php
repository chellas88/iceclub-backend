<?php

/**
 * Description of AlfaCRM
 *
 * @author Hulk
 */

namespace App\AdminkaLibs;

use App\Models\AlfaLogs;
use Illuminate\Support\Facades\Http;

class AlfaCRM
{

    private $config;
    private $errors_num;
    private $token;
    private $logs;
    private $script;

    public function __construct($config, $script_name)
    {
        $this->script = $script_name;
        $this->config = $config;
        $this->logs = new AlfaLogs();
        $this->refreshToken();
    }

    private function addLog($source, $log_data)
    {
        $error_data = $log_data->json();
        $error_data["code"] = $log_data->status();
        $error_data["errors_num"] = $this->errors_num;
        if (!isset($error_data["message"])) {
            $error_data["message"] = "Unknown error";
        }
        $new_log = $this->logs;
        $new_log->source = $source;
        $new_log->message = $error_data["message"];
        $new_log->data = json_encode($error_data);
        $new_log->save();
    }

    private function refreshToken()
    {
        $data = ["email" => $this->config["email"], "api_key" => $this->config["api_key"]];
        $url = "https://" . $this->config["domain"] . "/v2api/auth/login";
        $res = Http::withHeaders(["Accept" => "application/json", "Content-Type" => "application/json"])->post($url, $data);
        if ($res->status() == 200) {
            $this->token = $res->json(["token"]);
            $this->errors_num = 0;
        } else {
            $this->addLog("refreshToken", $res);
            $this->errors_num++;
            if ($this->errors_num < 5) {
                $this->refreshToken();
            }
        }
    }

    private function postRequest($method, $data = null)
    {
        $headers = [
            "X-ALFACRM-TOKEN" => $this->token,
            "Accept" => "application/json",
            "Content-Typ" => "application/json"
        ];
        $url = "https://" . $this->config["domain"] . "/v2api/" . $method;
        if ($data) {
            $res = Http::withHeaders($headers)->post($url, $data);
        } else {
            $res = Http::withHeaders($headers)->post($url);
        }
        //dd($res->json());
        if ($res->status() == 200) {
            return $res->json();
        } else {
            $this->addLog($method, $res);
            $this->errors_num++;
            if (($res->status() >= 400) && ($res->status() < 405) && ($this->errors_num < 5)) {
                $this->refreshToken();
                return $this->postRequest($method, $data);
            }
            return $res->json();
        }
    }

    //Создвние лидов
    public function createCGI($group_id, $cgi_param, $branch = null) // customer group involement
    {
        if ($branch) {
            $method = $branch . "/cgi/create?group_id=" . $group_id;
        } else {
            $method = $this->config["global_branch"] . "/cgi/create?group_id=" . $group_id;
        }
        return $this->postRequest($method, $cgi_param);
    }

    //Список филиалов
    public function listBranches()
    {
        $method = "branch/index";
        $data = [
            'is_active' => 1,
            'page' => 0
        ];
        $res = $this->postRequest($method, $data);
        if ((isset($res["items"])) && (!empty($res["items"]))) {
            return $res["items"];
        } else {
            return null;
        }
    }

    //Поиск лида/лидов
    public function listCustomers($params = null, $branch = null)
    {
        $res = true;
        $page = 0;
        $total_customer = null;
        $customer_list = null;
        if ($branch) {
            $method = $branch . "/customer/index";
        } else {
            $method = $this->config["global_branch"] . "/customer/index";
        }
        while ($res) {
            $customer_param = [
                "page" => $page,
                "is_study" => 1
            ];
            if (!empty($params)) {
                foreach ($params as $param => $val) {
                    $customer_param[$param] = $val;
                }
            }
            $customers = $this->postRequest($method, $customer_param);
            $total_customer += $customers["count"];
            if ($customers['total'] == $total_customer) {
                $res = FALSE;
            } else {
                $page++;
            }
            if (!empty($customers["items"])) {
                foreach ($customers["items"] as $customer) {
                    $customer_list[] = $customer;
                }
            }
        }
        return $customer_list;
    }

    // Lesson list
    public function listLessons($branch = null, $param = null)
    {
        if ($branch) {
            $method = $branch . '/lesson/index';
        } else {
            $method = $this->config["global_branch"] . '/lesson/index';
        }
        $get = true;
        $page = 0;
        $total_lessons = null;
        $lesson_list = null;
        $lesson_param = [
            "pageSize" => 50,
        ];
        if ($param) {
            $lesson_param = array_merge($lesson_param, $param);
        }
        while ($get) {
            $lesson_param["page"] = $page;
            $lessons = $this->postRequest($method, $lesson_param);
            $total_lessons += $lessons["count"];
            if ($lessons['total'] == $total_lessons) {
                $get = false;
            } else {
                $page++;
            }
            foreach ($lessons["items"] as $lesson) {
                $lesson_list[] = $lesson;
            }
            $get = false;
        }
        if (!empty($lesson_list)) {
            return $lesson_list;
        } else {
            return null;
        }
    }

    //Список филиалов
    public function listLocations($branch = null)
    {
        if ($branch) {
            $method = $branch . '/location/index';
        } else {
            $method = $this->config["global_branch"] . '/location/index';
        }
        $res = $this->postRequest($method);
        if ((isset($res["items"])) && (!empty($res["items"]))) {
            return $res["items"];
        } else {
            return null;
        }
    }

    // Список предметов
    public function listSubject($branch = null)
    {
        if ($branch) {
            $method = $branch . "/subject/index";
        } else {
            $method = $this->config["global_branch"] . "/subject/index";
        }
        $res = $this->postRequest($method);
        if ((isset($res["items"])) && (!empty($res["items"]))) {
            return $res["items"];
        } else {
            return null;
        }
    }

    //Создвние лидов
    public function leadCreate($lead_data, $branch = null)
    {
        if ($branch) {
            $method = $branch . '/customer/create';
        } else {
            $method = $this->config["global_branch"] . '/customer/create';
        }
        return $this->postRequest($method, $lead_data);
    }

    //Список участников групп
    public function getCGI($group_id, $branch = null)
    {
        if ($branch) {
            $method = $branch . '/cgi/index?group_id=' . $group_id;
        } else {
            $method = $this->config["global_branch"] . '/cgi/index?group_id=' . $group_id;
        }
        return $this->postRequest($method);
    }

    // Календарь клиента
    public function getCustomerCalendar($param, $branch = null)
    {
        if (empty($branch)) {
            $method = $branch . "/calendar/customer";
        } else {
            $method = $this->config["global_branch"] . "/calendar/customer";
        }
        if (!empty($param)) {
            $method .= "?" . http_build_query($param);
        }
        return $this->postRequest($method);
    }

    // Календарь клиента
    public function getTeacherCalendar($param, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/calendar/teacher";
        } else {
            $method = $this->config["global_branch"] . "/calendar/teacher";
        }
        if (!empty($param)) {
            $method .= "?" . http_build_query($param);
        }
        return $this->postRequest($method);
    }

    // Календарь группы
    public function getGroupCalendar($param, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/calendar/group";
        } else {
            $method = $this->config["global_branch"] . "/calendar/group";
        }
        if (!empty($param)) {
            $method .= "?" . http_build_query($param);
        }
        return $this->postRequest($method);
    }

    //Поиск лида/лидов
    public function getCustomerFromID($customer_id, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/customer/index";
        } else {
            $method = $this->config["global_branch"] . "/customer/index";
        }
        $param = [
            "id" => $customer_id,
            "is_study" => 1
        ];
        $result = $this->postRequest($method, $param);
        if (!empty($result["items"])) {
            return $result["items"][0];
        } else {
            $param = [
                "id" => $customer_id,
                "is_study" => 0
            ];
            $result = $this->postRequest($method, $param);
            if (!empty($result["items"])) {
                return $result["items"][0];
            } else {
                return null;
            }
        }
    }

    // Список групп
    public function getGroupFromID($group_id, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/group/index";
        } else {
            $method = $this->config["global_branch"] . "/group/index";
        }
        $param = [
            "id" => $group_id,
        ];
        $result = $this->postRequest($method, $param);
        if (!empty($result["items"])) {
            return $result["items"][0];
        } else {
            return null;
        }
    }

    public function getLesson($id, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/lesson/index";
        } else {
            $method = $this->config["global_branch"] . "/lesson/index";
        }
        $lesson_params = [
            "id" => $id
        ];
        $result = $this->postRequest($method, $lesson_params);
        if ((isset($result["items"])) && (!empty($result["items"]))) {
            return $result["items"][0];
        } else {
            return $result;
        }
    }

// Список групп
    public function getRegularLessons($branch = null, $id = null)
    {
        if ($branch) {
            $method = $branch . "/regular-lesson/index";
        } else {
            $method = $this->config["global_branch"] . "/regular-lesson/index";
        }
        if ($id) {
            //$result = $this->postRequest($method, ["id" => $id]);
            $result = $this->postRequest($method);
        } else {
            $result = $this->postRequest($method);
        }
        if (!empty($result["items"])) {
            return $result["items"];
        } else {
            return null;
        }
    }

    // Список педагогов
    public function getTeacher($param, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/teacher/index";
        } else {
            $method = $this->config["global_branch"] . "/teacher/index";
        }
        $result = $this->postRequest($method, $param);
        if (($result) && ($result["count"] > 0)) {
            return $result["items"];
        } else {
            return null;
        }
    }

    // Список педагогов
    public function getTeacherFromID($teacher_id, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/teacher/index";
        } else {
            $method = $this->config["global_branch"] . "/teacher/index";
        }
        $param = [
            "id" => $teacher_id,
        ];
        $result = $this->postRequest($method, $param);
        if (!empty($result["items"])) {
            return $result["items"][0];
        } else {
            return null;
        }
    }

//Обновление лида/лидов
    public function leadUpdate($lead, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/customer/update?id=" . $lead["id"];
        } else {
            $method = $this->config["global_branch"] . "/customer/update?id=" . $lead["id"];
        }
        return $this->postRequest($method, $lead);
    }

// Список групп
    public function listGroups($branch = null)
    {
        if ($branch) {
            $method = $branch . "/group/index";
        } else {
            $method = $this->config["global_branch"] . "/group/index";
        }
        $group_list = null;
        $total = null;
        $page = 0;
        $res = true;
        while ($res) {
            $param = [
                'page' => $page
            ];
            $result = $this->postRequest($method, $param);
            if ($result) {
                $total += $result["count"];
                for ($i = 0; $i < sizeof($result["items"]); $i++) {
                    $group_list[] = $result["items"][$i];
                }
                if (($total >= $result["total"]) || ($result["count"] == 0)) {
                    $res = false;
                } else {
                    $page++;
                }
            }
        }
        return $group_list;
    }


//Поиск лида/лидов
    public function searchCustomer($param, $branch = null)
    {
        if ($branch) {
            $method = $branch . "/customer/index";
        } else {
            $method = $this->config["global_branch"] . "/customer/index";
        }
        if (isset($param["is_study"])) {
            return $this->postRequest($method, $param);
        } else {
            $param["is_study"] = 1;
            $result = $this->postRequest($method, $param);
            if (!empty($result["items"])) {
                return $result["items"];
            } else {
                $param["is_study"] = 0;
                $result = $this->postRequest($method, $param);
                if (!empty($result["items"])) {
                    return $result["items"];
                } else {
                    return null;
                }
            }
        }

    }


//Создвние источников лидов
    public function leadSourceCreate($branch, $data)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lead-source/create';
        $result = $this->CurlPost($url, $data);
        return $result;
    }

//Список статусов обучение
    public function statusList($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lead-status/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

//Обновление лида
    public function UpdateStatus($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lead-status/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }


// Список источников
    public function GetSource($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lead-source/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

// Список групп
    public function GetGroup($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/group/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }


// Список статусов обучения
    public function ListStudyStatus($branch, $page)
    {
        if (!empty($page)) {
            $param = [
                'page' => $page
            ];
        } else {
            $param = NULL;
        }
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/study-status/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }


//Список участников групп
    public function GetCustomerCGI($branch, $cid)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/cgi/customer?customer_id=' . $cid;
        $result = $this->CurlPost($url, '');
        return $result;
    }

    public function RemoveCGI($branch, $id)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/cgi/delete?id=' . $id;
        $result = $this->CurlPost($url, '');
        return $result;
    }

// Список групп
    public function RemoveGroup($branch, $id)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/group/delete?id=' . $id;
        $result = $this->CurlPost($url, '');
        return $result;
    }

//Список участников групп
    public function GetCGI_byID($branch, $id)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/cgi/index';
        $result = $this->CurlPost($url, ['id' => $id]);
        return $result;
    }

// Список транзакций
    public function ListPayments($branch, $page)
    {
        if (!empty($page)) {
            $param = [
                'page' => $page
            ];
        } else {
            $param = NULL;
        }
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/pay/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

    public function UpdateLeadSource($branch, $id, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lead-source/update?id=' . $id;
        $result = $this->CurlPost($url, $param);
        return $result;
    }

    public function UpdateLesson($branch, $id, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lesson/update?id=' . $id;
        $result = $this->CurlPost($url, $param);
        return $result;
    }


// Список транзакций
    public function GetPayment($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/pay/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

// Список групп
    public function ListLessonType($branch)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lesson-type/index';
        $result = $this->CurlPost($url, '');
        return $result;
    }


    public function GetRegularLesson($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/regular-lesson/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

// Список абонементів
    public function GetTariff($branch, $page)
    {
        if (!empty($page)) {
            $param = [
                'page' => $page
            ];
        } else {
            $param = NULL;
        }
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/tariff/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

// Список абонементів
    public function GetCustomerTariff($branch, $cid)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/customer-tariff/index?customer_id=' . $cid;
        $result = $this->CurlPost($url, '');
        return $result;
    }

// Список абонементів
    public function GetDiscount($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/discount/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

// Список причин отказа
    public function GetLeadReject($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/lead-reject/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

// Список аудиторий
    public function GetRoom($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/room/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

// Список групп
    public function GetTest($branch, $param)
    {
        $url = 'https://' . $this->domain . '/v2api/' . $branch . '/level/index';
        $result = $this->CurlPost($url, $param);
        return $result;
    }

}
