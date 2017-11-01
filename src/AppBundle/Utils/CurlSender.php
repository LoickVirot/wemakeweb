<?php
/**
 * Created by PhpStorm.
 * User: loick
 * Date: 01/11/17
 * Time: 17:40
 */

namespace AppBundle\Utils;

class CurlSender
{
    private $url;
    private $method;
    private $headers;

    /**
     * CurlSender constructor.
     * @param $url
     * @param int|string $method
     * @param array $headers
     * @throws \Exception
     */
    public function __construct($url, $method = CURLOPT_URL, $headers = null)
    {
        if (is_null($url)) {
            throw new \Exception("URL is required");
        }
        $this->url = $url;
        $this->method = $method;
        $this->headers = $headers;

        return $this;
    }

    public function send()
    {
        $curl = curl_init($this->url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if ($this->method != CURLOPT_URL) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($curl, $this->method, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->headers));
        }
        curl_setopt($curl, CURLOPT_USERAGENT, 'Codular Sample cURL Request');
        $curl_response = curl_exec($curl);
        curl_close($curl);

        return $curl_response;
    }
}