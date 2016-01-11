<?php

namespace Kollway\WechatPay;


use Kollway\WechatPay\Data\WxPayDataBase;

interface HttpClientInterface
{

    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';

    /**
     * Make request.
     *
     * @param string $url
     * @param string $method
     * @param mixed $data
     * @return mixed
     * @throws Exception\HttpException
     */
    public function executeHttpRequest($url, $method = self::METHOD_GET, $data = []);

    public function executeXmlCurl(WxPayDataBase $wxPayDataBase,$url,$method = self::METHOD_POST,$useCert = false, $second = 30);
}