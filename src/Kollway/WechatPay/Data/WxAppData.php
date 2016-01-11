<?php

namespace Kollway\WechatPay\Data;
/**
 * Class AppData
 * API替APP签名
 * @package Kollway\WechatPay\Data
 */
class WxAppData extends WxPayDataBase
{
    /**
     * 设置微信分配的公众账号ID
     * @param string $value
     **/
    public function SetAppid($value)
    {
        $this->values['appid'] = $value;
    }
    /**
     * 设置微信支付分配的商户号
     * @param string $value
     **/
    public function SetPartner_id($value)
    {
        $this->values['partnerid'] = $value;
    }
    /**
     * 设置预支付交易会话ID
     * @param string $value
     **/
    public function SetPrepay_id($value)
    {
        $this->values['prepayid'] = $value;
    }
    /**
     * 设置预支付交易会话ID
     * @param string $value
     **/
    public function SetPackage($value)
    {
        $this->values['package'] = $value;
    }
    /**
     * 设置随机字符串
     * @param string $value
     **/
    public function SetNonce_str($value)
    {
        $this->values['noncestr'] = $value;
    }
    /**
     * 设置时间戳
     * @param string $value
     **/
    public function SetTimeStamp($value)
    {
        $this->values['timestamp'] = $value;
    }
    /**
     * 设置签名
     * @param string $value
     **/
    public function SetSign()
    {
        $this->values['sign'] = parent::SetSign();
    }
}