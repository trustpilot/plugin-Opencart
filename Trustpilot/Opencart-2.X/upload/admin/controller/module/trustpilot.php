<?php
    require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
    require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');
    require_once(DIR_SYSTEM . 'library/trustpilot/trustpilot_http_client.php');

    class ControllerModuleTrustpilot extends Controller {
        private $helper, $trustpilot_api = null;
        private $error = array();
        private static $language_assigns = array(
            'heading_title',
            'text_edit',
            'entry_appKey',
            'entry_fieldGTIN',
            'entry_GTINDescription',
            'entry_KeyDescription',
            'entry_None',
            'entry_UPC',
            'entry_ISBN',
            'entry_EAN',
            'entry_JAN',
            'button_save',
            'button_cancel'
        );

        public function __construct($registry) {
            $this->helper = TrustpilotHelper::getInstance($registry);
            $this->trustpilot_api = new TrustpilotHttpClient(TRUSTPILOT_API_URL, $registry, $this->helper->getBaseUrl());
            parent::__construct($registry);
        }

        public function index() {
            $this->document->addScript('view/javascript/trustpilot/js/integrate.js');

            $this->load->language($this->helper->versionSafePath('extension/module/trustpilot'));
            $this->document->setTitle($this->language->get('heading_title'));

            $settings = $this->helper->getTrustpilotField(TRUSTPILOT_MASTER_FIELD);

            //Pull languages
            foreach (self::$language_assigns as $data_name) {
                $data[$data_name] = $this->language->get($data_name);
            }

            if (isset($this->error['warning'])) {
                $data['error_warning'] = $this->error['warning'];
            } else {
                $data['error_warning'] = '';
            }

            $data['trustpilot_master_settings_field'] =  htmlspecialchars(json_encode($settings));
            $data['settings'] = base64_encode(json_encode($settings));
            $data = $this->setupBreadcrumbs($data);

            if ($this->helper->isV3()) {
                if (isset($this->request->post['module_Trustpilot_status'])) {
                    $data['module_Trustpilot_status'] = $this->request->post['module_Trustpilot_status'];
                } else {
                    $data['module_Trustpilot_status'] = $this->config->get('module_Trustpilot_status');
                }
            }
            $data['TRUSTPILOT_INTEGRATION_APP_URL'] = $this->getDomainName();
            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');
            $data['page_urls'] = base64_encode(stripslashes(json_encode($this->getPageUrls())));
            $data['sku'] = $this->getProductSku();
            $data['name'] = $this->getProductName();
            $data['version'] = VERSION;
            $data['plugin_version'] = TRUSTPILOT_PLUGIN_VERSION;
            $data['product_identification_options'] = json_encode(['sku', 'upc', 'ean', 'jan', 'isbn', 'mpn']);
            $data['past_orders_info'] = $this->getPastOrdersInfo();
            $data['is_from_marketplace'] = TRUSTPILOT_IS_FROM_MARKETPLACE;
            $data['starting_url'] = $this->config->get('config_secure') ? HTTPS_CATALOG : HTTP_CATALOG;
            $data['trustbox_preview_url'] = TRUSTPILOT_TRUSTBOX_PREVIEW_URL;
            $data['custom_trustboxes'] = $this->config->get(TRUSTPILOT_CUSTOM_TRUSTBOXES_FIELD);
            $data['configuration_scope_tree'] = base64_encode(json_encode($this->helper->getConfigurationScopeTree()));
            $data['plugin_status'] = base64_encode(json_encode($this->helper->getTrustpilotField(TRUSTPILOT_PLUGIN_STATUS_FIELD)));

            $this->response->setOutput($this->load->view($this->helper->versionSafeViewPath('extension/module/trustpilot'), $data));
        }

        private function setupBreadcrumbs($data) {
            //Setup Breadcrumbs
            $data['breadcrumbs'] = array();
            $data['breadcrumbs'][] = $this->getArrayUrlItem('text_home', 'common/dashboard');
            $data['breadcrumbs'][] = $this->getArrayUrlItem('text_module', $this->getExtensionsPath());
            $data['breadcrumbs'][] = $this->getArrayUrlItem('heading_title', $this->helper->versionSafePath('extension/module/trustpilot'));

            //Setup save/cancel buttons
            $data['action'] = $this->getLink($this->helper->versionSafePath('module/trustpilot'));
            $data['cancel'] = $this->getExtensionsLink();

            return $data;
        }

        private function getArrayUrlItem($language, $url) {
            return array(
                'text' => $this->language->get($language),
                'href' => $this->getLink($url)
            );
        }

        private function getLink($url) {
            if ($this->helper->isV3()) {
                return $this->url->link($url, 'user_token=' . $this->session->data['user_token'], true);
            } else {
                return $this->url->link($url, 'token=' . $this->session->data['token'], true);
            }
        }

        private function getEventModel() {
            if ($this->helper->isV3()) {
                $this->load->model('setting/event');
                return $this->model_setting_event;
            } else {
                $this->load->model('extension/event');
                return $this->model_extension_event;
            }
        }

        public function install() {
            $event_model = $this->getEventModel();
            if ($this->helper->isV21()) {
                $event_model->addEvent(
                    'tp_orderStatusChange',
                    'post.order.history.add',
                    $this->helper->versionSafePath('extension/module/trustpilot/legacyOrderStatusChange21')
                );
            } else if ($this->helper->isV22()) {
                $event_model->addEvent(
                    'tp_orderStatusChange',
                    'catalog/model/checkout/order/addOrderHistory/after',
                    $this->helper->versionSafePath('extension/module/trustpilot/legacyOrderStatusChange')
                );
            } else {
                $event_model->addEvent(
                    'tp_orderStatusChange',
                    'catalog/model/checkout/order/addOrderHistory/after',
                    $this->helper->versionSafePath('extension/module/trustpilot/orderStatusChange')
                );
            }
            $event_model->addEvent(
                'trustpilot_menu',
                'admin/view/common/column_left/before',
                $this->helper->versionSafePath('extension/module/trustpilot/trustpilotMenu')
            );
            $settings = array(
                TRUSTPILOT_MASTER_FIELD =>  json_encode(array(
                    'general' => array(
                        'key' => '',
                        'invitationTrigger' => 'orderConfirmed',
                        'mappedInvitationTrigger' => array(),
                    ),
                    'trustbox' => array(
                        'trustboxes' => array(),
                    ),
                    'skuSelector' => 'none',
                    'mpnSelector' => 'none',
                    'gtinSelector' => 'none',
                    'pastOrderStatuses' => array(2, 3, 5, 15),
                )),
                TRUSTPILOT_PAGE_URLS_FIELD => '[]',
                TRUSTPILOT_SYNC_IN_PROGRESS => 'false',
                TRUSTPILOT_SHOW_PAST_ORDERS_INITIAL => 'true',
                TRUSTPILOT_PAST_ORDERS_FIELD => '0',
                TRUSTPILOT_FAILED_ORDERS_FIELD => '{}',
                TRUSTPILOT_CUSTOM_TRUSTBOXES_FIELD => '{}',
                TRUSTPILOT_PLUGIN_STATUS_FIELD => json_encode(array(
                    'pluginStatus' => 200,
                    'blockedDomains' => array(),
                )),
            );
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('trustpilot', $settings);

            $this->load->model('user/user_group');
            $this->model_user_user_group->addPermission($this->user->getId(), 'access', $this->helper->versionSafePath('extension/module/trustpilot'));
            $this->model_user_user_group->addPermission($this->user->getId(), 'modify', $this->helper->versionSafePath('extension/module/trustpilot'));
        }

        public function uninstall() {
            $this->load->model('setting/setting');
            $settings = $this->config->get(TRUSTPILOT_MASTER_FIELD);
            $data = array(
                'settings'   => stripslashes(json_encode($settings)),
                'event'      => 'Uninstalled',
                'platform'   => 'OpenCart'
            );
            $this->trustpilot_api->postLog($data);
            $event_model = $this->getEventModel();
            if ($this->helper->isV3()) {
                $event_model->deleteEventByCode('trustpilot_menu');
                $event_model->deleteEventByCode('tp_orderStatusChange');
            } else {
                $event_model->deleteEvent('trustpilot_menu');
                $event_model->deleteEvent('tp_orderStatusChange');
            }
            $this->model_setting_setting->deleteSetting('trustpilot');
        }

        private function getExtensionsPath() {
            if ($this->helper->isV23()) {
                return 'extension/extension&type=module';
            } else if ($this->helper->isV3()) {
                return 'marketplace/extension&type=module';
            } else {
                return 'extension/module';
            }
        }

        private function getExtensionsLink() {
            return $this->getLink($this->getExtensionsPath());
        }

        function getProducts($limit = null) {
            $this->load->model('catalog/product');
            $filter_data = array(
                'filter_status' => '1',
            );
            if ($limit) {
                $filter_data['start'] = 0;
                $filter_data['limit'] = $limit;
            }
            return $this->model_catalog_product->getProducts($filter_data);
        }

        private function getCategoryUrl($base) {
            $product_ids = array_map(function ($a) { return (int)$a['product_id']; },
                $this->getProducts(100));
            $sql = "SELECT DISTINCT cp.category_id  AS category_id
                    FROM " . DB_PREFIX . "category_path cp
                    LEFT JOIN " . DB_PREFIX . "product_to_category ptc
                    ON (cp.category_id = ptc.category_id)
                    WHERE  ptc.product_id IN (".implode(',', $product_ids).")";
            $query = $this->db->query($sql);
            $category = $query->rows[0];

            return html_entity_decode($this->helper->link(
                'product/category',
                $base,
                'path=' . $category['category_id']
            ));
        }

        private function getProductUrl($base) {
            $product = $this->getProducts(1)[0];
            return html_entity_decode($this->helper->link(
                'product/product',
                $base,
                'product_id=' . $product['product_id']
            ));
        }

        private function getPageUrls() {
            $secure = $this->config->get('config_secure');
            $base = $secure ? HTTPS_CATALOG : HTTP_CATALOG;
            $pageUrls = array(
                'landing' => $base,
                'category' => $this->getCategoryUrl($base),
                'product' => $this->getProductUrl($base),
            );
            $customUrls = json_decode($this->config->get(TRUSTPILOT_PAGE_URLS_FIELD));
            $pageUrls = (object) array_merge((array) $customUrls, (array) $pageUrls);
            return $pageUrls;
        }

        private function getProductSku() {
            $product = $this->getProducts(1)[0];
            $skuSelector = json_decode($this->config->get('trustpilot_master_settings_field'))->skuSelector;
            if (!empty($product) && $skuSelector != 'none' && $skuSelector != '' ) {
                $sku = $product[$skuSelector];
                return $sku;
            } else {
                return '';
            }
        }

        private function getPastOrdersInfo() {
            $this->load->model($this->helper->versionSafePath('extension/trustpilot/past_orders'));
            $info = $this->model_trustpilot_past_orders->getPastOrdersInfo();
            $info['basis'] = 'plugin';
            return json_encode($info);
        }

        function getProductName() {
            $product = $this->getProducts(1)[0];
            if (!empty($product)) {
                return $product['name'];
            } else {
                return '';
            }
        }

        private function getDomainName() {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https:" : "http:";
            $domainName = $protocol . TRUSTPILOT_INTEGRATION_APP_URL;
            return $domainName;
        }

        public function trustpilotMenu($route, &$data) {
            if ($this->helper->isV22()) {
                $closingTagindex = strrpos($data['menu'], '</ul>');
                $data['menu'] = 
                    substr($data['menu'], 0, $closingTagindex) .
                        '<li id="menu-trustpilot">' .
                            '<a href="' . $this->getLink($this->helper->versionSafePath('extension/module/trustpilot')) . '">' .
                                '<i class="fa fa-trustpilot fa-fw"></i> <span>Trustpilot</span>' .
                            '</a>' .
                        '</li>' .
                    substr($data['menu'], $closingTagindex);
            } else {
                $data['menus'][] = array(
                    'id'       => 'menu-trustpilot',
                    'icon'     => 'fa-trustpilot',
                    'name'     => 'Trustpilot',
                    'href'     => $this->getLink($this->helper->versionSafePath('extension/module/trustpilot')),
                    'children' => array()
                );
            }
        }
    }
