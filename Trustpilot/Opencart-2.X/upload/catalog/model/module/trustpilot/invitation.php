<?php
require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');
class ModelModuleTrustpilotInvitation extends Model {
    private $helper = null;

    public function __construct($registry) {
        $this->helper = TrustpilotHelper::getInstance($registry);
        parent::__construct($registry);
    }

    public function getProducts($products){
        try {
            $this->load->model('catalog/product');
            $this->load->model('catalog/manufacturer');
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
                $image_width = $this->config->get($prefix . $this->config->get('config_theme') . '_image_cart_width');
                $image_height = $this->config->get($prefix . $this->config->get('config_theme') . '_image_cart_height');
                $image_url = $this->model_tool_image->resize($product_info['image'], $image_width, $image_height);
                $manufacturer = $this->model_catalog_manufacturer->getManufacturer($product_info['manufacturer_id']);
                array_push(
                    $products_array,
                    array(
                        'productUrl' => $product_link,
                        'name' => isset($product_info['name']) ? $product_info['name'] : '',
                        'brand' => isset($manufacturer['name']) ? $manufacturer['name'] : '',
                        'sku' => $sku,
                        'gtin' => $gtin,
                        'mpn' => $mpn,
                        'imageUrl' => $image_url,
                    )
                );
            }
            return $products_array;
        } catch (\Exception $e) {
            $message = 'Unable to get products: ' . $e->getMessage();
            $this->log->write($message);
            return array();
        }
    }

    /**
     * Get order details
     */
    public function getInvitation($order, $collect_product_data = WITH_PRODUCT_DATA) {
        $invitation = null;
        if (!is_null($order)) {
            $invitation = array();
            $invitation['recipientEmail'] = $order['email'];
            $invitation['recipientName'] = $order['firstname'] . ' ' . $order['lastname'];
            $invitation['referenceId'] = $order['order_id'];
            $invitation['source'] = 'OpenCart-' . VERSION;
            $invitation['pluginVersion'] = TRUSTPILOT_PLUGIN_VERSION;
            $invitation['orderStatusId'] = $order['order_status_id'];
            $invitation['orderStatusName'] = $order['order_status'];
            if ($collect_product_data == WITH_PRODUCT_DATA) {
                $this->load->model('account/order');
                $products = $this->getProducts($this->model_account_order->getOrderProducts($order['order_id']));
                $invitation['products'] = $products;
                $invitation['productSkus'] = $this->getSkus($products);
            }
        }
        return $invitation;
    }

    /**
     * Get products skus
     */
    private function getSkus($products) {
        $settings = $this->helper->getTrustpilotField(TRUSTPILOT_MASTER_FIELD);
        $skus = array();
        $skuSelector = $settings->skuSelector;
        if (isset($skuSelector) && $skuSelector != 'none' && $skuSelector != '') {
            foreach ($products as $product) {
                array_push($skus, $product[$skuSelector]);
            }
            return $skus;
        }
    }
}