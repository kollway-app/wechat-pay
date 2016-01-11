<?php

namespace Kollway\WechatPay\Exception;


class WechatPayException extends \Exception
{
    public function errorMessage()
    {
        return $this->getMessage();
    }
}