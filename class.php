<?php

if (!class_exists('AlikassaApi')) {
    class AlikassaApi
    {
        private $baseURL = 'https://api.alikassa.com/v1/site';
        private $merchantUuid = '';
        private $secret_key = '';
        private $m_id = '';

        public $lastRequest;
        public $lastResponse;

        /**
         * AlikassaApi constructor.
         *
         * @param $merchantUuid
         * @param $secret_key
         * @param $m_id
         */
        public function __construct($merchantUuid, $secret_key, $m_id)
        {
            $this->merchantUuid = trim($merchantUuid);
            $this->secret_key = trim($secret_key);
            $this->m_id = trim($m_id);
        }

        /**
         * Deposit
         * @url https://alikassa.com/site/api-doc#section/2.-Deposit-API
         * @param $data
         * @return array|mixed
         * @throws Exception
         */
        public function deposit($data)
        {
            $params = [
                'merchantUuid' => $this->merchantUuid,
                'paySystem' => 'Card',
                'payWayVia' => 'Card',
                'commissionType' => 'customer',
//                'lifetime' => '',
            ];
            $params = array_merge($params, $data);

            $result = $this->request('/deposit', 'POST', $params);

            return isset($result['return']) ? $result['return'] : [];
        }

        /**
         * @param $api_name
         * @param $method
         * @param array $params
         *
         * @return array|bool|mixed|object
         * @throws Exception
         */
        public function request($api_name, $method, $params = [])
        {
            $url = $this->baseURL . $api_name;

            $ch = curl_init();

            if ($method === "POST") {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

                $this->lastRequest = $params;
            } else {
                $get_params = http_build_query($params, '', '&');

                $this->lastRequest = $get_params;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode(
                    $this->merchantUuid . ':' . self::sign($params, $this->secret_key)
                ),
                'Content-Type: application/json'
            ]);

            curl_setopt($ch, CURLOPT_HEADER, 0);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_setopt($ch,CURLOPT_USERAGENT, 'AliKassa API');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $result = json_decode(curl_exec($ch), true);

            $this->lastResponse = $result;


            if (!$result || empty($result)) {
                $err = 'Empty JSON response.';

                throw new \Exception($err);
            }

            if (isset($result['success']) && $result['success'] === 'false') {
                $err = isset($result['error']) ? $result['error'] : 'Unknown API error.';

                throw new \Exception($err);
            }

            if ($result['success'] === 'true') {
                return $result;
            }

            return false;
        }

        /**
         * @param array $dataSet
         * @param string $key
         *
         * @return string
         */
        public static function sign(array $dataSet, $key)
        {
            if (isset($dataSet['sign'])) {
                unset($dataSet['sign']);
            }

            ksort($dataSet, SORT_STRING);
            $dataSet[] = $key;

            $signString = implode(':', $dataSet);

            $signString = hash('md5', $signString, true);

            return base64_encode($signString);
        }


        public static function wh_log($log_title, $log_msg)
        {
            $log_filename = getcwd() . '/wp-content/alikassa.log';
            if (!file_exists($log_filename)) {
                $handle = fopen($log_filename, 'wb');
                fclose($handle);
            }
            $log = $log_title . ': ' . $log_msg . ' - date: ' . date('F j, Y, g:i a') . PHP_EOL .
                '-------------------------' . PHP_EOL;
            file_put_contents($log_filename, $log, FILE_APPEND);
        }
    }
}