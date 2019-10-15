<?php

require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');

class TrustpilotPluginStatus
{
    const SUCCESSFUL_STATUS = 200;

    private $helper = null;

    public function __construct($registry) {
        $this->helper = TrustpilotHelper::getInstance($registry);
    }

    public function checkPluginStatus($origin) {
        $settings = $this->helper->getTrustpilotField(TRUSTPILOT_PLUGIN_STATUS_FIELD);
        if (in_array(parse_url($origin, PHP_URL_HOST), $settings->blockedDomains)) {
            return $settings->pluginStatus;
        }
        return self::SUCCESSFUL_STATUS;
    }

    public function setPluginStatus($status, $blockedDomains) {
        $new_value = array(
            'pluginStatus' => $status,
            'blockedDomains' => $blockedDomains ?: array(),
        );
        $this->helper->setTrustpilotField(TRUSTPILOT_PLUGIN_STATUS_FIELD, json_encode($new_value));
    }
}
