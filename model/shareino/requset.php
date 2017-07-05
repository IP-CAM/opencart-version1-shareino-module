<?php

class ModelShareinoRequset extends Model
{

    const SHAREINO_API_URL = "https://shareino.ir/api/v1/public/";
    const Version = "1.2.7";

    public function sendRequset($url, $body, $method)
    {
        // Get api token from server
        $this->load->model('setting/setting');
        $shareinoSetting = $this->model_setting_setting->getSetting("shareino");

        if ($shareinoSetting) {
            $SHAREINO_API_TOKEN = $shareinoSetting["shareino_api_token"];

            // Init curl
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            // SSL check
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

            // Generate url and set method in url
            $url = self::SHAREINO_API_URL . $url;
            curl_setopt($curl, CURLOPT_URL, $url);

            // Set method in curl
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

            // Set Body if its exist
            if ($body != null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
            }

            // Get result
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                "Authorization:Bearer $SHAREINO_API_TOKEN",
                "User-Agent: OpenCart_module_" . self::Version
                )
            );

            // Get result
            $result = curl_exec($curl);

            // Get Header Response header
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            switch ($httpcode) {
                case 200:
                    return json_decode($result, true);
                case 401:
                    return array('status' => false, 'message' => 'خطا! توکن وارد شده معتبر نمیباشد.');
                case 403:
                    return array('status' => false, 'message' => 'خطا! دسترسی  مجاز نمیباشد.');
                case 408:
                    return array('status' => false, 'message' => 'خطا! درخواست منقضی شد.');
                case 429:
                    return array('status' => false, 'code' => 429, 'message' => 'فرایند ارسال محصولات به طول می انجامد لطفا صبور باشید.');
                default:
                    return array('status' => false, 'message' => $httpcode);
            }
        }

        return array('status' => false, 'message' => 'ابتدا توکن را از سرور شرینو دریافت کنید');
    }

    public function deleteProducts($ids, $all = false)
    {
        $body = array();
        $url = "products";
        // Chek if want to delete All product
        if ($all) {
            $body = array("type" => "all");
        } else {
            // check if want to delete multiple
            if (is_array($ids)) {
                $body = array("type" => "selected", "code" => $ids);
            } // if want to delete once
            else {
                $url .= "/$ids";
            }
        }
        $result = $this->sendRequset($url, json_encode($body), "DELETE");
        return $result;
    }

}
