<?php

namespace Kollway\WechatPay;


use Kollway\WechatPay\Data\WxPayConfig;
use Kollway\WechatPay\Exception\HttpException;
use Kollway\WechatPay\Data\WxPayDataBase;
class CurlHttpClient implements HttpClientInterface
{

    /**
     * @inheritdoc
     */
    public function executeHttpRequest($url, $method = self::METHOD_GET, $data = [])
    {
        $ch = curl_init();
        if ($method === self::METHOD_GET) {
            if ($data) {
                $query = $this->buildQuery($data);
                if (strpos($url, '?') !== false) {
                    $url .= $query;
                } else {
                    $url .= '?' . $query;
                }
            }
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $code = $info['http_code'];
        if ($code === 200) {
            return $body;
        } else {
            $error = curl_errno($ch);
            $msg = curl_error($ch);
            throw new HttpException($code, sprintf('%s %s', $error, $msg));
        }
    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml  需要post的xml数据
     * @param string $url  url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second   url执行超时时间，默认30s
     * @throws WxPayException
     */
    public function executeXmlCurl(WxPayDataBase $payDataBase,$url,$method = self::METHOD_POST, $useCert = false, $second = 30)
    {
        $xml = $payDataBase->ToXml();
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        //如果有配置代理这里就设置代理
        if(WxPayConfig::CURL_PROXY_HOST != "0.0.0.0"
            && WxPayConfig::CURL_PROXY_PORT != 0){
            curl_setopt($ch,CURLOPT_PROXY, WxPayConfig::CURL_PROXY_HOST);
            curl_setopt($ch,CURLOPT_PROXYPORT, WxPayConfig::CURL_PROXY_PORT);
        }
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if($useCert == true){
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT, WxPayConfig::getSslCertPath());
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY, WxPayConfig::getSslKeyPath());
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if($data){
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new WxPayException("curl出错，错误码:$error");
        }
    }
    /**
     * Build query string.
     *
     * @param array $params
     * @return string
     */
    protected function buildQuery(array $params)
    {
        $segments = [];
        foreach ($params as $key => $value) {
            $segments[] = $key . '=' . urlencode($value);
        }
        return implode('&', $segments);
    }

}