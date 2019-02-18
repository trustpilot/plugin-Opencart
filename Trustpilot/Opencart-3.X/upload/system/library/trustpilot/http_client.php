<?php

class HttpClient
{
    const HTTP_REQUEST_TIMEOUT = 3;
    public $origin = null;

    public function __construct($origin)
    {
        $this->origin = $origin;
    }

    public function request($url, $httpRequest, $data = null, $params = array(), $timeout = self::HTTP_REQUEST_TIMEOUT)
    {
        $ch = curl_init();
        $this->setCurlOptions($ch, $httpRequest, $data, $timeout);
        $url = $this->buildParams($url, $params);
        curl_setopt($ch, CURLOPT_URL, $url);
        $content = curl_exec($ch);
        $responseData = $this->jsonDecoder($content);
        $responseInfo = curl_getinfo($ch);
        $responseCode = $responseInfo['http_code'];
        curl_close($ch);
        $response = array();
        $response['code'] = $responseCode;
        if (is_object($responseData) || is_array($responseData)) {
            $response['data'] = $responseData;
        }
        return $response;
    }

    private function jsonEncoder($data)
	{
		if (function_exists('json_encode'))
			return json_encode($data);
		elseif (method_exists('Tools', 'jsonEncode'))
			return Tools::jsonEncode($data);
    }

    private function jsonDecoder($data)
	{
		if (function_exists('json_decode'))
			return json_decode($data);
		elseif (method_exists('Tools', 'jsonDecode'))
			return Tools::jsonDecode($data);
    }

    private function setCurlOptions($ch, $httpRequest, $data, $timeout)
    {
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        if ($httpRequest == 'POST') {
            $encoded_data = $this->jsonEncoder($data);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'content-type: application/json',
                'Content-Length: ' . strlen($encoded_data),
                'Origin: ' . $this->origin,
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded_data);
        } elseif ($httpRequest == 'GET') {
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }
    }

    private function buildParams($url, $params = array()) {
        if (!empty($params) && is_array($params)) {
            $url .= '?'.http_build_query($params);
        }
        return $url;
    }
}
