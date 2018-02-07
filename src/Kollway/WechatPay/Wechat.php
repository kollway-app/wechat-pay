<?php

namespace Kollway\WechatPay;


use Kollway\WechatPay\Data\WxPayCloseOrder;
use Kollway\WechatPay\Data\WxPayConfig;
use Kollway\WechatPay\Data\WxPayOrderQuery;
use Kollway\WechatPay\Data\WxPayRefund;
use Kollway\WechatPay\Data\WxPayReport;
use Kollway\WechatPay\Data\WxPayResults;
use Kollway\WechatPay\Exception\WechatPayException;

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

    //是否启用沙盒测试（仅部分API可用）
    protected $is_sandbox;

    /**
     * The http client to send http request.
     *
     * @var HttpClientInterface
     */
    protected $httpClient;

    const METHOD_POST = "POST";
    const METHOD_GET = "GET";
    const CODE_URL = "http://paysdk.weixin.qq.com/example/qrcode.php?data=";
    /**
     * Wechat constructor.
     *
     * @param string $appId
     * @param string $mchId
     * @param string $key
     */
    public function __construct()
    {
        $this->appId = WxPayConfig::getAppId();
        $this->mchId = WxPayConfig::getMchID();
        $this->key = WxPayConfig::getKey();
        $this->is_sandbox = false;
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

    public function setIsSandBox($is_sandbox) {
        $this->is_sandbox = $is_sandbox;
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
     *
     * 查询订单，WxPayOrderQuery中out_trade_no、transaction_id至少填一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayOrderQuery $inputObj
     * @param int $timeOut
     * @throws WechatPayException
     * @return 成功时返回，其他抛异常
     */
    public function orderQuery($inputObj, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        //检测必填参数
        if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
            throw new WechatPayException("订单查询接口中，out_trade_no、transaction_id至少填一个！");
        }

        if($this->is_sandbox) {
            $url = "https://api.mch.weixin.qq.com/sandboxnew/pay/orderquery";
            $this->setupSandboxEnv();
        }

        if(!$inputObj->IsAppidSet()){
            $inputObj->SetAppid($this->appId);//公众账号ID
        }
        if(!$inputObj->IsMch_idSet()){
            $inputObj->SetMch_id($this->mchId);//商户号
        }
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串
        $inputObj->SetSign();//签名
        $startTimeStamp = self::getMillisecond();//请求开始时间
        $response = $this->getHttpClient()->executeXmlCurl($inputObj,$url,self::METHOD_POST,false,$timeOut);
        $result = WxPayResults::Init($response);
        self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 关闭订单，WxPayCloseOrder中out_trade_no必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayCloseOrder $inputObj
     * @param int $timeOut
     * @throws WechatPayException
     * @return 成功时返回，其他抛异常
     */
    public function closeOrder($inputObj, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/pay/closeorder";
        //检测必填参数
        if(!$inputObj->IsOut_trade_noSet()) {
            throw new WechatPayException("订单查询接口中，out_trade_no必填！");
        }
        $inputObj->SetAppid($this->appId);//公众账号ID
        $inputObj->SetMch_id($this->mchId);//商户号
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

        $inputObj->SetSign();//签名

        $startTimeStamp = self::getMillisecond();//请求开始时间
        $response = $this->getHttpClient()->executeXmlCurl($inputObj,$url,self::METHOD_POST,false,$timeOut);
        $result = WxPayResults::Init($response);
        self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 申请退款，WxPayRefund中out_trade_no、transaction_id至少填一个且
     * out_refund_no、total_fee、refund_fee、op_user_id为必填参数
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayRefund $inputObj
     * @param int $timeOut
     * @throws WechatPayException
     * @return 成功时返回，其他抛异常
     */
    public function refund($inputObj, $timeOut = 6)
    {
        $url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
        //检测必填参数
        if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
            throw new WechatPayException("退款申请接口中，out_trade_no、transaction_id至少填一个！");
        }else if(!$inputObj->IsOut_refund_noSet()){
            throw new WechatPayException("退款申请接口中，缺少必填参数out_refund_no！");
        }else if(!$inputObj->IsTotal_feeSet()){
            throw new WechatPayException("退款申请接口中，缺少必填参数total_fee！");
        }else if(!$inputObj->IsRefund_feeSet()){
            throw new WechatPayException("退款申请接口中，缺少必填参数refund_fee！");
        }
        $inputObj->SetAppid($this->appId);//公众账号ID
        $inputObj->SetMch_id($this->mchId);//商户号
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

        $inputObj->SetSign();//签名
        $startTimeStamp = self::getMillisecond();//请求开始时间
        $response = $this->getHttpClient()->executeXmlCurl($inputObj,$url,self::METHOD_POST,true,$timeOut);
        $result = WxPayResults::Init($response);
        self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    /**
     *
     * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayUnifiedOrder $inputObj
     * @param int $timeOut
     * @throws WechatPayException
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
            $inputObj->SetNotify_url(WxPayConfig::getNotifyUrl());//异步通知url
        }

        $inputObj->SetAppid(WxPayConfig::getAppId());//公众账号ID
        if(!$inputObj->IsMch_idSet()){
            $inputObj->SetMch_id(WxPayConfig::getMchID());//商户号
        }
        $inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
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
     * 扫码支付模式二
     * @param $url 统一下单native类型返回的code_url
     * 生成二维码链接
     */
    public function getCodeUrl($url) {
        if(!empty($url)){
            return self::CODE_URL.urlencode($url);
        }
        return $url;
    }

    /**
     * 获取毫秒级别的时间戳
     */
    protected static function getMillisecond()
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
    protected static function reportCostTime($url, $startTimeStamp, $data)
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
     * @throws WechatPayException
     * @return 成功时返回，其他抛异常
     */
    private static function report($inputObj, $timeOut = 1)
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
        $inputObj->SetAppid(WxPayConfig::getAppId());//公众账号ID
        $inputObj->SetMch_id(WxPayConfig::getMchID());//商户号
        $inputObj->SetUser_ip($_SERVER['REMOTE_ADDR']);//终端ip
        $inputObj->SetTime(date("YmdHis"));//商户上报时间
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

        $inputObj->SetSign();//签名

        $startTimeStamp = self::getMillisecond();//请求开始时间
        $response = self::getHttpClient()->executeXmlCurl($inputObj,$url,self::METHOD_POST,false, $timeOut);
        return $response;
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

    /**
     * 提交被扫支付API（发起刷卡支付API）
     * 收银员使用扫码设备读取微信用户刷卡授权码以后，二维码或条码信息传送至商户收银台，
     * 由商户收银台或者商户后台调用该接口发起支付。
     * WxPayWxPayMicroPay中body、out_trade_no、total_fee、auth_code参数必填
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WxPayMicroPay $inputObj
     * @param int $timeOut
     * @throws WechatPayException
     */
    public function micropay($inputObj, $timeOut = 6) {
        $url = "https://api.mch.weixin.qq.com/pay/micropay";

        //检测必填参数
        if(!$inputObj->IsBodySet()) {
            throw new WechatPayException("提交被扫支付API接口中，缺少必填参数body！");
        } else if(!$inputObj->IsOut_trade_noSet()) {
            throw new WechatPayException("提交被扫支付API接口中，缺少必填参数out_trade_no！");
        } else if(!$inputObj->IsTotal_feeSet()) {
            throw new WechatPayException("提交被扫支付API接口中，缺少必填参数total_fee！");
        } else if(!$inputObj->IsAuth_codeSet()) {
            throw new WechatPayException("提交被扫支付API接口中，缺少必填参数auth_code！");
        }

        if($this->is_sandbox) {
            $url = "https://api.mch.weixin.qq.com/sandboxnew/pay/micropay";
            $this->setupSandboxEnv();
        }

        $inputObj->SetAppid(WxPayConfig::getAppId());//公众账号ID
        if(!$inputObj->IsMch_idSet()){
            $inputObj->SetMch_id(WxPayConfig::getMchID());//商户号
        }
        $inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

        $inputObj->SetSign();//签名
        $response = $this->getHttpClient()->executeXmlCurl($inputObj,$url);
        $startTimeStamp = self::getMillisecond();//请求开始时间
        $result = WxPayResults::Init($response);
        self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间

        return $result;
    }

    private function setupSandboxEnv() {
        $url = 'https://api.mch.weixin.qq.com/sandboxnew/pay/getsignkey';
        $param = new WxPayUnifiedOrder();
        $param->SetMch_id(WxPayConfig::getMchID());//商户号
        $param->SetNonce_str(self::getNonceStr());//随机字符串
        $param->SetSign();//签名
        $response = $this->getHttpClient()->executeXmlCurl($param,$url);
        if($response) {
            $wxpay_results = new WxPayResults();
            $wxpay_results->FromXml($response);
            $result = $wxpay_results->GetValues();
            if($result && isset($result['sandbox_signkey']) && $result['sandbox_signkey']) {
                $sandbox_signkey = $result['sandbox_signkey'];
                WxPayConfig::setConfig(WxPayConfig::getAppId(), WxPayConfig::getMchID(), $sandbox_signkey);
            }
        }
    }
}