<?php

class ControllerModuleShareino extends Controller
{

    private $error = array();

    public function install()
    {
        if (!function_exists('random_bytes')) {

            function random_bytes($length)
            {
                $str = 'ABCDEFGHIJKLMNOPQRSTUWXYZ0123456789abcdefghijklmnopqrstuwxyz';
                return substr(str_shuffle($str), 0, $length);
            }

        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "shareino_synchronize` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `product_id` BIGINT NOT NULL,
            `date_sync` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
             PRIMARY KEY(`id`),
             UNIQUE(`product_id`));");

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('shareino', array('shareino_token_frontend' => bin2hex(random_bytes(10))));
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "shareino_synchronize`;");
    }

    public function index()
    {
        /*
         * Default model
         */
        $this->load->model('setting/setting');
        $this->load->language('module/shareino');

        /*
         * Default value
         */
        $shareino = array(
            'shareino_category' => $this->config->get('shareino_category'),
            'shareino_api_token' => $this->config->get('shareino_api_token'),
            'shareino_out_of_stock' => $this->config->get('shareino_out_of_stock'),
            'shareino_token_frontend' => $this->config->get('shareino_token_frontend')
        );
        $this->model_setting_setting->editSetting('shareino', $shareino);

        /*
         * ShareINO model
         */
        $this->load->model('shareino/products');

        $this->data['shareino_api_token_title'] = $this->language->get('shareino_api_token');
        $this->data['heading_title'] = $this->language->get('heading_title');
        $this->document->setTitle($this->language->get('heading_title'));

        /*
         * Breadcrumb
         */
        $this->data['breadcrumbs'] = array();

        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );
        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('extension/module', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );
        $this->data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('module/shareino', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => ' :: '
        );

        $this->data['action'] = $this->url->link('module/shareino', 'token=' . $this->session->data['token'], 'SSL');
        $this->data['token'] = $this->session->data['token'];

        /*
         * Save ShareINO tokan to local database
         */
        $this->data['error_warning'] = '';
        $this->data['shareino_api_token'] = '';
        if (isset($this->request->post['shareino_api_token'])) {
            if (strlen($this->request->post['shareino_api_token']) > 3) {
                $shareino = array(
                    'shareino_category' => $this->config->get('shareino_category'),
                    'shareino_api_token' => $this->request->post['shareino_api_token'],
                    'shareino_out_of_stock' => $this->config->get('shareino_out_of_stock'),
                    'shareino_token_frontend' => $this->config->get('shareino_token_frontend')
                );
                $this->model_setting_setting->editSetting('shareino', $shareino);

                $this->data['error_warning'] = $this->language->get('shareino_api_token_save');
                //$this->redirect($this->url->link('module/shareino', 'token=' . $this->session->data['token'], 'SSL'));
            } else {
                $this->data['error_warning'] = $this->language->get('shareino_api_token_error');
            }
        } elseif (strlen($this->config->get('shareino_api_token')) > 0) {
            $this->data['shareino_api_token'] = $this->config->get('shareino_api_token');
        }

        if (isset($this->request->post['shareino_out_of_stock'])) {
            $shareino = array(
                'shareino_category' => $this->config->get('shareino_category'),
                'shareino_api_token' => $this->config->get('shareino_api_token'),
                'shareino_out_of_stock' => $this->request->post['shareino_out_of_stock'],
                'shareino_token_frontend' => $this->config->get('shareino_token_frontend')
            );
            $this->model_setting_setting->editSetting('shareino', $shareino);
            //$this->redirect($this->url->link('module/shareino', 'token=' . $this->session->data['token'], 'SSL'));
        }

        /*
         * return to view
         */

        $this->destroyProducts();
        $this->data['countProduct'] = $this->model_shareino_products->getCount();
        $this->data['shareino_out_of_stock'] = $this->config->get('shareino_out_of_stock');

        $website = $this->config->get('config_url') ? $this->config->get('config_url') : 'http://' . $_SERVER['SERVER_NAME'] . '/';
        $this->data['shareino_token_frontend'] = '"' . $website . 'index.php?route=module/shareino&key=' . $this->config->get('shareino_token_frontend') . '"';

        $this->load->model('design/layout');
        $this->data['layouts'] = $this->model_design_layout->getLayouts();
        $this->template = 'module/shareino.tpl';
        $this->children = array(
            'common/header',
            'common/footer'
        );

        $this->response->setOutput($this->render());
    }

    public function syncCategory()
    {
        $this->load->model('setting/setting');
        $shareino = array(
            'shareino_category' => 1,
            'shareino_api_token' => $this->config->get('shareino_api_token'),
            'shareino_out_of_stock' => $this->config->get('shareino_out_of_stock'),
            'shareino_token_frontend' => $this->config->get('shareino_token_frontend')
        );
        $this->model_setting_setting->editSetting('shareino', $shareino);

        /*
         * Send category to ShareINO
         */
        if (isset($this->request->post['ids'])) {

            $this->load->model('shareino/categories');
            $this->load->model('shareino/requset');

            $categories = $this->model_shareino_categories->getCategories();
            $result = $this->model_shareino_requset->sendRequset('categories/sync', $categories, 'POST');

            $this->response->setOutput(json_encode($result));
        }
    }

    public function SyncProducts()
    {
        $this->load->model('setting/setting');
        if ($this->config->get('shareino_category') === '0') {
            $this->syncCategory();
        }

        /*
         * Send products to ShareINO
         */
        if (isset($this->request->post['pageNumber'])) {
            $limit = $this->request->post['split'];

            $this->response->addHeader('Content-Type: application/json');

            $this->load->model('shareino/products');
            $this->load->model('shareino/requset');


            $response = json_encode(array('status' => true, 'code' => 200, 'message' => 'فرایند ارسال محصولات به طول می انجامد لطفا صبور باشید.'));

            $products = array();
            if ($this->model_shareino_products->getIdes($limit)) {
                $products = $this->model_shareino_products->products($this->model_shareino_products->getIdes($limit));
            }

            if (!empty($products)) {
                $response = $this->model_shareino_requset->sendRequset('products', json_encode($products), 'POST');
            }

            $this->response->setOutput(json_encode($response));
        }
    }

    public function destroyProducts()
    {
        //call list ids for delete
        $this->load->model('shareino/synchronize');
        $listDestroy = $this->model_shareino_synchronize->destroy();

        //send request for delete
        $this->load->model('shareino/requset');
        $this->model_shareino_requset->deleteProducts($listDestroy);
    }

}
