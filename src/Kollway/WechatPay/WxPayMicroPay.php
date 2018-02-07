<?php
namespace Kollway\WechatPay;
use Kollway\WechatPay\Data\WxPayDataBase;

/**
 *
 * 刷卡支付输入对象
 * @author widyhu
 *
 */
class WxPayMicroPay extends WxPayUnifiedOrder {

    /**
     * 设置扫码支付授权码，设备读取用户微信中的条码或者二维码信息
     * @param string $value
     **/
    public function SetAuth_code($value)
    {
        $this->values['auth_code'] = $value;
    }
    /**
     * 获取扫码支付授权码，设备读取用户微信中的条码或者二维码信息的值
     * @return 值
     **/
    public function GetAuth_code()
    {
        return $this->values['auth_code'];
    }
    /**
     * 判断扫码支付授权码，设备读取用户微信中的条码或者二维码信息是否存在
     * @return true 或 false
     **/
    public function IsAuth_codeSet()
    {
        return array_key_exists('auth_code', $this->values);
    }

}