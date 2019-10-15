<?php

class TrustpilotHttpClient
{
    private $apiUrl, $origin, $httpClient, $pluginStatus;

    public function __construct($apiUrl, $registry, $origin)
    {
        include_once 'http_client.php';
        include_once 'trustpilot_plugin_status.php';
        $this->apiUrl = $apiUrl;
        $this->origin = $origin;
        $this->httpClient = new HttpClient($origin);
        $this->pluginStatus = new TrustpilotPluginStatus($registry);
    }

    public function post($url, $data)
    {
        $httpRequest = "POST";
        $response =$this->httpClient->request(
            $url,
            $httpRequest,
            $data
        );
        if ($response['code'] > 250 && $response['code'] < 254) {
            $this->pluginStatus->setPluginStatus($response['code'], $response['data']);
        }
        return $response;
    }

    public function buildUrl($key, $endpoint)
    {
        return $this->apiUrl . $key . $endpoint;
    }

    public function postLog($data) {
        try {
            return $this->post($this->apiUrl . 'log', $data);
        } catch (Exception $e) {
            return false;
        }
    }

    public function postInvitation($key, $data = array())
    {
        return $this->checkStatusAndPost($this->buildUrl($key, '/invitation'), $data);
    }

    public function postBatchInvitations($key, $data = array())
    {
        return $this->checkStatusAndPost($this->buildUrl($key, '/batchinvitations'), $data);
    }

    public function checkStatusAndPost($url, $data = array()) {
        $code = $this->pluginStatus->checkPluginStatus($this->origin);
        if ($code > 250 && $code < 254) {
            return array(
                'code' => $code,
            );
        }
        return $this->post($url, $data);
    }
}
