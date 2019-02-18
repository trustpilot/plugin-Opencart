<?php
require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');
require_once(DIR_SYSTEM . 'library/trustpilot/trustpilot_http_client.php');

class ControllerExtensionModuleTrustpilot extends Controller {
    private $helper = null;

    public function __construct($registry) {
        $this->helper = TrustpilotHelper::getInstance($registry);
        parent::__construct($registry);
    }

    /**
     * A wrapper function for 2.2 support
     */
    public function legacyOrderStatusChange($route, $output, $order_id, $order_status_id) {
        $data = array($order_id, $order_status_id);
        $this->orderStatusChange($route, $data);
    }

    /**
     * Order status change. Backend side
     */
    public function orderStatusChange($route, $data) {
        $settings = $this->helper->getTrustpilotField(TRUSTPILOT_MASTER_FIELD);
        $trustpilot_api = new TrustpilotHttpClient(TRUSTPILOT_API_URL, $this->registry, $this->helper->getBaseUrl());
        if (isset($settings->general->key) && $this->trustpilotCompatible()) {
            $key = $settings->general->key;
            try {
                $order_id = $data[0];
                $order_status_id = $data[1];

                $this->load->model('checkout/order');
                $this->load->model($this->helper->versionSafePath('extension/module/trustpilot/invitation'));

                $order = $this->model_checkout_order->getOrder($order_id);
                $common = array(
                    'hook' => 'opencart_order_status_changed'
                );
                $invitation = array_merge($this->model_extension_module_trustpilot_invitation->getInvitation($order, WITHOUT_PRODUCT_DATA), $common);

                if (in_array($order_status_id, $settings->general->mappedInvitationTrigger)) {

                    $response = $trustpilot_api->postInvitation($key, $invitation);

                    if ($response['code'] == 202) {
                        $invitation = array_merge($this->model_extension_module_trustpilot_invitation->getInvitation($order, WITH_PRODUCT_DATA), $common);
                        $response = $trustpilot_api->postInvitation($key, $invitation);
                    }

                    $this->handleSingleResponse($response, $invitation);
                } else {
                    $invitation['payloadType'] = 'OrderStatusUpdate';
                    $response = $trustpilot_api->postInvitation($key, $invitation);
                }
            } catch (Exception $e) {
                $error = array('message' => $e->getMessage());
                $data = array('error' => $error);
                $trustpilot_api->postInvitation($key, $data);
            }
        }
    }

    /**
	 * Updating post orders lists after automatic invitation
	 */
    private function handleSingleResponse($response, $order) {
        try {
            $synced_orders = (int) $this->helper->getTrustpilotField(TRUSTPILOT_PAST_ORDERS_FIELD, false);
            $failed_orders = $this->helper->getTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD);

            if ($response['code'] == 201) {
                $this->helper->setTrustpilotField(TRUSTPILOT_PAST_ORDERS_FIELD, $synced_orders + 1, false);  
                if (isset($failed_orders->{$order['referenceId']})) {
                    unset($failed_orders->{$order['referenceId']});
                    $this->helper->setTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD, json_encode($failed_orders));
                }
            } else {
                $failed_orders->{$order['referenceId']} = base64_encode('Automatic invitation sending failed');
                $this->helper->setTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD, json_encode($failed_orders));
            }
        } catch (Exception $e) {
            $message = 'Unable to update past orders for order id: Error: ' . $e->getMessage();
            $this->log->write($message);
        }
    }

    private function trustpilotCompatible() {
        return version_compare(phpversion(), '5.2.0') >= 0 && function_exists('curl_init');
    }
}
