<?php
namespace Kollway\WechatPay\Data;

use Kollway\WechatPay\Exception\WechatPayException;
use Kollway\WechatPay\Data\WxPayConfig;

class WxPayDataBase
{
    protected $values = array();

    /** 设置子商户公众账号ID（服务商模式下使用）
     * @param $sub_appid
     */
    public function SetSubAppId($sub_appid) {
        $this->values['sub_appid'] = $sub_appid;
    }

    public function GetSubAppId() {
        return $this->values['sub_appid'];
    }

    public function IsSubAppIdSet() {
        return array_key_exists('sub_appid', $this->values);
    }

    /** 设置子商户号（服务商模式下使用）
     * @param $sub_mch_id
     */
    public function SetSubMchId($sub_mch_id) {
        $this->values['sub_mch_id'] = $sub_mch_id;
    }

    public function GetSubMchId() {
        return $this->values['sub_mch_id'];
    }

    public function IsSubMchIdSet() {
        return array_key_exists('sub_mch_id', $this->values);
    }

    /**
     * 设置签名，详见签名生成算法
     * @param string $value
     **/
    public function SetSign()
    {
        $sign = $this->MakeSign();
        $this->values['sign'] = $sign;
        return $sign;
    }

    /**
     * 获取签名，详见签名生成算法的值
     * @return 值
     **/
    public function GetSign()
    {
        return $this->values['sign'];
    }

    /**
     * 判断签名，详见签名生成算法是否存在
     * @return true 或 false
     **/
    public function IsSignSet()
    {
        return array_key_exists('sign', $this->values);
    }

    /**
     * 输出xml字符
     * @throws WechatPayException
     **/
    public function ToXml()
    {
        if(!is_array($this->values)
            || count($this->values) <= 0)
        {
            throw new WechatPayException("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($this->values as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @throws WechatPayException
     */
    public function FromXml($xml)
    {
        if(!$xml){
            throw new WechatPayException("xml数据异常！");
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $this->values;
    }

    /**
     * 格式化参数格式化成url参数
     */
    public function ToUrlParams()
    {
        $buff = "";
        foreach ($this->values as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function MakeSign()
    {
        //签名步骤一：按字典序排序参数
        ksort($this->values);
        $string = $this->ToUrlParams();
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".WxPayConfig::getKey();
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 获取设置的值
     */
    public function GetValues()
    {
        return $this->values;
    }
}