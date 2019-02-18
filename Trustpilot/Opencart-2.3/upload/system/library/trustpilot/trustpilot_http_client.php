<?php

class TrustpilotHttpClient
{
    public function __construct($apiUrl, $registry, $origin)
    {
        include_once 'http_client.php';
        $this->apiUrl = $apiUrl;
        $this->httpClient = new HttpClient($origin);
    }

    public function post($url, $data)
    {
        $httpRequest = "POST";
        return $this->httpClient->request(
            $url,
            $httpRequest,
            $data
        );
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
        return $this->post($this->buildUrl($key, '/invitation'), $data);
    }

    public function postBatchInvitations($key, $data = array())
    {
        return $this->post($this->buildUrl($key, '/batchinvitations'), $data);
    }
}
