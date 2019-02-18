<?php

require_once(DIR_SYSTEM . 'library/trustpilot/config.php');

class TrustpilotHelper {
    private $registry = null;
    protected static $instance = null;

    public static function getInstance($registry) {
        if ( null == self::$instance ) {
            self::$instance = new self($registry);
        }
        return self::$instance;
    }

    public function __construct($registry) {
        $this->registry = $registry;
    }

    public function isV22() {
        return version_compare(VERSION, '2.3', '<');
    }

    public function isV23() {
        return version_compare(VERSION, '3.0', '<') &&
               version_compare(VERSION, '2.3', '>=');
    }

    public function isV3() {
        return version_compare(VERSION, '3.0', '>=');
    }

    public function versionSafePath($path) {
        if ($this->isV22()) {
            return str_replace('extension/', '', $path);
        } else {
            return $path;
        }
    }

    public function versionSafeViewPath($path) {
        if ($this->isV22()) {
            return str_replace('extension/', '', $path) . '.tpl';
        } else {
            return $path;
        }
    }

    private function getTrustpilotSetting() {
        $this->registry->get('load')->model('setting/setting');
        $model_setting_setting = $this->registry->get('model_setting_setting');
        return $model_setting_setting->getSetting('trustpilot');
    }

    public function getTrustpilotField($key, $json = true) {
        $settings = $this->getTrustpilotSetting();
        if (array_key_exists($key, $settings)) {
            if ($json) {
                return json_decode($settings[$key]);
            }
            return $settings[$key];
        } else {
            return '';
        }
    }

    public function setTrustpilotField($key, $value, $json = true) {
        $this->registry->get('load')->model('setting/setting');
        $model_setting_setting = $this->registry->get('model_setting_setting');

        $settings = $model_setting_setting->getSetting('trustpilot');
        $settings[$key] = $value;
        if (method_exists('ModelSettingSetting', 'editSetting')) {
            $model_setting_setting->editSetting('trustpilot', $settings);
        } else {
            $this->registry->get('load')->model($this->versionSafePath('extension/module/trustpilot/setting'));
            $model_trustpilot_setting = $this->registry->get('model_module_trustpilot_setting');
            $model_trustpilot_setting->editSetting('trustpilot', $settings);
        }
        return $this->getTrustpilotField($key, $json);
    }

    public function loadTrustboxes($settings, $request) {
        if (isset($settings->trustboxes)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $current_url = $protocol . trim($_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"], "/");
            $current_url_trustboxes = $this->getAvailableTrustboxesByPage($settings, $current_url, $request);

            if (isset($request->get['route']) && $request->get['route'] == 'product/product') {
                $current_url_trustboxes = array_merge((array) $this->getAvailableTrustboxesByPage($settings, 'product', $request), (array) $current_url_trustboxes);
            }
            if (isset($request->get['route']) && $request->get['route'] == 'product/category') {
                $current_url_trustboxes = array_merge((array) $this->getAvailableTrustboxesByPage($settings, 'category', $request),(array) $current_url_trustboxes);
            }
            if (!isset($request->get['route']) || $request->get['route'] == 'common/home') {
                $current_url_trustboxes = array_merge((array) $this->getAvailableTrustboxesByPage($settings, 'landing', $request),(array) $current_url_trustboxes);
            }
            
            if (count($current_url_trustboxes) > 0) {
                $settings->trustboxes = $current_url_trustboxes;
                return json_encode($settings);
            }
        }
        return '{"trustboxes":[]}';
    }

    public function getAvailableTrustboxesByPage($settings, $page, $request) {
        $data = array();
        foreach ($settings->trustboxes as $trustbox) {
            if (rtrim($trustbox->page, '/') == $page && $trustbox->enabled == 'enabled') {
                if (isset($request->get['route']) && $request->get['route'] == 'product/product') {
                    $this->registry->get('load')->model('catalog/product');
                    $product = $this->registry->get('model_catalog_product')->getProduct($request->get['product_id']);
                    $skuSelector = json_decode($this->registry->get('config')->get('trustpilot_master_settings_field'))->skuSelector;
                    $sku = ($skuSelector != 'none' && $skuSelector != '' && isset($product[$skuSelector]))
                        ? $product[$skuSelector] 
                        : '';
                    $trustbox->sku = $sku;
                    $trustbox->name = $product['name'];
                } else {
                    unset($trustbox->sku);
                    unset($trustbox->name);
                }
                array_push($data, $trustbox);
            }
        }
        return $data;
    }
    
    public function link($route, $base, $args = '') {
        $url = $base . 'index.php?route=' . (string)$route;
        if ($args) {
            if (is_array($args)) {
                $url .= '&amp;' . http_build_query($args, '', '&amp;');
            } else {
                $url .= str_replace('&', '&amp;', '&' . ltrim($args, '&'));
            }
        }
        return $url;
    }

    public function getBaseUrl() {
        if (defined('HTTP_CATALOG')) {
            return HTTP_CATALOG;
        } else {
            return HTTP_SERVER;
        }
    }
}
