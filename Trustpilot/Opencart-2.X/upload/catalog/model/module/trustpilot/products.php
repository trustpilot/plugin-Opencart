<?php
class ModelModuleTrustpilotProducts extends Model {
    public function __construct($registry) {
        parent::__construct($registry);
    }

    public function checkSkus($skuSelector) {
        $page_id = 0;
        $limit = 20;
        $productsWithoutSku = array();

        $this->load->model('catalog/product');

        $total_products = $this->model_catalog_product->getTotalProducts();
        $pages_count = ceil($total_products / $limit);
        while ($page_id < $pages_count) {
            $args = array(
                'start' => $page_id * $limit,
                'limit' => $limit
            );
            $results = $this->model_catalog_product->getProducts($args);
            foreach ($results as $product) {
                $productSku = $skuSelector != 'none' && $skuSelector != '' && isset($product[$skuSelector]) ? $product[$skuSelector] : '';
                if (empty($productSku)) {
                    $data = array(
                        'id' => $product['product_id'],
                        'name' => $product['name'],
                        'productFrontendUrl' => $this->url->link('product/product', 'product_id=' . $product['product_id'])
                    );
                    array_push($productsWithoutSku, $data);
                }
            }
            $page_id++;
        }
        return array(
            'skuScannerResults' => $productsWithoutSku
        );
    }
}
