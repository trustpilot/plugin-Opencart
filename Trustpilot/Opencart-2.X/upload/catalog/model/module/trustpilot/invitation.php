<?php
require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');
class ModelModuleTrustpilotInvitation extends Model {
    private $helper = null;

    public function __construct($registry) {
        $this->helper = TrustpilotHelper::getInstance($registry);
        parent::__construct($registry);
    }

    public function getProducts($products, $currency){
        try {
            $this->load->model('catalog/product');
            $this->load->model('tool/image');
            $products_array = array();
            $master_settings = json_decode($this->config->get('trustpilot_master_settings_field'));
            $gtinSelector = $master_settings->gtinSelector;
            $skuSelector = $master_settings->skuSelector;
            $mpnSelector = $master_settings->mpnSelector;
            foreach ($products as $p) {
                $product_info = $this->model_catalog_product->getProduct($p['product_id']);
                $sku = $skuSelector != 'none' && $skuSelector != '' && isset($product_info[$skuSelector]) ? $product_info[$skuSelector] : '';
                $gtin =  $gtinSelector != 'none' && $gtinSelector != '' && isset($product_info[$gtinSelector]) ? $product_info[$gtinSelector] : '';
                $mpn = $mpnSelector != 'none' && $mpnSelector != '' && isset($product_info[$mpnSelector]) ? $product_info[$mpnSelector] : '';
                $product_link = $this->url->link('product/product&product_id=' . $p['product_id']);
                $prefix = $this->helper->isV3() ? 'theme_' : '';
                $configTheme = $this->config->get('config_theme');
                $image_width = $this->config->get($prefix . (isset($configTheme) ? $configTheme : 'config') . '_image_popup_width');
                $image_height = $this->config->get($prefix . (isset($configTheme) ? $configTheme : 'config') . '_image_popup_height');
                $image_url = $this->model_tool_image->resize($product_info['image'], $image_width, $image_height);
                array_push(
                    $products_array,
                    array( 
                        'price' => isset($product_info['price'])  ? $product_info['price'] : 0,
                        'categories' => $this->getProductCategories($product_info['product_id']),
                        'description' => isset($product_info['description']) ? strip_tags(htmlspecialchars_decode($product_info['description'])) : '',
                        'images' => $this->getAllImages($product_info['product_id'], $image_width, $image_height),
                        'tags' => $product_info['tag'] ? explode(',', $product_info['tag']) : '',
                        'meta' => array(
                            'title' => isset($product_info['meta_title']) ? $product_info['meta_title'] : '',
                            'keywords' => isset($product_info['meta_keyword']) ? $product_info['meta_keyword'] : '',
                            'description' => isset($product_info['meta_description']) ? $product_info['meta_description'] : '',
                        ),
                        'currency' => $currency,
                        'manufacturer' => isset($product_info['manufacturer']) ? $product_info['manufacturer'] : '',
                        'productUrl' => $product_link,
                        'name' => isset($product_info['name']) ? $product_info['name'] : '',
                        'brand' => isset($product_info['manufacturer']) ? $product_info['manufacturer'] : '',
                        'sku' => $sku,
                        'gtin' => $gtin,
                        'mpn' => $mpn,
                        'imageUrl' => $image_url,
                    )
                );
            }
            return $products_array;
        } catch (Exception $e) {
            $this->helper->log('Unable to get products: ' . $e->getMessage());
            return array();
        }
    }

    private function getProductCategories($product_id) {
        $this->load->model('catalog/product');
        $this->load->model('catalog/category');
        $categoryNames = array();
        $categories = $this->model_catalog_product->getCategories($product_id);
        foreach ($categories as $category) {
            if ($category) {
                $category_info = $this->model_catalog_category->getCategory($category['category_id']);
                array_push($categoryNames, $category_info['name']);
            }
        }
        return $categoryNames;
    }

    private function getAllImages($product_id, $image_width, $image_height) {
        $images = array();
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        foreach ($this->model_catalog_product->getProductImages($product_id) as $image) {
            $url = $this->model_tool_image->resize($image['image'], $image_width, $image_height);
            array_push($images, $url);
        }
        return $images;
    }

    /**
     * Get order details
     */
    public function getInvitation($order, $collect_product_data = WITH_PRODUCT_DATA, $hook = '') {
        $invitation = null;
        if (!is_null($order)) {
            $currency = isset($order['currency_code']) ? $order['currency_code'] : '';
            $invitation = array();
            $invitation['recipientEmail'] = isset($order['email']) ? $order['email'] : '';
            $invitation['recipientName'] = isset($order['firstname']) && isset($order['lastname']) ? $order['firstname'] . ' ' . $order['lastname'] : '';
            $invitation['referenceId'] = isset($order['order_id']) ? $order['order_id'] : '';
            $invitation['source'] = 'OpenCart-' . VERSION;
            $invitation['pluginVersion'] = TRUSTPILOT_PLUGIN_VERSION;
            $invitation['orderStatusId'] = isset($order['order_status_id']) ? $order['order_status_id'] : '';
            $invitation['orderStatusName'] = isset($order['order_status']) ? $order['order_status'] : '';
            if (isset($hook)) { $invitation['hook'] = $hook; }
            if ($collect_product_data == WITH_PRODUCT_DATA) {
                $this->load->model('checkout/order');
                if (method_exists($this->model_checkout_order, 'getOrderProducts')) {
                    $products = $this->getProducts($this->model_checkout_order->getOrderProducts($order['order_id']), $currency);
                } else {
                    $this->load->model('account/order');
                    $products = $this->getProducts($this->model_account_order->getOrderProducts($order['order_id']), $currency);
                }
                $invitation['products'] = $products;
                $invitation['productSkus'] = $this->getSkus($products);
            }
            $invitation['templateParams'] = array(
                $this->config->get('config_store_id'),
                $this->config->get('config_language_id'),
            );
        }
        return $invitation;
    }

    /**
     * Get products skus
     */
    private function getSkus($products) {
        $skus = array();
        foreach ($products as $product) {
            array_push($skus, $product['sku']);
        }
        return $skus;
    }
}
