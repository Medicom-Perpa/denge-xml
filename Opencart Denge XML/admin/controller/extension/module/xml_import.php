<?php
class ControllerExtensionModuleXmlImport extends Controller {

    private $error = array();

    public function index() {
        $this->load->language('extension/module/xml_import');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $this->model_setting_setting->editSetting('module_xml_import', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect(
                $this->url->link('extension/module/xml_import', 'user_token=' . $this->session->data['user_token'], true)
            );
        }

        $data['heading_title'] = $this->language->get('heading_title');

        // Hatalar
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_xml_url'] = isset($this->error['xml_url']) ? $this->error['xml_url'] : '';
        $data['error_category_xml_url'] = isset($this->error['category_xml_url']) ? $this->error['category_xml_url'] : '';

        // Breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/xml_import', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/xml_import', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // FORM ALANLARI — Ürün URL
        if (isset($this->request->post['module_xml_import_url'])) {
            $data['module_xml_import_url'] = $this->request->post['module_xml_import_url'];
        } else {
            $data['module_xml_import_url'] = $this->config->get('module_xml_import_url');
        }

        // FORM ALANLARI — Kategori URL  (YENİ EKLENDİ)
        if (isset($this->request->post['module_xml_import_category_url'])) {
            $data['module_xml_import_category_url'] = $this->request->post['module_xml_import_category_url'];
        } else {
            $data['module_xml_import_category_url'] = $this->config->get('module_xml_import_category_url');
        }

        // Durum
        if (isset($this->request->post['module_xml_import_status'])) {
            $data['module_xml_import_status'] = $this->request->post['module_xml_import_status'];
        } else {
            $data['module_xml_import_status'] = $this->config->get('module_xml_import_status');
        }

        // Döviz alanı
        if (isset($this->request->post['module_xml_import_currency'])) {
            $data['module_xml_import_currency'] = $this->request->post['module_xml_import_currency'];
        } else {
            $data['module_xml_import_currency'] = $this->config->get('module_xml_import_currency');
        }

        if (!$data['module_xml_import_currency']) {
            $data['module_xml_import_currency'] = 'USD';
        }

        $data['currencies'] = array(
            array('code' => 'USD', 'name' => 'USD → TRY'),
            array('code' => 'EUR', 'name' => 'EUR → TRY')
        );

        // Front URL'ler (cron & run_now)
        if (defined('HTTPS_CATALOG')) {
            $catalog_base = HTTPS_CATALOG;
        } else {
            $catalog_base = HTTP_CATALOG;
        }

        $data['cron_url']    = $catalog_base . 'index.php?route=extension/module/xml_import/cron';
        $data['run_now_url'] = $catalog_base . 'index.php?route=extension/module/xml_import/run_now';

        // LANG STRINGS
        $data['text_edit']       = $this->language->get('text_edit');
        $data['text_enabled']    = $this->language->get('text_enabled');
        $data['text_disabled']   = $this->language->get('text_disabled');

        $data['entry_status']    = $this->language->get('entry_status');
        $data['entry_xml_url']   = $this->language->get('entry_xml_url');
        $data['entry_currency']  = $this->language->get('entry_currency');
        $data['entry_cron_url']  = $this->language->get('entry_cron_url');
        $data['entry_run_now']   = $this->language->get('entry_run_now');

        // YENİ EKLENEN
        $data['entry_category_xml_url'] = $this->language->get('entry_category_xml_url');
        $data['help_category_xml_url']  = $this->language->get('help_category_xml_url');

        $data['help_xml_url']   = $this->language->get('help_xml_url');
        $data['help_currency']  = $this->language->get('help_currency');
        $data['help_cron_url']  = $this->language->get('help_cron_url');

        $data['button_save']    = $this->language->get('button_save');
        $data['button_cancel']  = $this->language->get('button_cancel');
        $data['button_run_now'] = $this->language->get('button_run_now');

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Common parçalar
        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/xml_import', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/xml_import')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Ürün XML URL boşsa
        if (empty($this->request->post['module_xml_import_url'])) {
            $this->error['xml_url'] = $this->language->get('error_xml_url');
        }

        // Kategori XML URL boşsa (YENİ)
        if (empty($this->request->post['module_xml_import_category_url'])) {
            $this->error['category_xml_url'] = $this->language->get('error_category_xml_url');
        }

        return !$this->error;
    }
}
