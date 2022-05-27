<?php

namespace App\Http\Controllers;

use App\AdminkaLibs\AlfaCRM;
use App\Jobs\SyncAlfaGroups;
use App\Models\LeadContacts;
use App\Models\LeadLessons;
use Illuminate\Http\Request;

class AmoAlfaController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function dev()
    {
        dd(time());
        //$post = file_get_contents("php://input");
        $post = '{"event":"send_alfa","lead_id":"16702957","target":"https:\/\/iceclub.dev.adminka.pro\/amo\/widget"}';
        $json = json_decode($post, true);
        //dd($json);

        if (!empty($json)) {
            $this->addAmoLog("alfa_hook", $json);
            if ($json["event"] == "create") {
                switch ($json["entity"]) {
                    case "LessonDetails":
                        $db_lessons = new LeadLessons();
                        $find_params = [
                            "alfa_id" => $json["fields_new"]["customer_id"],
                            "status" => "new"
                        ];
                        $db_lesson = $db_lessons->where($find_params)->first();
                        if (!empty($db_lesson)) {
                            $alfa_lesson = $this->alfa->getLesson($json["fields_new"]["lesson_id"]);
                            //if ($alfa_lesson["subject_id"] == $db_lesson["subject_id"]) {
                            $aa_config = config("ice.aa");
                            $update_params = [
                                "lesson_id" => $json["fields_new"]["lesson_id"],
                                "attend" => $json["fields_new"]["is_attend"],
                                "status" => "old"
                            ];
                            $db_lessons->where("id", $db_lesson["id"])->update($update_params);
                            if ($json["fields_new"]["is_attend"]) {
                                $lead_data = [
                                    "updated_by" => 0,
                                    "status_id" => $aa_config["attend"]
                                ];
                            } else {
                                $lead_data = [
                                    "updated_by" => 0,
                                    "status_id" => $aa_config["not_attend"]
                                ];
                            }
                            $amo_res = $this->amo->updateLead($lead_data, $db_lesson["lead_id"]);
                            $this->addAmoLog("alfa_attend", $amo_res);
                            //}
                        }
                        break;
                    case "Lesson":
                        if (isset($json["fields_new"]["customer_ids"])) {
                            foreach ($json["fields_new"]["customer_ids"] as $c_id) {
                                //dd($json);
                                $where = [
                                    ["alfa_id", $c_id],
                                    ["status", "individual"]
                                ];
                                $db_lesson = LeadLessons::where($where)->first();
                                if (!empty($db_lesson)) {
                                    $update_params = [
                                        "lesson_id" => $json["entity_id"],
                                        "subject_id" => $json["fields_new"]["subject_id"],
                                        "b_date" => $json["fields_new"]["lesson_date"],
                                        "e_date" => $json["fields_new"]["lesson_date"],
                                        "status" => "individual_trial"
                                    ];
                                    if (isset($json["fields_new"]["teacher_ids"])) {
                                        $update_params["teacher_id"] = $json["fields_new"]["teacher_ids"][0];
                                    }
                                    LeadLessons::where("id", $db_lesson["id"])->update($update_params);
                                }
                            }
                        }
                        break;
                    default:
                        SyncAlfaGroups::dispatch();
                        break;
                }
            } elseif ($json["event"] == "update") {
                switch ($json["entity"]) {
                    case 'Customer':
                        if ((isset($json["fields_old"]["lead_status_id"])) && (isset($json["fields_new"]["lead_status_id"]))) {
                            $alfa_customer = $this->alfa->getCustomerFromID($json["entity_id"]);
                            if ((!empty($alfa_customer)) && (!empty($alfa_customer["custom_amo_lead_id"]))) {
                                $amo_lead = $this->amo->getLeadFromID($alfa_customer["custom_amo_lead_id"]);
                                if (!empty($amo_lead)) {
                                    $statuses = config("ice.statuses");
                                    if (isset($statuses[$json["fields_new"]["lead_status_id"]])) {
                                        $lead_data = [
                                            "updated_by" => 0,
                                            "pipeline_id" => $amo_lead["pipeline_id"],
                                            "status_id" => $statuses[$json["fields_new"]["lead_status_id"]]
                                        ];
                                        $res = $this->amo->updateLead($lead_data, $alfa_customer["custom_amo_lead_id"]);
                                        $this->addAmoLog("update_status", $res);

                                    }
                                }
                            }
                        }
                        break;
                    case
                    "Lesson":
                        break;
                    default:
                        SyncAlfaGroups::dispatch();
                        break;
                }
            } elseif
            (isset($json["event"])) {
                SyncAlfaGroups::dispatch();
            }
        }
    }

    public function amoHook(Request $request)
    {
        $req = $request->post();
        if (!empty($req)) {
            $this->addAmoLog("amo_hook", $req);
            if ((isset($req["leads"])) && (isset($req["leads"]["status"])) && ($req["leads"]["status"][0]["modified_user_id"] != 0)) {
                $lead = $req["leads"]["status"][0];
                if (!empty($lead["custom_fields"])) {
                    $alfa_id = $this->getAlfaId($lead["custom_fields"], 487683);
                    if (!empty($alfa_id)) {
                        $aa_config = config("ice.amo_alfa");
                        $alfa_param = [
                            "id" => $alfa_id,
                            "lead_status_id" => $aa_config[$lead["status_id"]]
                        ];
                        $alfa_res = $this->alfa->leadUpdate($alfa_param);
                        $this->addAmoLog("alfa_status_update", $alfa_res);
                    }
                }
            } elseif ((isset($req["contacts"])) && (isset($req["contacts"]["update"])) && ($req["contacts"]["update"][0]["modified_user_id"] != 0)) {
                $contact = $req["contacts"]["update"][0];
                if (!empty($contact["custom_fields"])) {
                    $alfa_id = $this->getAlfaId($contact["custom_fields"], 487669);
                    if (empty($alfa_id)) {
                        $lead_contacts = LeadContacts::where("parent_contact_id", $contact["id"])->get();
                        if (!empty($lead_contacts)) {
                            foreach ($lead_contacts as $lc) {
                                $alfa_data = [
                                    "id" => $lc["alfa_id"],
                                    "branch_ids" => [1],
                                    "legal_name" => $contact["name"]
                                ];
                                $this->alfa->leadUpdate($alfa_data);
                            }
                        }
                    } else {
                        $alfa_data = [
                            "id" => $alfa_id,
                            "branch_ids" => [1],
                            "name" => $contact["name"]
                        ];
                        $this->alfa->leadUpdate($alfa_data);
                    }
                }
            }
        }

    }

    private function getAlfaId($cfs, $field_id)
    {
        foreach ($cfs as $cf) {
            if ($cf["id"] == $field_id) {
                return $cf["values"][0]["value"];
            }
        }
        return null;
    }

    private function getPhoneOrEmail($cfs, $field_id)
    {
        foreach ($cfs as $cf) {
            if ($cf["id"] == $field_id) {
                $values = null;
                foreach ($cf["values"] as $value) {
                    if ($cf["code"] == "PHONE") {
                        $values[] = preg_replace('/[^0-9]/', '', $value["value"]);
                    } else {
                        $values[] = $value["value"];
                    }
                }
                return $values;
            }
        }
        return null;
    }

    public
    function alfaHook()
    {
        $post = file_get_contents("php://input");
        $json = json_decode($post, true);

        if (!empty($json)) {
            $this->addAmoLog("alfa_hook", $json);
            if ($json["event"] == "create") {
                switch ($json["entity"]) {
                    case 'LessonDetails':
                        $db_lessons = new LeadLessons();
                        $find_params = [
                            "alfa_id" => $json["fields_new"]["customer_id"],
                            "status" => "new"
                        ];
                        $db_lesson = $db_lessons->where($find_params)->first();
                        if (!empty($db_lesson)) {
                            $alfa_lesson = $this->alfa->getLesson($json["fields_new"]["lesson_id"]);
                            //if ($alfa_lesson["subject_id"] == $db_lesson["subject_id"]) {
                            $aa_config = config("ice.aa");
                            $update_params = [
                                "lesson_id" => $json["fields_new"]["lesson_id"],
                                "attend" => $json["fields_new"]["is_attend"],
                                "status" => "old"
                            ];
                            $db_lessons->where("id", $db_lesson["id"])->update($update_params);
                            if ($json["fields_new"]["is_attend"]) {
                                $lead_data = [
                                    "updated_by" => 0,
                                    "status_id" => $aa_config["amo_attend"]
                                ];
                                $alfa_param = [
                                    "id" => $json["fields_new"]["customer_id"],
                                    "lead_status_id" => $aa_config["alfa_attend"]
                                ];
                            } else {
                                $lead_data = [
                                    "updated_by" => 0,
                                    "status_id" => $aa_config["amo_not_attend"]
                                ];
                                $alfa_param = [
                                    "id" => $json["fields_new"]["customer_id"],
                                    "lead_status_id" => $aa_config["alfa_not_attend"]
                                ];
                            }
                            $amo_res = $this->amo->updateLead($lead_data, $db_lesson["lead_id"]);
                            $this->addAmoLog("alfa_attend", $amo_res);

                            $alfa_res = $this->alfa->leadUpdate($alfa_param);
                            $this->addAmoLog("alfa_res", $alfa_res);
                            //}
                        }
                        break;
                    case "Lesson":
                        if (isset($json["fields_new"]["customer_ids"])) {
                            foreach ($json["fields_new"]["customer_ids"] as $c_id) {
                                //dd($json);
                                $where = [
                                    "alfa_id" => $c_id,
                                    "status" => "individual"
                                ];
                                $db_lesson = LeadLessons::where($where)->first();
                                if (!empty($db_lesson)) {
                                    $update_params = [
                                        "lesson_id" => $json["entity_id"],
                                        "subject_id" => $json["fields_new"]["subject_id"],
                                        "b_date" => $json["fields_new"]["lesson_date"],
                                        "e_date" => $json["fields_new"]["lesson_date"],
                                        "status" => "new"
                                    ];
                                    if (isset($json["fields_new"]["teacher_ids"])) {
                                        $update_params["teacher_id"] = $json["fields_new"]["teacher_ids"][0];
                                    }
                                    LeadLessons::where("id", $db_lesson["id"])->update($update_params);
                                }
                            }
                        }
                        break;
                    default:
                        SyncAlfaGroups::dispatch();
                        break;
                }
            } elseif ($json["event"] == "update") {
                switch ($json["entity"]) {
                    case 'Customer':
                        if ((isset($json["fields_old"]["lead_status_id"])) && (isset($json["fields_new"]["lead_status_id"]))) {
                            $alfa_customer = $this->alfa->getCustomerFromID($json["entity_id"]);
                            if ((!empty($alfa_customer)) && (!empty($alfa_customer["custom_amo_lead_id"]))) {
                                $amo_lead = $this->amo->getLeadFromID($alfa_customer["custom_amo_lead_id"]);
                                if (!empty($amo_lead)) {
                                    $statuses = config("ice.statuses");
                                    if (isset($statuses[$json["fields_new"]["lead_status_id"]])) {
                                        $lead_data = [
                                            "updated_by" => 0,
                                            "pipeline_id" => $amo_lead["pipeline_id"],
                                            "status_id" => $statuses[$json["fields_new"]["lead_status_id"]]
                                        ];
                                        $res = $this->amo->updateLead($lead_data, $alfa_customer["custom_amo_lead_id"]);
                                        $this->addAmoLog("update_status", $res);

                                    }
                                }
                            }
                        }
                        break;
                    case
                    "Lesson":
                        if (isset($json["fields_new"]["customer_ids"])) {
                            foreach ($json["fields_new"]["customer_ids"] as $c_id) {
                                //dd($json);
                                $where = [
                                    ["alfa_id", $c_id],
                                    ["status", "individual"]
                                ];
                                $db_lesson = LeadLessons::where($where)->first();
                                if (!empty($db_lesson)) {
                                    $update_params = [
                                        "lesson_id" => $json["entity_id"],
                                        "status" => "new"
                                    ];
                                    if (isset($json["fields_new"]["subject_id"])) {
                                        $update_params["subject_id"] = $json["fields_new"]["subject_id"];
                                    }
                                    if (isset($json["fields_new"]["lesson_date"])) {
                                        $update_params["b_date"] = $json["fields_new"]["lesson_date"];
                                        $update_params["e_date"] = $json["fields_new"]["lesson_date"];
                                    }
                                    if (isset($json["fields_new"]["teacher_ids"])) {
                                        $update_params["teacher_id"] = $json["fields_new"]["teacher_ids"][0];
                                    }
                                    LeadLessons::where("id", $db_lesson["id"])->update($update_params);
                                }
                            }
                        }
                        break;
                    default:
                        SyncAlfaGroups::dispatch();
                        break;
                }
            } elseif (isset($json["event"])) {
                SyncAlfaGroups::dispatch();
            }
        }
    }
}
