<?php
    require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
    require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');
    
    class ControllerTrustpilotAjax extends Controller {
        private $helper = null;
        public function __construct($registry) {
            $this->helper = TrustpilotHelper::getInstance($registry);
            parent::__construct($registry);
        }
        public function index() {
            try {
                if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
                    if (!$this->validateToken()) {
                        $output = array(
                            'error' => 'Invalid token!',
                        );
                        $this->createResponse($output, 'plugin');
                        return;
                    }
                    $post = $this->request->post;
                    if ($post['action'] == 'handle_save_changes') {
                        if (isset($_POST['settings'])) {
                            $post_settings = htmlspecialchars_decode($post['settings']);
                            $settings = $this->helper->setTrustpilotField(TRUSTPILOT_MASTER_FIELD, $post_settings);
                            $this->createResponse($settings);
                        }
                        if (isset($_POST['pageUrls'])) {
                            $pageUrls = htmlspecialchars_decode($post['pageUrls']);
                            $this->helper->setTrustpilotField(TRUSTPILOT_PAGE_URLS_FIELD, $pageUrls);
                            echo $pageUrls;
                        }
                        if (isset($_POST['customTrustBoxes'])) {
                            $customTrustBoxes = htmlspecialchars_decode($_POST['customTrustBoxes']);
                            $this->helper->setTrustpilotField(TRUSTPILOT_CUSTOM_TRUSTBOXES_FIELD, $customTrustBoxes);
                            echo $customTrustBoxes;
                        }
                        return;
                    }
                    if ($post['action'] == 'handle_past_orders') {
                        $this->load->model($this->helper->versionSafePath('extension/module/trustpilot/past_orders'));
                        if (array_key_exists('sync', $post)) {
                            $this->model_module_trustpilot_past_orders->syncPastOrders($post['sync']);
                            $output = $this->model_module_trustpilot_past_orders->getPastOrdersInfo();
                            $this->createResponse($output, 'plugin');
                            return;
                        }
                        if (array_key_exists('resync', $post)) {
                            $this->model_module_trustpilot_past_orders->resyncPastOrders();
                            $output = $this->model_module_trustpilot_past_orders->getPastOrdersInfo();
                            $this->createResponse($output, 'plugin');
                            return;
                        }
                        if (array_key_exists('issynced', $post)) {
                            $output = $this->model_module_trustpilot_past_orders->getPastOrdersInfo();
                            $this->createResponse($output, 'plugin');
                            return;
                        }
                        if (array_key_exists('showPastOrdersInitial', $post)) {
                            $this->helper->setTrustpilotField(TRUSTPILOT_SHOW_PAST_ORDERS_INITIAL, $post['showPastOrdersInitial']);
                            return;
                        }
                    }
                    if ($post['action'] == 'check_product_skus') {
                        $this->load->model($this->helper->versionSafePath('extension/module/trustpilot/products'));
                        $output = $this->model_module_trustpilot_products->checkSkus($post['skuSelector']);
                        $this->createResponse($output);
                        return;
                    }
                } elseif (($this->request->server['REQUEST_METHOD'] == 'GET')) {
                    $output = array(
                        'health-check' => 'OK',
                    );
                    $this->createResponse($output, 'plugin');
                    return;
                }
            } catch (Exception $e) {
                $message = 'extension/trustpilot/ajax: Failed to process request. Error: ' . $e->getMessage();
                $output = array(
                    'error' => $message,
                );
                $this->helper->log($message);
                $this->createResponse($output, 'plugin');
                return;
            }
        }

        private function createResponse($output = null, $basis = null) {
            if (isset($output)) {
                if (isset($basis)) {
                    $output['basis'] = $basis;
                }
                $this->response->setOutput(json_encode($output));
            }
        }

        private function validateToken() {
            if (isset($this->request->get['token'])) {
                if (isset($this->session->data['token'])) {
                    return $this->session->data['token'] == $this->request->get['token'];
                }
            }
            if (isset($this->request->get['user_token'])) {
                if (isset($this->session->data['user_token'])) {
                    return $this->session->data['user_token'] == $this->request->get['user_token'];
                }
            }
            return false;
        }
    }
