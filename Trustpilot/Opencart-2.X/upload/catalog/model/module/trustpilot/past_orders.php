<?php

require_once(DIR_SYSTEM . 'library/trustpilot/config.php');
require_once(DIR_SYSTEM . 'library/trustpilot/helper.php');
require_once(DIR_SYSTEM . 'library/trustpilot/trustpilot_http_client.php');

class ModelModuleTrustpilotPastOrders extends Model {
    private $helper, $trustpilot_api = null;
    
	public function __construct($registry) {
		$this->helper = TrustpilotHelper::getInstance($registry);
        $this->trustpilot_api = new TrustpilotHttpClient(TRUSTPILOT_API_URL, $registry, $this->helper->getBaseUrl());
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

    public function syncPastOrders($period_in_days) {
        $this->helper->setTrustpilotField(TRUSTPILOT_SYNC_IN_PROGRESS, 'true');
        $this->helper->setTrustpilotField(TRUSTPILOT_SHOW_PAST_ORDERS_INITIAL, 'false');
        try {
            $key = $this->helper->getTrustpilotField(TRUSTPILOT_MASTER_FIELD)->general->key;
            $collect_product_data = WITHOUT_PRODUCT_DATA;
            if (!is_null($key)) {
                $this->helper->setTrustpilotField(TRUSTPILOT_PAST_ORDERS_FIELD, 0, false);

                $pageId = 1;
                $post_batch = $this->getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId);
                while ($post_batch) {
                    set_time_limit(30);
                    $batch = null;
                    if (!is_null($post_batch)) {
                        $batch['invitations'] = $post_batch;
                        $batch['type'] = $collect_product_data;
                        $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                        $code = $this->handleTrustpilotResponse($response, $batch);

                        if ($code == 202) {
                            $collect_product_data = WITH_PRODUCT_DATA;
                            $batch['invitations'] = $this->getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId);
                            $batch['type'] = $collect_product_data;
                            $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                            $this->handleTrustpilotResponse($response, $batch);
                        }
                        if ($code < 200 || $code > 202) {
                            $this->helper->setTrustpilotField(TRUSTPILOT_SHOW_PAST_ORDERS_INITIAL, 'true');
                            $this->helper->setTrustpilotField(TRUSTPILOT_SYNC_IN_PROGRESS, 'false');
                            $this->helper->setTrustpilotField(TRUSTPILOT_PAST_ORDERS_FIELD, 0, false);
                            $this->helper->setTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD, '{}');
                        }
                    }
                    $pageId = $pageId + 1;
                    $post_batch = $this->getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId);
                }
            }
        } catch (Exception $e) {
            $this->helper->log('Failed to sync past orders. Error: ' . $e->getMessage(), $key);
        }
        $this->helper->setTrustpilotField(TRUSTPILOT_SYNC_IN_PROGRESS, 'false');
    }

    public function resyncPastOrders() {
        $this->helper->setTrustpilotField(TRUSTPILOT_SYNC_IN_PROGRESS, 'true');
        try {
            $failed_orders_object = $this->helper->getTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD);
            $key = $this->helper->getTrustpilotField(TRUSTPILOT_MASTER_FIELD)->general->key;
            $collect_product_data = WITHOUT_PRODUCT_DATA;
            if (!is_null($key)) {
                $failed_orders_array = array();
                foreach ($failed_orders_object as $id => $value) {
                    array_push($failed_orders_array, $id);
                }

                $chunked_failed_orders = array_chunk($failed_orders_array, 10, true);
                foreach ($chunked_failed_orders as $failed_orders_chunk) {
                    set_time_limit(30);
                    $post_batch = $this->getInvitationsByOrderIds($collect_product_data, $failed_orders_chunk);

                    $batch = null;
                    $batch['invitations'] = $post_batch;
                    $batch['type'] = $collect_product_data;
                    $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                    $code = $this->handleTrustpilotResponse($response, $batch);

                    if ($code == 202) {
                        $collect_product_data = WITH_PRODUCT_DATA;
                        $batch['invitations'] = $this->getInvitationsByOrderIds($collect_product_data, $failed_orders_chunk);
                        $batch['type'] = $collect_product_data;
                        $response = $this->trustpilot_api->postBatchInvitations($key, $batch);
                        $code = $this->handleTrustpilotResponse($response, $batch);
                    }
                    if ($code < 200 || $code > 202) {
                        $this->helper->setTrustpilotField(TRUSTPILOT_SYNC_IN_PROGRESS, 'false');
                        return;
                    }
                }
            }
        } catch (Exception $e) {
            $this->helper->log('Failed to resync failed orders. Error: ' . $e->getMessage(), $key);
        }
        $this->helper->setTrustpilotField(TRUSTPILOT_SYNC_IN_PROGRESS, 'false');
    }

    private function getInvitationsByOrderIds($collect_product_data, $order_ids) {
        $args = array(
            'filter_order_ids' => $order_ids,
        );

        $orders = $this->getPastOrders($args);
        return $this->getInvitations($orders, $collect_product_data);
    }

    private function getInvitationsForPeriod($period_in_days, $collect_product_data, $pageId) {
        $date = new DateTime();
        $limit = 10;
        $args = array(
            'filter_date_added_from' => $date->setTimestamp(time() - (86400 * $period_in_days))->format('Y-m-d H:i:s'),
            'filter_order_status' => $this->helper->getTrustpilotField(TRUSTPILOT_MASTER_FIELD)->pastOrderStatuses,
            'limit' => $limit,
            'pageId' => $pageId,
            'start' => ($limit * $pageId) - $limit,
        );

        $paged_orders = $this->getPastOrders($args);
        return $this->getInvitations($paged_orders, $collect_product_data);
    }

    private function getInvitations($orders, $collect_product_data) {
        $invitations = array();
        $this->load->model($this->helper->versionSafePath('extension/module/trustpilot/invitation'));
        foreach ($orders as $order) {
            $invitation = $this->model_module_trustpilot_invitation->getInvitation($order, $collect_product_data, 'past-orders');
            if (!is_null($invitation)) {
                array_push($invitations, $invitation);
            }
        }

        return $invitations;
    }

    private function getPastOrders($data) {
        $sql =
            "SELECT
              o.order_id,
              o.firstname,
              o.lastname,
              o.total,
              o.currency_code,
              (SELECT os.name FROM " . DB_PREFIX . "order_status os WHERE os.order_status_id = o.order_status_id AND os.language_id = '" . (int)$this->config->get('config_language_id') . "') AS order_status,
              o.order_status_id,
              o.email
            FROM `" . DB_PREFIX . "order` o";

        if (isset($data['filter_order_status'])) {
            $sql .= " WHERE o.order_status_id IN (" . implode(",", $data['filter_order_status']) . ")";
        } else {
            $sql .= " WHERE o.order_status_id > '0'";
        }

        if (isset($data['filter_order_ids'])) {
            $sql .= " AND o.order_id IN (" . implode(",", $data['filter_order_ids']) . ")";
        }

        if (isset($data['filter_date_added_from'])) {
			$sql .= " AND DATE(o.date_added) > DATE('" . $this->db->escape($data['filter_date_added_from']) . "')";
        }

        $sql .= " ORDER BY o.order_id";

        if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

        if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }

		$query = $this->db->query($sql);

		return $query->rows;
    }

    private function handleTrustpilotResponse($response, $post_batch) {
        $synced_orders = $this->helper->getTrustpilotField(TRUSTPILOT_PAST_ORDERS_FIELD, false);
        $failed_orders = $this->helper->getTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD);

        // all succeeded
        if ($response['code'] == 201 && count($response['data']) == 0) {
            $this->trustpilotSaveSyncedOrders($synced_orders, $post_batch['invitations']);
            $this->trustpilotSaveFailedOrders($failed_orders, $post_batch['invitations']);
        }

        // all/some failed
        if ($response['code'] == 201 && count($response['data']) > 0) {
            $failed_order_ids = $this->selectColumn($response['data'], 'referenceId');
            $succeeded_orders = array_filter($post_batch['invitations'], function ($invitation) use ($failed_order_ids)  {
                return !(in_array($invitation['referenceId'], $failed_order_ids));
            });

            $this->trustpilotSaveSyncedOrders($synced_orders, $succeeded_orders);
            $this->trustpilotSaveFailedOrders($failed_orders, $succeeded_orders, $response['data']);
        }

        return $response['code'];
    }

    private function selectColumn($array, $column) {
        if (version_compare(phpversion(), '7.2.10', '<')) {
            $newarr = array();
            foreach ($array as $row) {
                array_push($newarr, $row->{$column});
            }
            return $newarr;
        } else {
            return array_column($array, $column);
        }
    }

    private function trustpilotSaveSyncedOrders($synced_orders, $new_orders) {
        if (count($new_orders) > 0) {
            $this->helper->setTrustpilotField(TRUSTPILOT_PAST_ORDERS_FIELD, $synced_orders + count($new_orders), false);
        }
    }

    private function trustpilotSaveFailedOrders($failed_orders, $succeeded_orders, $new_failed_orders = array()) {
        $update_needed = false;
        if (count($succeeded_orders) > 0) {
            $update_needed = true;
            foreach ($succeeded_orders as $order) {
                if (isset($failed_orders->{$order['referenceId']})) {
                    unset($failed_orders->{$order['referenceId']});
                }
            }
        }

        if (count($new_failed_orders) > 0) {
            $update_needed = true;
            foreach ($new_failed_orders as $failed_order) {
                $failed_orders->{$failed_order->referenceId} = base64_encode($failed_order->error);
            }
        }

        if ($update_needed) {
            $this->helper->setTrustpilotField(TRUSTPILOT_FAILED_ORDERS_FIELD, json_encode($failed_orders));
        }
    }
}
