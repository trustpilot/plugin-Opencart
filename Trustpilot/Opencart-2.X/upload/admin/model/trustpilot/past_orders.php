<?php

require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');

class ModelTrustpilotPastOrders extends Model {
    private $helper = null;
	public function __construct($registry) {
		$this->helper = TrustpilotHelper::getInstance($registry);
		parent::__construct($registry);
	}

	public function getPastOrdersInfo() {
        $syncInProgress = $this->helper->getTrustpilotField(TRUSTPILOT_SYNC_IN_PROGRESS, false);
        $showInitial = $this->helper->getTrustpilotField(TRUSTPILOT_SHOW_PAST_ORDERS_INITIAL, false);
        if ($syncInProgress === 'false') {
            $synced_orders = (int)$this->helper->getTrustpilotField(TRUSTPILOT_PAST_ORDERS_FIELD, false);
            $failed_orders = $this->helper->getTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD);

            $failed_orders_result = array();
            foreach ($failed_orders as $key => $value) {
                $item = array(
                    'referenceId' => $key,
                    'error' => $value
                );
                array_push($failed_orders_result, $item);
            }

            return array(
                'pastOrders' => array(
                    'synced' => $synced_orders,
                    'unsynced' => count($failed_orders_result),
                    'failed' => $failed_orders_result,
                    'syncInProgress' => $syncInProgress === 'true',
                    'showInitial' => $showInitial === 'true',
                )
            );
        } else {
            return array(
                'pastOrders' => array(
                    'syncInProgress' => $syncInProgress === 'true',
                    'showInitial' => $showInitial === 'true',
                )
            );
        }
    }
}