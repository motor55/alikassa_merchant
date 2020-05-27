<?php
if (!defined('ABSPATH')) {
    exit();
}

/*
title: [en_US:]Alikassa[:en_US][ru_RU:]Alikassa[:ru_RU]
description: [en_US:]Alikassa merchant[:en_US][ru_RU:]мерчант Alikassa[:ru_RU]
version: 2.1
author: max.dynko@gmail.com
 */

if(!class_exists('Merchant_Premiumbox')){ return; }

if (!class_exists('merchant_alikassa')) {
    class merchant_alikassa extends Merchant_Premiumbox
    {
        protected $messageResponse = 'OK';

        public function __construct($file, $title)
        {
            parent::__construct($file, $title, 0);

            $ids = $this->get_ids('merchants', $this->name);
            foreach ($ids as $id) {
                add_action('premium_merchant_' . $id . '_status' . hash_url($id), [$this, 'merchant_status']);
                add_action('premium_merchant_' . $id . '_return', [$this, 'merchant_return']);
            }
        }

        public function get_map()
        {
            $map = array(
                'merchantUuid' => array(
                    'title' => '[en_US:]merchantUuid key[:en_US][ru_RU:]Идентификатор сайта[:ru_RU]',
                    'view' => 'input',
                ),
                'secretKey' => array(
                    'title' => '[en_US:]Secret key[:en_US][ru_RU:]Секретный ключ[:ru_RU]',
                    'view' => 'input',
                ),
            );

            return $map;
        }

        public function settings_list()
        {
            $arrs = array();
            $arrs[] = array('merchantUuid', 'secretKey');

            return $arrs;
        }

        public function options($options, $data, $id, $place)
        {
            $options = pn_array_unset($options, 'personal_secret');
            $options = pn_array_unset($options, 'pagenote');
            $options = pn_array_unset($options, 'check_api');

            # "Способ оплаты"
            $opts = [
                '0' => 'Все методы',
                '1' => 'Qiwi',
                '2' => 'YandexMoney',
                '3' => 'Card',
            ];
            $options['methodpay'] = [
                'view' => 'select',
                'title' => __('Payment method', 'pn'),
                'options' => $opts,
                'default' => intval(is_isset($data, 'methodpay')),
                'name' => 'methodpay',
                'work' => 'int',
            ];

            # "Тип комиссии"
            $opts = [
                '1' => 'клиент',
                '2' => 'обменник',
//                '3' => 'распределенная комиссия меджу клиентом и обменником',
            ];
            $options['commission_type'] = [
                'view' => 'select',
                'title' => 'Коммисию оплачивает',
                'options' => $opts,
                'default' => intval(is_isset($data, 'commission_type')),
                'name' => 'commission_type',
                'work' => 'int',
            ];

            $options['private_line'] = array(
                'view' => 'line',
                'colspan' => 2,
            );

            $text = '
                <div><strong>URL оповещания:</strong> <a href="' . get_mlink($id . '_status' . hash_url($id)) . '" target="_blank" rel="noreferrer noopener">' . get_mlink($id . '_status' . hash_url($id)) . '</a></div>
                <div><strong>URL возвратов(fail, success):</strong> <a href="' . get_mlink($id . '_return') . '" target="_blank" rel="noreferrer noopener">' . get_mlink($id . '_return') . '</a></div>
	  		';

            $options['private_line'] = array(
                'view' => 'line',
                'colspan' => 2,
            );

            $options[] = array(
                'view' => 'textfield',
                'title' => '',
                'default' => $text,
            );

            $options['private_line'] = array(
                'view' => 'line',
                'colspan' => 2,
            );

            $options[] = array(
                'view' => 'textfield',
                'title' => 'Тело успешного ответа',
                'default' => 'OK',
            );
            $options[] = array(
                'view' => 'textfield',
                'title' => 'Код успешного ответа',
                'default' => '200',
            );

            $options[] = [
                'view' => 'user_func',
                'title' => '',
                'func' => 'func_option_mds',
            ];

            return $options;
        }


        public function bidform($temp, $m_id, $pay_sum, $item, $direction)
        {
            $script = get_mscript($m_id);
            if ($script and $script == $this->name) {

                $m_defin = $this->get_file_data($m_id);
                $m_data = get_merch_data($m_id);

                $currency = pn_strip_input($item->currency_code_give);
                $currency = str_replace('RUR','RUB',$currency);

                $item_id = $item->id;

                $email = $item->user_email;

                // cloudefare REMOTE_ADDR
                // $user_ip = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'];
                $user_ip = $item->user_ip;

                $user_agent = $_SERVER['HTTP_USER_AGENT'];

                $text_pay = pn_maxf(get_text_pay($m_id, $item, $pay_sum), 250);
                if (empty($text_pay)) {
                    $text_pay = "Заявка#$item_id";
                }

                if ( ! in_array($currency, ['RUB', 'USD', 'EUR'])) {
                    do_action('save_merchant_error', $this->name, $item_id . ' Оплата не удалась! Неверный код валюты: ' . $currency);

                    return $this->printErrorBlock(__('Error', 'pn'));
                }

                $return_url = get_mlink($m_id . '_return') . '?hashed=' . $item->hashed;

                $data = [
                    'merchantUuid' => is_deffin($m_defin, 'merchantUuid'),
                    'orderId' => $item_id,
                    'amount' => $pay_sum,
                    'currency' => $currency,
                    'customerEmail' => $email,
                    'desc' => $text_pay,
//                    'urlSuccess' => $return_url,
//                    'urlFail' => $return_url,

//                    'commissionType' => 'customer',

//                    'payWayOn' => 'Card',
//                    'payWayVia' => 'Card',
                ];

                // Способ оплаты
                $method_pay = intval(is_isset($m_data, 'methodpay'));
                switch ($method_pay) {
                    case 1: $data['payWayVia']  = 'Qiwi'; break;
                    case 2: $data['payWayVia']  = 'YandexMoney'; break;
                    case 3: $data['payWayVia']  = 'Card'; break;
                }

                // Тип комиссии
                $commission_type = intval(is_isset($m_data, 'commission_type'));
                switch ($commission_type) {
                    case 1: $data['commissionType']  = 'customer'; break;
                    case 2: $data['commissionType']  = 'merchant'; break;
//                    case 3: $data['commissionType']  = 'split'; break;

                    default: $data['commissionType'] = 'customer';
                }

                $data['sign'] = AlikassaApi::sign($data, is_deffin($m_defin, 'secretKey'));


                $params = '';
                foreach ($data as $key => $value) {
                    $params .= sprintf( "<input type='hidden' name='%s' value='%s'>\n", $key, $value );
                }

                $temp = '
				<form name="payment" method="post" action="https://sci.alikassa.com/payment" accept-charset="UTF-8">
				    ' . $params . '			

					<input type="submit" value="Payment">
				</form>			
				';

                return $temp;
            }

            return $temp;
        }

        public function merchant_status()
        {
            $m_id = key_for_url('_status');
            $m_defin = $this->get_file_data($m_id);
            $m_data = get_merch_data($m_id);

            do_action('merchant_logs', $this->name, '', $m_id, $m_defin, $m_data);

            $action = pn_strip_input( is_param_post('action') );
            $merchantUuid = pn_strip_input( is_param_post('merchantUuid') );
            $orderId = (int) is_param_post('orderId');
            $currency = pn_maxf( pn_strip_input(is_param_post('currency')) , 3);
            $amount = (float) is_param_post('amount');
            $payAmount = (float) is_param_post('payAmount');
            $desc = is_param_post('desc');
            $payWayVia = is_param_post('payWayVia');

            ### Дополнительные параметры
            $transactionId = is_param_post('id');
            $payStatus = is_param_post('payStatus');
            $paySystemAmount = is_param_post('paySystemAmount');
            $receiptNumber = is_param_post('receiptNumber');
            $sign = is_param_post('sign');

            if ($sign != AlikassaApi::sign($_POST, is_deffin($m_defin, 'secretKey'))) {
                $this->logs($orderId . ' invalid SIGN');
                exit($this->messageResponse);
            }


            if ($merchantUuid != is_deffin($m_defin, 'merchantUuid')) {
                $this->logs($orderId . ' invalid merchantUuid');
                exit($this->messageResponse);
            }
            if ($action !== 'deposit') {
                $this->logs($orderId . ' invalid ACTION callback');
                exit($this->messageResponse);
            }

            $data = get_data_merchant_for_id( (int) $orderId);

            $bid_err = $data['err'];
            $bid_m_script = $data['m_script'];
            $bid_m_id = $data['m_id'];


            if($bid_err > 0){
                $this->logs($orderId . ' The application does not exist or the wrong ID');
                exit($this->messageResponse);
            }

            if($bid_m_script and $bid_m_script != $this->name or !$bid_m_script){
                $this->logs($orderId . ' wrong script');
                exit($this->messageResponse);
            }

            if($bid_m_id and $m_id != $bid_m_id or !$bid_m_id){
                $this->logs($orderId . ' not a faithful merchant');
                exit($this->messageResponse);
            }

            if(check_trans_in($m_id, $transactionId, $orderId)){
                $this->logs($orderId . ' Error check trans in!');
                exit($this->messageResponse);
            }

            $in_sum = $amount;
            $in_sum = is_sum($in_sum,2);
            $bid_status = $data['status'];

            $pay_purse = is_pay_purse('', $m_data, $bid_m_id);

            $bid_currency = $data['currency'];
            $bid_currency = str_replace('RUR','RUB',$bid_currency);


            $bid_sum = is_sum($data['pay_sum'],2);
            $bid_corr_sum = apply_filters('merchant_bid_sum', $bid_sum, $bid_m_id);

            $invalid_ctype = intval(is_isset($m_data, 'invalid_ctype'));
            $invalid_minsum = intval(is_isset($m_data, 'invalid_minsum'));
            $invalid_maxsum = intval(is_isset($m_data, 'invalid_maxsum'));
            $invalid_check = intval(is_isset($m_data, 'check'));


            if (in_array($bid_status, ['new', 'coldpay'])) {
                if ($bid_currency == $currency or $invalid_ctype == 1) {
                    if ($in_sum >= $bid_corr_sum or $invalid_minsum == 1) {

                        //  'SUCCESS', 'CANCELED', 'FAIL', 'NO-FULL-PAID'
                        $payStatus = strtoupper($payStatus);

                        if (in_array($payStatus, ['CANCELED', 'FAIL'])) {
                            $newStatus = 'error';
                        } elseif($payStatus === 'NO-FULL-PAID') {
                            $newStatus = 'coldpay';
                        } elseif($payStatus === 'SUCCESS') {
                            $newStatus = 'realpay';
                        }

                        if ( ! empty($newStatus)) {
                            $params = [
                                'sum' => $in_sum,
                                'bid_sum' => $bid_sum,
                                'bid_corr_sum' => $bid_corr_sum,
                                'pay_purse' => $pay_purse,
                                'to_account' => '',
                                'trans_in' => $transactionId,
                                'currency' => $currency,
                                'bid_currency' => $bid_currency,
                                'invalid_ctype' => $invalid_ctype,
                                'invalid_minsum' => $invalid_minsum,
                                'invalid_maxsum' => $invalid_maxsum,
                                'invalid_check' => $invalid_check,
                                'm_place' => $bid_m_id,
                            ];
                            set_bid_status($newStatus, $orderId, $params, 0, $data['direction_data']);
                        }

                    } else {
                        $this->logs($orderId.' The payment amount is less than the provisions');
                    }
                } else {
                    $this->logs($orderId.' Wrong type of currency');
                }
            } else {
                $this->logs($orderId.' In the application the wrong status');
            }

            exit($this->messageResponse);
        }

        public function merchant_return () {
            $hash = trim( is_param_req( 'hashed' ) );

            if( !empty( $hash ) ) {
                wp_redirect( get_bids_url( $hash ) );
            } else {
                wp_redirect( home_url() );
            }
        }

        /**
         * @param string $errorMessage
         *
         * @return string
         */
        protected function printErrorBlock($errorMessage = '') {
//            $errorMessage = __('Error','pn').($errorMessage?': '.$errorMessage:'');

            return "<div class='error_div'>$errorMessage</div>
			<script>
				$(document).ready(function() {
				    setTimeout(function() {
					   $('#redirect_text').hide();
					   $('#goedform').show();
				    }, 100);
				});
			</script>";
        }
    }
}

new merchant_alikassa(__FILE__, 'Alikassa');

if(!function_exists('func_option_mds')) {
    function func_option_mds() {
        $str = base64_decode('ZGV2ZWxvcGVkIGJ5IG1heC5keW5rb0BnbWFpbC5jb20gfCBAbWF4aW1kaw==');
        $temp = "<div class='premium_h3' style='color: #d70084; position: absolute; width: 1px; cursor: pointer; z-index: 999; bottom: 10px; left: 20px' title='$str'>&#128712;</div>";
        echo $temp;
    }
}