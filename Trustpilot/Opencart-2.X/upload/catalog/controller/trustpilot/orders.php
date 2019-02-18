<?php

require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');

class TrustpilotOrders {

    private $context, $helper = null;

    public function __construct($context, $registry)
    {
        $this->context = $context;
        $this->helper = TrustpilotHelper::getInstance($registry);
    }

    public function getOrder() {
        try {
            $this->context->load->model('setting/setting');
            $settings = $this->context->model_setting_setting->getSetting('trustpilot');
            $general_settings = isset($settings[TRUSTPILOT_MASTER_FIELD]) ? json_decode($settings[TRUSTPILOT_MASTER_FIELD])->general : null;
            if (isset($this->context->session->data['order_id']) && isset($general_settings->key)) {
                $order_id = $this->context->session->data['order_id'];
                $this->context->load->model('checkout/order');
                $order = $this->context->model_checkout_order->getOrder($order_id);
                $this->context->load->model($this->helper->versionSafePath('extension/module/trustpilot/invitation'));

                $data = $this->context->model_module_trustpilot_invitation->getInvitation($order, WITH_PRODUCT_DATA);
                $data['hook'] = 'opencart_thankyou';

                if (isset($this->context->session->data['guest'])) {
                    $guest = $this->context->session->data['guest'];
                    $data['recipientEmail']= $guest['email'];
                    $data['recipientName'] = $guest['firstname'] . ' ' . $guest['lastname'];
                }

                if (!in_array('trustpilotOrderConfirmed', $general_settings->mappedInvitationTrigger)) {
                    $data['payloadType'] = 'OrderStatusUpdate';
                }
                return $data;
            }
        } catch (Exception $e) {
            $error = array('message' => $e->getMessage());
            $data = array('error' => $error);
            return $data;
        }
    }
}
