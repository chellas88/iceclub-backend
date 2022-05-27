<?php

namespace App\Http\Controllers;

use App\AdminkaLibs\AlfaCRM;
use App\Models\AlfaGroups;
use App\Models\AlfaLevels;
use App\Models\AmoLogs;
use App\Models\AmoAuth;
use App\Models\LeadContacts;
use App\Models\LeadLessons;
use Illuminate\Http\Request;
use Adminka\AmoCRM\AmoCRM;
use Adminka\AmoCRM\AmoOAuth;

class WidgetController extends Controller
{
//    private $amo;
//    private $alfa;
//
//    public function __construct()
//    {
//
//        $alfa_conf = config("ice.alfa");
//        $this->alfa = new AlfaCRM($alfa_conf, "WidgetController");
//        $amo_app = new AmoAuth();
//        $api_data = $amo_app->getApiData(1);
//        $this->amo = new AmoCRM($api_data);
//        $this->amo->setExternalUpdateToken(function ($token_data) {
//            $this->saveToken($token_data);
//        });
//    }

    public function dev()
    {
        //dd($this->getCfs());


        //dd($this->getGroups());

        //dd($this->getLessons(14));
//        dd($this->amo->getCustomFields('contacts'));
//        $lead_cfs[] = $this->amo->setCustomField(487683, '1');
//        $lead_data = [
//            "updated_by" => 0,
//            "custom_fields_values" => $lead_cfs
//        ];
//        $amo_result = $this->amo->updateLead($lead_data, 14889367);
//        dd($amo_result);

        $data = [
//            'lead_id' => 15614837,
            'lead_id' => 15897917,
            'lesson_id' => 58,
            'group_id' => 15,
            'less_date' => '2021-05-05',
            'subject_id' => 645,
            'teacher_id' => 12
        ];

        //dd(time());
        $post = '{"event":"send_alfa","lead_id":"16702957","target":"https:\/\/iceclub.dev.adminka.pro\/amo\/widget"}';
        $json = json_decode($post, true);

        //dd($json);
        dd($this->addToAlfa($json));
        dd($this->addGroup(991, $json));
        dd($this->addGroup(982, $data));

        //$this->addToAlfa($data);
        dd(time());
    }

    private function getCfsEnums()
    {
        $cfs = $this->amo->getCustomFields("leads");
        $teacher_list = null;
        $subject_list = null;
        foreach ($cfs as $cf) {
            if ($cf["id"] == 137571) { // Преподаватель
                //dd($cf);
                foreach ($cf["enums"] as $enum) {
                    $teacher_list[] = $enum["value"];
                }
            }
            if ($cf["id"] == 292697) { // Курс
                //dd($cf);
                foreach ($cf["enums"] as $enum) {
                    $subject_list[] = $enum["value"];
                }
            }
        }
        return [
            "teachers" => $teacher_list,
            "subjects" => $subject_list
        ];
    }

    private function getTeacherEnum($teacher_list, $teacher_name)
    {
        $alfa_teacher = explode(" ", $teacher_name);
        foreach ($teacher_list as $teacher) {
            $sim = null;
            $amo_teacher = explode(" ", $teacher);
            similar_text(strtolower($amo_teacher[0]), strtolower($alfa_teacher[0]), $sim);
            if ($sim > 95) {
                return $teacher;
            }
        }
        return null;
    }

    private function getSubjectEnum($subject_list, $alfa_subject)
    {
        foreach ($subject_list as $subject) {
            $sim = null;
            similar_text(strtolower($subject), strtolower($alfa_subject), $sim);
            //print_r($subject . " - " . $sim . "<br/>");
            if ($sim > 90) {
                return $subject;
            }
        }
        return null;
    }

    public function info()
    {
        //dd($this->amo->getCustomFields("contacts"));
        dd($this->amo->getPipelines());
    }

    public function widget()
    {
        $json = $_POST;
        //$post =file_get_contents("php://input");
        //$post = '{"event":"save","lesson_id":"1","lead_id":"15897917","group_id":"26","subject_id":"4","subject":"\u0422\u0420\u0418\u0417","teacher_id":"6","teacher":"\u041a\u0443\u0434\u0440\u044f\u0448\u043e\u0432\u0430 \u0410\u043b\u0435\u043a\u0441\u0430\u043d\u0434\u0440\u0430","date":"03.05.2021","time":"1620057600","target":"https:\/\/iceclub.dev.adminka.pro\/amo\/widget"}';
        //$json = json_decode($post, true);
        $log_id = $this->addAmoLog("widget", $json);
        if (isset($json["event"])) {
            switch ($json["event"]) {
                case "load":
                    $db_groups = new AlfaGroups();
                    $result = [
                        "teachers" => $this->getTeachers(),
                        "subjects" => $this->getSubjects(),
                        "groups" => $db_groups->get()->toArray(),
                        "ages" => $db_groups->listAges(),
                        "levels" => AlfaLevels::where("is_active", 1)->get()->toArray()
                    ];
                    return json_encode($result);

                case "get_lessons":
                    $lessons = $this->getLessons($json["group_id"]);
                    return json_encode($lessons);
                case "save":
                    $alfa_res = $this->addToAlfa($json);
                    if ($alfa_res["status"] == "success") {
                        $result = $this->addGroup($alfa_res["alfa_id"], $json); // додаємо клієнта в групу
                        return json_encode($result);
                    } else {
                        return json_encode($alfa_res);
                    }
                case "send_alfa":
                    $alfa_res = $this->addToAlfa($json);
                    if ($alfa_res["status"] == "success") {
                        $update_param = [
                            'lead_id' => $json['lead_id'],
                            'status' => 'individual'
                        ];
                        LeadLessons::updateOrInsert(["alfa_id" => $alfa_res["alfa_id"]], $update_param);
                        $result = [
                            "result" => true,
                            "alfa_id" => $alfa_res["alfa_id"]
                        ];
                        return json_encode($result);
                    } else {
                        return json_encode($alfa_res);
                    }
                default:
                    $error = [
                        "result" => false,
                        "error_message" => "Not supported event"
                    ];
                    return json_encode($error);
            }
        } else {
            $res = [
                "status" => "error",
                "log_id" => $log_id
            ];
            return json_encode($res);
        }
    }

    public function getGroups()
    {
        print_r("Start group sync\n");
        $group_list = null;
        $groups = $this->alfa->listGroups();
        if (!empty($groups)) {
            $db_groups = new AlfaGroups();
            foreach ($groups as $group) {
                $customers = null;
                $cgi_list = $this->alfa->getCGI($group["id"]);
                if (!empty($cgi_list["items"])) {
                    foreach ($cgi_list["items"] as $cgi) {

                        if (strtotime($cgi["e_date"]) > time()) {
                            $customers++;
                        }
                    }
                }
                $group_data = [
                    "name" => $group["name"],
                    "level_id" => $group["level_id"],
                    "status_id" => $group["status_id"],
                    "limit" => $group["limit"],
                    "customers" => $customers,
                    "note" => $group["name"],
                    "b_date" => date("Y-m-d", strtotime($group["b_date"])),
                    "e_date" => date("Y-m-d", strtotime($group["e_date"])),
                    "age" => $group["custom_age_"],
                ];
                $db_groups->updateOrInsert(["id" => $group["id"]], $group_data);
                $group_list[] = $group_data;
            }
        }
        print_r("Finish group sync\n");
        return $group_list;
    }

    public function getTeachers()
    {
        $teachers = $this->alfa->getTeacher('');
        foreach ($teachers as $teacher) {
            $list[] = [
                'id' => $teacher['id'],
                'name' => $teacher['name']
            ];
        }
        return ($list);
    }

    public function getSubjects()
    {
        $lessons = $this->alfa->listSubject();
        foreach ($lessons as $lesson) {
            $list[] = [
                'id' => $lesson['id'],
                'name' => $lesson['name']
            ];
        }
        return ($list);
    }

    public function getLessons($group_id)
    {
        $lesson_list = null;
        $calendar_params = [
            "id" => $group_id,
            "date1" => date("Y-m-d", time()),
            "date2" => date("Y-m-d", strtotime("+30day"))
        ];
        $lessons = $this->alfa->getGroupCalendar($calendar_params);
        if (!empty($lessons)) {
            foreach ($lessons as $lesson) {
                if (strtotime($lesson["start"]) > time()) {
                    $tmp = [
                        "id" => $lesson["id"],
                        "teacher" => $lesson["teacher"],
                        "teacher_id" => $lesson["teacher_id"],
                        "subject" => $lesson["subject"],
                        "subject_id" => $lesson["subject_id"],
                        "start" => date("H:i", strtotime($lesson["start"])),
                        "date" => date("d.m.Y", strtotime($lesson["date"])),
                        "time" => strtotime($lesson["start"]),
                        "customers" => null,
                        "title" => $this->getDay($lesson["start"]) . date(", d.m.Y, H:i", strtotime($lesson["start"])) . ", " . $lesson["subject"] . ", " . $lesson["teacher"]
                    ];
                    if (!empty($lesson["customers"])) {
                        $tmp["customers"] = sizeof($lesson["customers"]);
                    }
                    $lesson_list[] = $tmp;
                }
            }
        }
        return $lesson_list;
    }

    private function getDay($date)
    {
        $day = date("w", strtotime($date));
        switch ($day) {
            case 0:
                return "Вс";
            case 1:
                return "Пн";
            case 2:
                return "Вт";
            case 3:
                return "Ср";
            case 4:
                return "Чт";
            case 5:
                return "Пт";
            case 6:
                return "Сб";
        }
    }

    public
    function getLessonsOld()
    {
        $group_list = $this->alfa->listGroups();
        dd($group_list);
        $calendar_list = [];
        foreach ($group_list as $group) {
            $calendar_params = [
                "id" => $group["id"],
                "date1" => date("Y-m-d", time()),
                "date2" => date("Y-m-d", strtotime("+7day"))
            ];
            $group_calendar = $this->alfa->getGroupCalendar($calendar_params);
            if (!empty($group_calendar)) {
                foreach ($group_calendar as $lesson) {
                    $calendar_list[] = [
                        "group_id" => $group["id"],
                        "limit" => $group["limit"],
                        "lesson" => $lesson
                    ];
                }

            }
        }
//        AmoLogs::insert([
//            'data' => json_encode($calendar_list),
//            'source' => 'CalendarList',
//            'created_at' => now()
//        ]);
        AmoLogs::updateOrInsert(
            ["id" => 163],
            [
                'data' => json_encode($calendar_list),
                'source' => 'CalendarList',
                'created_at' => now()
            ]);
//        return $calendar_list;
        dd($calendar_list);

    }

    public
    function addToAlfa($data)
    {
        $err = [];

        $alfa_id = null;
        $amo_contact_id = null;
        $alfa_url = 'https://iceqclub.s20.online/company/1/lead/view?id=';
        $lead_data = null;
        $res = null;
        $lead_cfs = null;
        $res['note'] = "";
        $res['amo_lead_id'] = $data['lead_id'];
        $res['web'][] = 'https://kidsprojectsruiceqclub.amocrm.ru/leads/detail/' . $data['lead_id'];
        $lead = $this->amo->getLeadFromID($data['lead_id']);
        if (!empty($lead["_embedded"]["contacts"])) {
            foreach ($lead["_embedded"]["contacts"] as $contact) {
                $lead_data['contacts'][] = [
                    "is_main" => $contact["is_main"],
                    "data" => $this->amo->getContactFromID($contact["id"])
                ];
            }
        }
        $lead_data['lead'] = $lead;
        if (!empty($lead_data['lead']['custom_fields_values'])) {
            foreach ($lead_data['lead']['custom_fields_values'] as $field) {
                switch ($field['field_id']) {
                    case 137193: // поле Источник
                        $res['source'] = $field['values'][0]['value'];
                        $res['note'] .= "Источник: " . $field['values'][0]['value'] . "\n ";
                        break;
                    case 137047: // Дата пробного урока
                        $res['note'] .= "Дата пробного урока: " . date("H:i, d-m-Y", $field['values'][0]['value']) . "\n ";
                        break;
                    case 292697: // Курс
                        $res['note'] .= "Курс: " . $field['values'][0]['value'] . "\n ";
                        break;
                    case 137571: // Преподаватель
                        $res['source'] = $field['values'][0]['value'];
                        $res['note'] .= "Преподаватель: " . $field['values'][0]['value'] . "\n ";
                        break;
                    case 137035: // Тип абонемента
                        $res['note'] .= "Тип абонемента: " . $field['values'][0]['value'] . "\n ";
                        break;
                    case 137161: // Комментарий
                        $res['note'] .= "Комментарий: " . $field['values'][0]['value'] . "\n ";
                        break;
                    case 310733: // Метод оплаты
                        $res['note'] .= "Метод оплаты: " . $field['values'][0]['value'] . "\n ";
                        break;
                    case 310735: // Кому оплатили
                        $res['note'] .= "Кому оплатили: " . $field['values'][0]['value'] . "\n ";
                        break;
                    case 487683: // перевірка наявності альфа_ід в сделці
                        $alfa = LeadContacts::where('lead_id', '=', $data['lead_id'])->first(); // шукаємо сделку в бд
                        if (isset($alfa['alfa_id'])) { // сделка є в БД і є альфа_ід
                            $alfa_id = $alfa["alfa_id"];
                            if ($alfa['alfa_id'] != $field['values'][0]['value']) { // альфа_ід в базі не такий, як в амо - оновлюємо дані в сделці
                                $lead_cfs[] = $this->amo->setCustomField(487683, $alfa['alfa_id']);
                                $lead_cfs[] = $this->amo->setCustomField(487685, $alfa_url . '' . $alfa['alfa_id']);
                                $lead_data = [
                                    "updated_by" => 0,
                                    "custom_fields_values" => $lead_cfs
                                ];
                                $this->amo->updateLead($lead_data, $data['lead_id']);
                            }
                        }
                        break;
                }
            }
        }

        if (empty($alfa_id)) { // клієнта в альфі нема, підготовлюємо дані і створюємо його
            foreach ($lead_data['contacts'] as $contact) {
                $amo_contact_id = $contact["data"]["id"];
                if (!empty ($contact["data"]["custom_fields_values"])) { // кастомні поля заповнені
                    foreach ($contact["data"]["custom_fields_values"] as $custom) {
                        $contact_cfs = null;
                        switch ($custom['field_id']) {
                            case 487669: // альфа_ід
                                if (!$contact["is_main"]) { // шукаємо тільки в контакті дитини
                                    $alfa = LeadContacts::where('child_contact_id', '=', $contact["data"]["id"])->first();
                                    if (isset($alfa['alfa_id'])) {
                                        $alfa_id = $alfa["alfa_id"];
                                        if ($alfa['alfa_id'] != $custom['values'][0]['value']) {
                                            $contact_cfs[] = $this->amo->setCustomField(487669, $alfa['alfa_id']);
                                            $contact_cfs[] = $this->amo->setCustomField(487673, $alfa_url . '' . $alfa['alfa_id']);
                                            $contact_data = [
                                                "updated_by" => 0,
                                                "custom_fields_values" => $contact_cfs
                                            ];
                                            $this->amo->updateContact($contact_data, $contact["data"]["id"]);
                                        }
                                    }
                                }
                                break;
                            case 490129: // роль
                                if ($custom['values'][0]['value'] == 'Родитель') {
                                    $res['parent'] = $contact["data"]['name'];
                                    $res['parent_id'] = $contact["data"]['id'];
                                    $is_parent = 1;
                                } else {
                                    $res['customer'] = $contact["data"]['name'];
                                    $res['customer_id'] = $contact["data"]['id'];
                                    $res['web'][] = 'https://kidsprojectsruiceqclub.amocrm.ru/contacts/detail/' . $contact["data"]['id'];
                                    $res['amo_contact_id'] = $contact["data"]['id'];
                                }
                                break;
                            case 136649:
                                foreach ($custom['values'] as $phone) {
                                    $res['phone'][] = $phone['value'];
                                }
                                break;
                            case 136651:
                                foreach ($custom['values'] as $email) {
                                    $res['email'][] = $email['value'];
                                }
                                break;
                            case 142027:
                                if (!$contact["is_main"]) {
                                    $res['birth'] = $custom['values'][0]['value'];
                                }
                                break;
                            case 142029:
                                if (!$contact["is_main"]) {
                                    $res['note'] .= "Возраст ребенка: " . $custom['values'][0]['value'] . " \n";
                                    $res['age'] = $custom['values'][0]['value'];
                                }
                                break;
                            case 137129:
                                if (!$contact["is_main"]) {
                                    $res['note'] .= "Страна, город: " . $custom['values'][0]['value'] . " \n";
                                }
                                break;
                        }
                    }
                }

                if ($contact["is_main"]) {
                    $res['parent'] = $contact["data"]["name"];
                    $res['parent_id'] = $contact["data"]["id"];
                } else {
                    $res['customer'] = $contact["data"]["name"];
                    $res['customer_id'] = $contact["data"]["id"];
                    $res['web'][] = 'https://kidsprojectsruiceqclub.amocrm.ru/contacts/detail/' . $contact["data"]["id"];
                    $res['amo_contact_id'] = $contact["data"]["id"];
                }
            }
            if (!isset($res['customer'])) {
                $err = [
                    'status' => 'error',
                    'desc' => 'Добавьте контакт ребенка'
                ];
                return $err;
            } else if (!isset($res['parent'])) {
                $err = [
                    'status' => 'error',
                    'desc' => 'Добавьте контакт родителя'
                ];
                return $err;
            }
            if (empty($alfa_id)) {
                $alfa_data = [
                    'branch_ids' => [1],
                    'name' => $res['customer'],
                    'is_study' => 0,
                    'lead_status_id' => 2,
                    'legal_type' => 1,
                    'legal_name' => $res['parent'],
                    'web' => $res['web'],
                    'custom_amo_lead_id' => $res['amo_lead_id'],
                    'custom_amo_contact_id' => $res['amo_contact_id'],
                ];
                if (isset($res["phone"])) {
                    $alfa_data['phone'] = $res['phone'];
                }
                if (isset($res["email"])) {
                    $alfa_data['email'] = $res['email'];
                }
                if (isset($res["age"])) {
                    $alfa_data['custom_vozrastrebenka'] = $res['age'];
                }
                if (isset($res["note"])) {
                    $alfa_data['note'] = $res['note'];
                }

                $result = $this->alfa->leadCreate($alfa_data);
                if ($result['success'] == false) {
                    $err = [
                        'status' => 'error',
                        'desc' => $result['errors']
                    ];
                    return $err;
                } else {
                    $alfa_id = $result['model']['id'];
                    $alfa_url = 'https://iceqclub.s20.online/company/1/lead/view?id=' . $alfa_id;
                    $update_param = [ // записуємо в БД
                        'lead_id' => $data['lead_id'],
                        'parent_contact_id' => $res['parent_id'],
                        'child_contact_id' => $res['customer_id']
                    ];
                    LeadContacts::updateOrInsert(["alfa_id" => $alfa_id], $update_param);
                    $lead_cfs = null;
                    $lead_cfs[] = $this->amo->setCustomField(487683, $alfa_id);
                    $lead_cfs[] = $this->amo->setCustomField(487685, $alfa_url);
                    $lead_data = [
                        "updated_by" => 0,
                        "custom_fields_values" => $lead_cfs
                    ];
                    $lead_result = $this->amo->updateLead($lead_data, $data['lead_id']);
                    if (!isset($lead_result['id'])) {
                        return [
                            'status' => 'error',
                            'desc' => 'Не удалось обновить поля в сделке Амо'
                        ];
                    }

                    $contact_cfs = null;
                    $contact_cfs[] = $this->amo->setCustomField(487669, $alfa_id);
                    $contact_cfs[] = $this->amo->setCustomField(487673, $alfa_url);
                    $contact_data = [
                        "updated_by" => 0,
                        "custom_fields_values" => $contact_cfs
                    ];
                    $contact_result = $this->amo->updateContact($contact_data, $amo_contact_id);
                    if (!isset($contact_result['id'])) {
                        return [
                            'status' => 'error',
                            'desc' => 'Не удалось обновить поля в контакте Амо'
                        ];
                    }
                }
            }
        }
        return [
            "status" => "success",
            "alfa_id" => $alfa_id
        ];
    }

    public
    function addGroup($alfa_id, $data)
    {
        $log_id = $this->addAmoLog("addGroup", ["alfa_id" => $alfa_id, "data" => $data]);
        $b_date = date("d.m.Y", $data['time'] - 86400);
        $e_date = date("d.m.Y", $data['time']);
        $cgi_param = [
            "customer_id" => $alfa_id,
            //"b_date" => date("d.m.Y", time()),
            "b_date" => $b_date,
            "e_date" => $e_date
        ];
        //dd($cgi_param);
        $add_lessons_result = $this->alfa->createCGI($data['group_id'], $cgi_param);
        if ($add_lessons_result == false) {
            $err = [
                'status' => 'error',
                'desc' => 'Урок не добавлен'
            ];
            return $err;
        }

        $update_param = [
            'lead_id' => $data['lead_id'],
            'subject_id' => $data['subject_id'],
            'teacher_id' => $data['teacher_id'],
            'group_id' => $data['group_id'],
            'b_date' => date("Y-m-d", strtotime($b_date)),
            'e_date' => date("Y-m-d", $data['time']),
            'status' => 'new'

        ];
        LeadLessons::updateOrInsert(["alfa_id" => $alfa_id], $update_param);

        $aa_conf = config("ice.aa");
        $cfs_enums = $this->getCfsEnums();
        $lead_cfs = null;
        $lead_cfs[] = $this->amo->setCustomField(137047, (int)$data["time"]);
        $lead_cfs[] = $this->amo->setCustomField(137035, "Пробный урок");
        $lead_cfs[] = $this->amo->setCustomField(137571, $this->getTeacherEnum($cfs_enums["teachers"], $data["teacher"])); // Преподаватель
        $lead_cfs[] = $this->amo->setCustomField(292697, $this->getSubjectEnum($cfs_enums["subjects"], $data["subject"])); // Курс

        $lead_data = [
            "updated_by" => 0,
            "status_id" => $aa_conf["trial"],
            "custom_fields_values" => $lead_cfs
        ];
        $upd_res = $this->amo->updateLead($lead_data, $data["lead_id"]);
        $this->addAmoLog("update_lead_status", $upd_res);

        return [
            "status" => "success"
        ];
    }

    public
    function amoAuth()
    {
        $amo_apps = new AmoAuth();
        $auth_data = $amo_apps->getAuthData(1);
        $amo_auth = new AmoOAuth($auth_data);
        $url = $amo_auth->getRedirectUrl(1);
//        dd($url);
        echo '<a href="' . $url . '">Войти в Амо</a>';

    }


    public
    function getAmoToken(Request $request)
    {
        if (isset($request['code'])) {
            $amo_apps = new AmoAuth();
            $auth_data = $amo_apps->getAuthData($request['state']);
            $amo_auth = new AmoOAuth($auth_data);
            $amo_auth->setExternalSaveToken(function ($token_data) {
                $this->saveToken($token_data);
            });
            $amo_auth->getAccessToken($request["code"]);
        }
    }


//    public function saveToken($token_data)
//    {
//        $amo_auth = new AmoAuth();
//
//        if ($amo_auth->where('id', $token_data['auth_id'])->exists()) {
//            $token = $amo_auth->where('id', '=', $token_data['auth_id'])->get()[0];
//            if(empty($token['access_token'])) {
//                $amo_auth->where('id', '=', $token_data['auth_id'])->update(['refresh_token' => $token_data['refresh_token'], 'access_token' => $token_data['access_token'], 'expires' => $token_data['expires_in']]);
//            }
//        } else {
//            dd('no params');
//        }
//
//
//    }

//    public
//    function saveToken($token_data)
//    {
//        $amo_app = new AmoAuth();
//        $update_param = [
//            "access_token" => $token_data["access_token"],
//            "refresh_token" => $token_data["refresh_token"]
//        ];
//        if (isset($token_data["expires_in"])) {
//            $update_param["expires"] = date("Y-m-d H:i:s", time() + $token_data["expires_in"]);
//        } elseif (isset($token_data["expired_in"])) {
//            $update_param["expires"] = date("Y-m-d H:i:s", $token_data["expired_in"]);
//        }
//        $amo_app->where("id", $token_data["auth_id"])->update($update_param);
//    }


}
