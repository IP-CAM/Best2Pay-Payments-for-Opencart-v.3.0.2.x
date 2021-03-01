<?php
class ControllerExtensionPaymentBest2Paye extends Controller {
	private $error = array();

	public function index() {
        $this->load->language('extension/payment/best2pay_e');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_best2pay_e', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['heading_title'] = $this->language->get('heading_title');

        $data['entry_sector'] = $this->language->get('entry_sector');
        $data['help_sector'] = $this->language->get('help_sector');
        $data['entry_password'] = $this->language->get('entry_password');
        $data['help_password'] = $this->language->get('help_password');
        $data['text_on'] = $this->language->get('text_on');
        $data['text_off'] = $this->language->get('text_off');
        $data['entry_test'] = $this->language->get('entry_test');
        $data['help_test'] = $this->language->get('help_test');
        $data['entry_kkt'] = $this->language->get('entry_kkt');
        $data['help_kkt'] = $this->language->get('help_kkt');
        $data['entry_tax'] = $this->language->get('entry_tax');
        $data['help_tax'] = $this->language->get('help_tax');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['sector'])) {
            $data['error_sector'] = $this->error['sector'];
        } else {
            $data['error_sector'] = '';
        }

        if (isset($this->error['password'])) {
            $data['error_password'] = $this->error['password'];
        } else {
            $data['error_password'] = '';
        }

        if (isset($this->request->post['payment_best2pay_e_status'])) {
            $data['payment_best2pay_e_status'] = $this->request->post['payment_best2pay_e_status'];
        } else {
            $data['payment_best2pay_e_status'] = $this->config->get('payment_best2pay_e_status');
        }

        if (isset($this->request->post['payment_best2pay_e_sort_order'])) {
            $data['payment_best2pay_e_sort_order'] = $this->request->post['payment_best2pay_e_sort_order'];
        } else {
            $data['payment_best2pay_e_sort_order'] = $this->config->get('payment_best2pay_e_sort_order');
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => false
        );

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_payment'),
            'href'      => $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
            'separator' => ' :: '
        );

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('extension/payment/best2pay_e', 'user_token=' . $this->session->data['user_token'], true),
            'separator' => ' :: '
        );

        $data['action'] = $this->url->link('extension/payment/best2pay_e', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_best2pay_e_sector'])) {
            $data['payment_best2pay_e_sector'] = $this->request->post['payment_best2pay_e_sector'];
        } else {
            $data['payment_best2pay_e_sector'] = $this->config->get('payment_best2pay_e_sector');
        }

        if (isset($this->request->post['payment_best2pay_e_kkt'])) {
            $data['payment_best2pay_e_kkt'] = $this->request->post['payment_best2pay_e_kkt'];
        } else {
            $data['payment_best2pay_e_kkt'] = $this->config->get('payment_best2pay_e_kkt');
        }

        if (isset($this->request->post['payment_best2pay_e_tax'])) {
            $data['payment_best2pay_e_tax'] = $this->request->post['payment_best2pay_e_tax'];
        } else {
            $data['payment_best2pay_e_tax'] = $this->config->get('payment_best2pay_e_tax');
        }

        if (isset($this->request->post['payment_best2pay_e_password'])) {
            $data['payment_best2pay_e_password'] = $this->request->post['payment_best2pay_e_password'];
        } else {
            $data['payment_best2pay_e_password'] = $this->config->get('payment_best2pay_e_password');
        }

        $data['callback'] = HTTPS_CATALOG . 'index.php?route=payment/best2pay_e/callback';

        if (isset($this->request->post['payment_best2pay_e_test'])) {
            $data['payment_best2pay_e_test'] = $this->request->post['payment_best2pay_e_test'];
        } else {
            $data['payment_best2pay_e_test'] = $this->config->get('payment_best2pay_e_test');
        }

        //$this->template = 'extension/payment/best2pay_e.tpl';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/best2pay_e', $data));
	}

	protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/best2pay_e')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_best2pay_e_sector']) {
            $this->error['warning'] = $this->language->get('error_sector');
        }

        if (!$this->request->post['payment_best2pay_e_password']) {
            $this->error['warning'] = $this->language->get('error_password');
        }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
	}
}