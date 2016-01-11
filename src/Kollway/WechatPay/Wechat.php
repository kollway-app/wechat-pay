<?php

namespace Kollway\WechatPay;


use Kollway\WechatPay\Data\WxPayReport;
use Kollway\WechatPay\Data\WxPayResults;
use Kollway\WechatPay\Exception\HttpException;
use Kollway\WechatPay\Exception\WechatPayException;
use Kollway\WechatPay\Data\WxPayConfig;
use Kollway\WechatPay\Data\WxPayDataBase;
class Wechat
{

    /**
     * The wechat payment app id.
     *
     * @var string
     */
    protected $appId;

    /**
     * The wechat merchant id.
     *
     * @var string
     */
    protected $mchId;

    /**
     * The secret key to sign and verify params.
     *
     * @var string
     */
    protected $key;

    /**
     * The http client to send http request.
     *
     * @var HttpClientInterface
     */
    protected $httpClient;

    const METHOD_POST = "POST";
    const METHOD_GET = "GET";
    /**
     * Wechat constructor.
     *
     * @param string $appId
     * @param string $mchId
     * @param string $key
     */
    public function __construct()
    {
        $this->appId = WxPayConfig::APPID;
        $this->mchId = WxPayConfig::MCHID;
        $this->key = WxPayConfig::KEY;
    }

    /**
     * Get the app id.
     *
     * @return string
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * Get the merchant id.
     *
     * @return string
     */
    public function getMchId()
    {
        return $this->mchId;
    }

    /**
     * Get the http client.
     *
     * @return HttpClientInterface
     */
    public function getHttpClient()
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new CurlHttpClient();
        }
        return $this->httpClient;
    }

    /**
     * Set the http client.
     *
     * @param HttpClientInterface $httpClient
     */
    public function setHttpClient(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Create unified order instance.
     *
     * @param string $out_trade_no
     * @param string $device_info
     * @param string $body
     * @param string $total_fee
     * @param string $spbill_create_ip
     * @param string $notify_url
     * @param string $trade_type
     * @return UnifiedOrder
     */
    public function createUnifiedOrder($out_trade_no, $device_info, $body, $total_fee, $spbill_create_ip, $notify_url, $trade_type)
    {
        $params = compact('out_trade_no', 'device_info', 'body', 'total_fee', 'spbill_create_ip', 'notify_url', 'trade_type');
        $params = array_merge($params, [
            'appid' => $this->appId,
            'mch_id' => $this->mchId,
        ]);
        $unifiedOrder = new UnifiedOrder($this);
        $unifiedOrder->add($params);
        return $unifiedOrder;
    }

    /**
     *
     * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayUnifiedOrder $inputObj
     * @param int $timeOut
     * @throws WxPayException
     * @return 成功时返回，其他抛异常
     */
    public function unifiedOrder($inputObj, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //检测必填参数
        if(!$inputObj->IsOut_trade_noSet()) {
            throw new WechatPayException("缺少统一支付接口必填参数out_trade_no！");
        }else if(!$inputObj->IsBodySet()){
            throw new WechatPayException("缺少统一支付接口必填参数body！");
        }else if(!$inputObj->IsTotal_feeSet()) {
            throw new WechatPayException("缺少统一支付接口必填参数total_fee！");
        }else if(!$inputObj->IsTrade_typeSet()) {
            throw new WechatPayException("缺少统一支付接口必填参数trade_type！");
        }

        //关联参数
        if($inputObj->GetTrade_type() == "JSAPI" && !$inputObj->IsOpenidSet()){
            throw new WechatPayException("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
        }
        if($inputObj->GetTrade_type() == "NATIVE" && !$inputObj->IsProduct_idSet()){
            throw new WechatPayException("统一支付接口中，缺少必填参数product_id！trade_type为NATIVE时，product_id为必填参数！");
        }

        //异步通知url未设置，则使用配置文件中的url
        if(!$inputObj->IsNotify_urlSet()){
            $inputObj->SetNotify_url(WxPayConfig::NOTIFY_URL);//异步通知url
        }

        $inputObj->SetAppid(WxPayConfig::APPID);//公众账号ID
        if(!$inputObj->IsMch_idSet()){
            $inputObj->SetMch_id(WxPayConfig::MCHID);//商户号
        }
        $inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
        //$inputObj->SetSpbill_create_ip("1.1.1.1");
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串
        //签名
        $inputObj->SetSign();
        $response = $this->getHttpClient()->executeXmlCurl($inputObj,$url);
        $startTimeStamp = self::getMillisecond();//请求开始时间
        $result = WxPayResults::Init($response);
        self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间
        return $result;
    }
    /**
     * 获取毫秒级别的时间戳
     */
    private static function getMillisecond()
    {
        //获取毫秒的时间戳
        $time = explode ( " ", microtime () );
        $time = $time[1] . ($time[0] * 1000);
        $time2 = explode( ".", $time );
        $time = $time2[0];
        return $time;
    }

    /**
     *
     * 上报数据， 上报的时候将屏蔽所有异常流程
     * @param string $usrl
     * @param int $startTimeStamp
     * @param array $data
     */
    private static function reportCostTime($url, $startTimeStamp, $data)
    {
        //如果不需要上报数据
        if(WxPayConfig::REPORT_LEVENL == 0){
            return;
        }
        //如果仅失败上报
        if(WxPayConfig::REPORT_LEVENL == 1 &&
            array_key_exists("return_code", $data) &&
            $data["return_code"] == "SUCCESS" &&
            array_key_exists("result_code", $data) &&
            $data["result_code"] == "SUCCESS")
        {
            return;
        }

        //上报逻辑
        $endTimeStamp = self::getMillisecond();
        $objInput = new WxPayReport();
        $objInput->SetInterface_url($url);
        $objInput->SetExecute_time_($endTimeStamp - $startTimeStamp);
        //返回状态码
        if(array_key_exists("return_code", $data)){
            $objInput->SetReturn_code($data["return_code"]);
        }
        //返回信息
        if(array_key_exists("return_msg", $data)){
            $objInput->SetReturn_msg($data["return_msg"]);
        }
        //业务结果
        if(array_key_exists("result_code", $data)){
            $objInput->SetResult_code($data["result_code"]);
        }
        //错误代码
        if(array_key_exists("err_code", $data)){
            $objInput->SetErr_code($data["err_code"]);
        }
        //错误代码描述
        if(array_key_exists("err_code_des", $data)){
            $objInput->SetErr_code_des($data["err_code_des"]);
        }
        //商户订单号
        if(array_key_exists("out_trade_no", $data)){
            $objInput->SetOut_trade_no($data["out_trade_no"]);
        }
        //设备号
        if(array_key_exists("device_info", $data)){
            $objInput->SetDevice_info($data["device_info"]);
        }

        try{
            self::report($objInput);
        } catch (WechatPayException $e){
            //不做任何处理
        }
    }

    /**
     *
     * 测速上报，该方法内部封装在report中，使用时请注意异常流程
     * WxPayReport中interface_url、return_code、result_code、user_ip、execute_time_必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayReport $inputObj
     * @param int $timeOut
     * @throws WxPayException
     * @return 成功时返回，其他抛异常
     */
    public static function report($inputObj, $timeOut = 1)
    {
        $url = "https://api.mch.weixin.qq.com/payitil/report";
        //检测必填参数
        if(!$inputObj->IsInterface_urlSet()) {
            throw new WechatPayException("接口URL，缺少必填参数interface_url！");
        } if(!$inputObj->IsReturn_codeSet()) {
        throw new WechatPayException("返回状态码，缺少必填参数return_code！");
    } if(!$inputObj->IsResult_codeSet()) {
        throw new WechatPayException("业务结果，缺少必填参数result_code！");
    } if(!$inputObj->IsUser_ipSet()) {
        throw new WechatPayException("访问接口IP，缺少必填参数user_ip！");
    } if(!$inputObj->IsExecute_time_Set()) {
        throw new WechatPayException("接口耗时，缺少必填参数execute_time_！");
    }
        $inputObj->SetAppid(WxPayConfig::APPID);//公众账号ID
        $inputObj->SetMch_id(WxPayConfig::MCHID);//商户号
        $inputObj->SetUser_ip($_SERVER['REMOTE_ADDR']);//终端ip
        $inputObj->SetTime(date("YmdHis"));//商户上报时间
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

        $inputObj->SetSign();//签名
        $xml = $inputObj->ToXml();

        $startTimeStamp = self::getMillisecond();//请求开始时间
        $response = self::getHttpClient()->executeXmlCurl($url,self::METHOD_POST,false, $timeOut);
        return $response;
    }

    /**
     * Create verifier with xml.
     *
     * @param string $xml
     * @return NotifyVerifier
     */
    public function createVerifier($xml)
    {
        return new NotifyVerifier($this, $this->createArrayFromXML($xml));
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }
}