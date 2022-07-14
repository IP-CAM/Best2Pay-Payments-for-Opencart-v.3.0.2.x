<?php
class ControllerExtensionPaymentBest2pay extends Controller {
    public function index() {
        $this->load->language('payment/best2pay');
        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $redirect_url = $this->registerOrder($order_info);
        if ($redirect_url) {
            return $this->load->view('extension/payment/best2pay',  array(
                'button_confirm' => $this->language->get('button_confirm'),
                'action' => $redirect_url
            ));
        } else {
            return $this->load->view('extension/payment/best2pay_error', array(
                'error' => $this->language->get('text_error')
            ));
        }
    }

    public function callback() {
        try {
            $xml = file_get_contents("php://input");
            if (!$xml)
                throw new Exception("Empty data");
            $xml = simplexml_load_string($xml);
            if (!$xml)
                throw new Exception("Non valid XML was received");
            $response = json_decode(json_encode($xml));
            if (!$response)
                throw new Exception("Non valid XML was received");

            if (($response->reason_code)) {
                $this->load->model('checkout/order');
                if ($response->reason_code == 1){
                    $this->model_checkout_order->addOrderHistory($response->reference, 2, 'Best2Pay Success'); // Processing
                } else {
                    $this->model_checkout_order->addOrderHistory($response->reference, 16, 'Best2Pay Fail'); // Voided
                }
                die("ok");
            }
        } catch (Exception $ex) {
            $this->log->write(($ex->getMessage()));
            die($ex->getMessage());
        }
    }

    public function request() {
        error_reporting(0);
        try {
            $this->language->load('payment/best2pay');
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->request->get['reference']);
            if (!$order_info) {
                $this->response->redirect($this->url->link('checkout/failure'));
                return false;
            }

            // если пришло уведомление
            if ($order_info['order_status_id'] == $this->config->get('payment_best2pay_approved_status') || $order_info['order_status_id'] == 15) {
                $this->response->redirect($this->url->link('checkout/success'));
                return true;
            }

            if ($this->checkPaymentStatus()) {
                $this->model_checkout_order->addOrderHistory($this->request->get['reference'], 2, 'Best2Pay Success'); // Processing
                $this->response->redirect($this->url->link('checkout/success'));
            } else {
                $this->model_checkout_order->addOrderHistory($this->request->get['reference'], 16, 'Best2Pay Fail'); // Voided
                $this->response->redirect($this->url->link('checkout/failure'));
            }
        } catch (Exception $ex){
            $this->log->write(($ex->getMessage()));
            $this->model_checkout_order->addOrderHistory($this->request->get['reference'], 16, 'Best2Pay Fail'); // Voided
            $this->response->redirect($this->url->link('checkout/failure'));
        }
        error_reporting(1);
    }

    private function registerOrder($order_info) {
        $this->load->language('extension/payment/best2pay');

        switch ($order_info['currency_code']) {
            case 'EUR':
                $currency = '978';
                break;
            case 'USD':
                $currency = '840';
                break;
            default:
                $currency = '643';
                break;
        }

        if (!$this->config->get('payment_best2pay_test')) {
            $best2pay_url = 'https://pay.best2pay.net';
        } else {
            $best2pay_url = 'https://test.best2pay.net';
        }
        $descOrderName = $this->language->get('order_number');
        $desc=$descOrderName.' '.$order_info['order_id'];

        $amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $signature = base64_encode(md5($this->config->get('payment_best2pay_sector') . intval($amount * 100) . $currency . $this->config->get('payment_best2pay_password')));

        $fiscalPositions='';
        $fiscalAmount = 0;
        $KKT = $this->config->get('payment_best2pay_kkt');
        if ($KKT==1){
            $TAX = (strlen($this->config->get('payment_best2pay_tax')) > 0) ?
                intval($this->config->get('payment_best2pay_tax')) : 7;
            if ($TAX > 0 && $TAX < 7){
                $products = $this->cart->getProducts();
                foreach ($products as $product) {
                    $fiscalPositions.=$product['quantity'].';';
                    $elementPrice = $product['price'];
                    $elementPrice = $elementPrice * 100;
                    $fiscalPositions.=$elementPrice.';';
                    $fiscalPositions.=$TAX.';';
                    $fiscalPositions.=str_ireplace([';', '|'], '', $product['name']).'|';

                    $fiscalAmount += $product['quantity'] * $elementPrice;
                }
                if (isset($this->session->data['shipping_method']) && $this->session->data['shipping_method']['cost'] > 0) {
                    $fiscalPositions.='1;';
                    $fiscalPositions.=($this->session->data['shipping_method']['cost']*100).';';
                    $fiscalPositions.=$TAX.';';
                    //$fiscalPositions.='shipping'.'|';
                    // $fiscalPositions.=$this->session->data['shipping_method']['title'].'|';
                    $fiscalPositions.='Доставка'.'|';

                    $fiscalAmount += $this->session->data['shipping_method']['cost']*100;
                }
                $amountDiff = abs($fiscalAmount - ($amount * 100));
                if ($amountDiff) {
                    $fiscalPositions.='1;'.$amountDiff.';6;Скидка;14|';
                }
                $fiscalPositions = substr($fiscalPositions, 0, -1);
            }
        }

        $query = http_build_query(array(
            'sector' => $this->config->get('payment_best2pay_sector'),
            'reference' => $order_info['order_id'],
            'fiscal_positions' => urlencode($fiscalPositions),
            'amount' => intval($amount * 100),
            'description' => $desc,
            'email' => $order_info['email'],
            'phone' => $order_info['telephone'],
            'currency' => $currency,
            'mode' => 1,
            'url' => HTTP_SERVER . 'index.php?route=extension/payment/best2pay/request',
            'signature' => $signature
        ));

        $context = stream_context_create(array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . "Content-Length: " . strlen($query) . "\r\n",
                'method'  => 'POST',
                'content' => $query
            )
        ));

        $old_lvl = error_reporting(0);

        $b2p_order_id = $this->session->data['$b2p_order_id'];
        $b2p_order_id_original = $this->session->data['$b2p_order_id_original'];
        if (!isset($b2p_order_id)){
                $b2p_order_id = file_get_contents($best2pay_url . '/webapi/Register', false, $context);
                if (!intval($b2p_order_id)) {
                    if( $curl = curl_init() ) {
                        curl_setopt($curl, CURLOPT_URL, $best2pay_url . '/webapi/Register');
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl, CURLOPT_POST, true);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, '&sector=' . $this->config->get('payment_best2pay_sector') . '&reference=' . $order_info['order_id'] . '&fiscal_positions=' . urlencode($fiscalPositions) . '&amount=' .
                            intval($amount * 100) . '&description=' . urlencode($desc) . '&email=' . $order_info['email'] . '&currency=' . $currency . '&mode=' . '1' . '&signature=' . $signature . '&url=' . HTTP_SERVER . 'index.php?route=extension/payment/best2pay/request');
                        $b2p_order_id = curl_exec($curl);
                        curl_close($curl);
                    }
                }
                $order_info['b2p_order_id']=$b2p_order_id;
                $this->session->data['$b2p_order_id']=$b2p_order_id;
                $this->session->data['$b2p_order_id_original']=$order_info['order_id'];
        } else if ($b2p_order_id_original!=$order_info['order_id']){
            $b2p_order_id = file_get_contents($best2pay_url . '/webapi/Register', false, $context);
            if (!intval($b2p_order_id)) {
                if( $curl = curl_init() ) {
                    curl_setopt($curl, CURLOPT_URL, $best2pay_url . '/webapi/Register');
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, '&sector=' . $this->config->get('payment_best2pay_sector') . '&reference=' . $order_info['order_id'] . '&fiscal_positions=' . urlencode($fiscalPositions) . '&amount=' .
                        intval($amount * 100) . '&description=' . urlencode($desc) . '&email=' . $order_info['email'] . '&currency=' . $currency . '&mode=' . '1' . '&signature=' . $signature . '&url=' . HTTP_SERVER . 'index.php?route=extension/payment/best2pay/request');
                    $b2p_order_id = curl_exec($curl);
                    curl_close($curl);
                }
            }
            $order_info['b2p_order_id']=$b2p_order_id;
            $this->session->data['$b2p_order_id']=$b2p_order_id;
            $this->session->data['$b2p_order_id_original']=$order_info['order_id'];
        } else {
            //TODO ничего не делаем заказ уже зарегистрирован
        }

        error_reporting($old_lvl);

        if (intval($b2p_order_id) == 0) {
            error_log($b2p_order_id);
            return false;
        } else {
            $signature = base64_encode(md5($this->config->get('payment_best2pay_sector') . $b2p_order_id . $this->config->get('payment_best2pay_password')));
            $this->model_checkout_order->addOrderHistory($order_info['order_id'], $this->config->get('payment_best2pay_registered_status'), 'best2pay: Заказ зарегистрирован в ПЦ'); // Processed
            if ($this->config->get('payment_best2pay_two_steps')) {
                $endpoint = '/webapi/Authorize';
            } else {
                $endpoint = '/webapi/Purchase';
            }
            return "{$best2pay_url}{$endpoint}?sector={$this->config->get('payment_best2pay_sector')}&id={$b2p_order_id}&signature={$signature}";
        }

    }

    private function checkPaymentStatus() {
        $b2p_order_id = intval($this->request->get['id']);
        if (!$b2p_order_id)
            return false;

        $b2p_operation_id = intval($this->request->get['operation']);
        if (!$b2p_operation_id)
            return false;

        $order_id = intval($this->request->get['reference']);
        if (!$order_id)
            return false;

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info)
            return false;

        // check payment operation state
        $signature = base64_encode(md5($this->config->get('payment_best2pay_sector') . $b2p_order_id . $b2p_operation_id . $this->config->get('payment_best2pay_password')));

        if (!$this->config->get('payment_best2pay_test')) {
            $best2pay_url = 'https://pay.best2pay.net';
        } else {
            $best2pay_url = 'https://test.best2pay.net';
        }

        $query = http_build_query(array(
            'sector' => $this->config->get('payment_best2pay_sector'),
            'id' => $b2p_order_id,
            'operation' => $b2p_operation_id,
            'signature' => $signature
        ));
        $context  = stream_context_create(array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . "Content-Length: " . strlen($query) . "\r\n",
                'method'  => 'POST',
                'content' => $query
            )
        ));

        $repeat = 3;
        while ($repeat) {

            $repeat--;
            // pause because of possible background processing in the Best2Pay
            sleep(2);

            $xml = file_get_contents($best2pay_url . '/webapi/Operation', false, $context);

            if (!$xml)
                break;
            $xml = simplexml_load_string($xml);
            if (!$xml)
                break;
            $response = json_decode(json_encode($xml));
            if (!$response)
                break;

            if (!$this->orderWasPayed($response))
                continue;

            return true;
        }

        return false;
    }

    private function orderWasPayed($response) {
        // looking for an order
        $order_id = (isset($response->reference)) ? intval($response->reference) : 0;
        if ($order_id == 0)
            return false;

        // check payment state
        if (($response->type != 'PURCHASE_BY_QR' && $response->type != 'PURCHASE' && $response->type != 'EPAYMENT' && $response->type != 'AUTHORIZE') || $response->state != 'APPROVED')
            return false;

        // check server signature
        $tmp_response = json_decode(json_encode($response), true);
        unset($tmp_response["signature"]);
        unset($tmp_response["protocol_message"]);
        unset($tmp_response["ofd_state"]);

        $signature = base64_encode(md5(implode('', $tmp_response) . $this->config->get('payment_best2pay_password')));
        return $signature === $response->signature;
    }

    /**
     * @return bool
     */
    public function notify() {
        $response = simplexml_load_string(file_get_contents('php://input'));
        // error_log($response);
        error_log(print_r($response, true));
        if (!(isset($response->order_state) && isset($response->state) && isset($response->reference))) return false;

        $response_signature = $response->signature;

        $arResponse = (array)$response;
        unset($arResponse['signature']);

        $signature = base64_encode(md5(implode('', $arResponse) . $this->config->get('payment_best2pay_password')));
        if ($response_signature != $signature) {
            error_log('b2p_notify_service: Ошибка! Неверная подпись');
            return false;
        }

        $config_notify_status = ($this->config->get('payment_best2pay_'.strtolower((string)$response->order_state).'_status')) ?: false;

        //
        // statuses
        //
        // $message_prefix = 'best2pay : order_id='.$response->reference.' ';
        $message_prefix = 'best2pay : ';
        $local_status = array(
            'REGISTERED' => [
                'code' => ($config_notify_status) ?: 2,
                'message' => $message_prefix . 'Заказ зарегистрирован в ПЦ.'
            ],
            'AUTHORIZED' => [
                'code' => ($config_notify_status) ?: 2,
                'message' => $message_prefix . 'Деньги для проведения платежа заблокированы на счёте Карты Плательщика'
            ],
            'P2PAUTHORIZED' => [
                'code' => ($config_notify_status) ?: 15,
                'message' => $message_prefix . 'В рамках Заказа успешно проведена операция типа P2PDEBIT.'
            ],
            'COMPLETED' => [
                'code' => ($config_notify_status) ?: 15,
                'message' => $message_prefix . 'Заказ успешно оплачен.'
            ],
            'CANCELED' => [
                'code' => ($config_notify_status) ?: 16,
                'message' => $message_prefix . 'Заказ отменен.'
            ],
            'BLOCKED' => [
                'code' => ($config_notify_status) ?: 16,
                'message' => $message_prefix . 'Заказ заблокирован.'
            ],
            'EXPIRED' => [
                'code' => ($config_notify_status) ?: 16,
                'message' => $message_prefix . 'Срок действия заказа истек.'
            ]
        );

        // todo: изменение статуса заказа
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($response->reference);
        $local_amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false) * 100;
        $state = $local_status[(string)$response->order_state];
        $sym_currency = ((string)$response->currency == 643) ? 'RUB' : (((string)$response->currency == 840) ? 'USD' : (((string)$response->currency == 978) ? 'EUR' : '' ));
        if ((string)$response->order_state == 'COMPLETED') {
            if ((string)$response->type == 'REVERSE') {
                $state['message'] = '❗❗❗' . $message_prefix . '<b style="color: red;">Выполнен частичный возврат на сумму ' . ((int)$response->amount) / 100 . ' ' . $sym_currency;
            } else if ((int)$response->amount != $local_amount) {
                $state['message'] = '❗❗❗' . $message_prefix . '<b style="color: red;">Выполнен частичный комплит на сумму ' . ((int)$response->amount) / 100 . ' ' . $sym_currency;
            }
        }
        if ($order_info['order_status_id'] != $state['code'] || true) {
            $this->model_checkout_order->addOrderHistory($response->reference, $state['code'], $state['message']); // Processed
            if ((string)$response->order_state == 'COMPLETED') {
                $b2p_total = ($this->db->query("select b2p_total from ".DB_PREFIX."order where order_id = ".$response->reference.";")->rows)[0]['b2p_total'] * 100;
                if (!$b2p_total) $b2p_total = 0;
                if ((string)$response->type == 'REVERSE') {
                    // частичный возврат 
                    $this->db->query("update ".DB_PREFIX."order set b2p_total=".(($b2p_total - (int)$response->amount)/100)." where order_id = ".$response->reference.";");
                } else if ((int)$response->amount != $local_amount) {
                    // частичный комплит
                    $this->db->query("update ".DB_PREFIX."order set b2p_total=".(((int)$response->amount)/100)." where order_id = ".$response->reference.";");
                } else {
                    // полный комплит
                    $this->db->query("update ".DB_PREFIX."order set b2p_total=".(((int)$response->amount)/100)." where order_id = ".$response->reference.";");
                }
            } else if ((string)$response->order_state == 'CANCELED') {
                // заказ отменен
                $this->db->query("update ".DB_PREFIX."order set b2p_total=0 where order_id = ".$response->reference.";");
            }
        }

        return true;
    }
}
