<?php
namespace Kollway\WechatPay\Notify;

use Kollway\WechatPay\Data\WxPayConfig;
use Kollway\WechatPay\Data\WxPayResults;
use Kollway\WechatPay\Exception\WechatPayException;
use Kollway\WechatPay\Notify\WxPayNotifyReply;
/**
 *
 * 回调基础类
 *
 */
class WxPayNotify extends WxPayNotifyReply
{
    /**
     *
     * 回调入口
     * @param bool $needSign  是否需要签名输出
     */
    final public function Handle($needSign = true)
    {
        $msg = "OK";
        //当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
        $result = $this->notify(array($this, 'NotifyCallBack'), $msg);
        if($result == false){
            $this->SetReturn_code("FAIL");
            $this->SetReturn_msg($msg);
            $this->ReplyNotify(false);
            return;
        } else {
            //该分支在成功回调到NotifyCallBack方法，处理完成之后流程
            $this->SetReturn_code("SUCCESS");
            $this->SetReturn_msg("OK");
        }
        $this->ReplyNotify($needSign);
    }

    /**
     *
     * 回调方法入口，子类可重写该方法
     * 注意：
     * 1、微信回调超时时间为2s，建议用户使用异步处理流程，确认成功之后立刻回复微信服务器
     * 2、微信服务器在调用失败或者接到回包为非确认包的时候，会发起重试，需确保你的回调是可以重入
     * @param array $data 回调解释出的参数
     * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    public function NotifyProcess($data, &$msg)
    {
        //TODO 用户基础该类之后需要重写该方法，成功的时候返回true，失败返回false
        return true;
    }

    /**
     *
     * notify回调方法，该方法中需要赋值需要输出的参数,不可重写
     * @param array $data
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    final public function NotifyCallBack($data)
    {
        $msg = "OK";
        $result = $this->NotifyProcess($data, $msg);

        if($result == true){
            $this->SetReturn_code("SUCCESS");
            $this->SetReturn_msg("支付成功");
        } else {
            $this->SetReturn_code("FAIL");
            $this->SetReturn_msg("支付失败");
        }
        return $result;
    }

    /**
     *
     * 回复通知
     * @param bool $needSign 是否需要签名输出
     */
    final private function ReplyNotify($needSign = true)
    {
        //如果需要签名
        if($needSign == true &&
            $this->GetReturn_code() == "SUCCESS")
        {
            $this->SetSign();
        }
        $this->echoNotify($this->ToXml());
    }
    /**
     * 直接输出xml
     * @param string $xml
     */
    public static function echoNotify($xml)
    {
        echo $xml;
    }
    /**
     *
     * 支付结果通用通知
     * @param function $callback
     * 直接回调函数使用方法: notify(you_function);
     * 回调类成员函数方法:notify(array($this, you_function));
     * $callback  原型为：function function_name($data){}
     */
    public function notify($callback, &$msg)
    {
        //获取通知的数据
        //$GLOBALS 有些配置可能有限制
        //$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml = file_get_contents("php://input");

        $multi_config_array = WxPayConfig::getMultiConfigArray();
        if(!$multi_config_array) {
            $default_config = array(
                'app_id' => WxPayConfig::getAppId(),
                'mchid' => WxPayConfig::getMchID(),
                'api_token' => WxPayConfig::getKey(),
            );
            $multi_config_array = array($default_config);
        }

        $verify_success = false;
        foreach ($multi_config_array as $config) {
            WxPayConfig::setConfig($config['app_id'], $config['mchid'], $config['api_token']);
            //如果返回成功则验证签名
            try {
                $result = WxPayResults::Init($xml);
                if($result) {
                    $verify_success = true;
                    break ;
                }
            } catch (WechatPayException $e){
                $msg = $e->errorMessage();
                $verify_success = false;
            }
        }

        if(!$verify_success) {
            return false;
        }

        return call_user_func($callback, $result);
    }

}