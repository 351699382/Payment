<?php
/**
 * SuJun (https://github.com/351699382)
 *
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment;

use Payment\PayException;

class Payment
{

    /**
     * 检测最基本的配置
     * @param ConfigInterface $config
     * @param array $reqData
     * @throws PayException
     */
    public function __construct()
    {

    }

    /**
     * 充值
     * @param  [type] $channel [description]
     * @param  [type] $config  [description]
     * @param  [type] $param   [description]
     * @return [type]          [description]
     */
    public function charge($channel, $config, $param)
    {
        // 初始化时，可能抛出异常，再次统一再抛出给客户端进行处理
        switch ($channel) {
            case Config::ALI_CHANNEL_WAP:
                $obj = new \Payment\Api\Ali\Charge\Wap($config, $param);
                break;
            case Config::ALI_CHANNEL_APP:
                $obj = new \Payment\Api\Ali\Charge\App($config, $param);
                break;
            case Config::ALI_CHANNEL_WEB:
                $obj = new \Payment\Api\Ali\Charge\Web($config, $param);
                break;
            case Config::ALI_CHANNEL_QR:
                $obj = new \Payment\Api\Ali\Charge\Qr($config, $param);
                break;
            case Config::ALI_CHANNEL_BAR:
                $obj = new \Payment\Api\Ali\Charge\Bar($config, $param);
                break;

            //微信
            case Config::WX_CHANNEL_QR:
                $obj = new \Payment\Api\Wx\Charge\Qr($config, $param);
                break;
            case Config::WX_CHANNEL_APP:
                $obj = new \Payment\Api\Wx\Charge\App($config, $param);
                break;
            case Config::WX_CHANNEL_WAP:
                $obj = new \Payment\Api\Wx\Charge\Wap($config, $param);
                break;
            case Config::WX_CHANNEL_PUB:
                $obj = new \Payment\Api\Wx\Charge\Pub($config, $param);
                break;

            //银联
            case Config::UNION_CHANNEL_WAP:
                $obj = new \Payment\Api\Union\Charge\Wap($config, $param);
                break;
            case Config::UNION_CHANNEL_APP:
                $obj = new \Payment\Api\Union\Charge\App($config, $param);
                break;
            case Config::UNION_CHANNEL_PC:
                $obj = new \Payment\Api\Union\Charge\Pc($config, $param);
                break;

            default:
                throw new PayException('当前仅支持：支付宝  微信 招商一网通');
        }

        return $obj->handle();
    }

    /**
     * 通知
     * [notify description]
     * @return [type] [description]
     */
    public function notify($channel, $config)
    {
        switch ($channel) {
            case Config::ALI_CHARGE:
                $obj = new \Payment\Api\Ali\Notify\Notify($config);
                break;
            case Config::WX_CHARGE:
                $obj = new \Payment\Api\Wx\Notify\Notify($config);
                break;
            case Config::UNION_CHARGE:
                $obj = new \Payment\Api\Union\Notify\Notify($config);
                break;
            default:
                throw new \Exception('当前不支持' . $channel);
        }
        return $obj->handle();
    }

    /**
     * 回复通知
     * [notify description]
     * @return [type] [description]
     */
    public function replyNotify($channel, $flag)
    {
        switch ($channel) {
            case Config::ALI_CHARGE:
                $obj = new \Payment\Api\Ali\Notify\Notify([]);
                break;
            case Config::WX_CHARGE:
                $obj = new \Payment\Api\Wx\Notify\Notify([]);
                break;
            case Config::UNION_CHARGE:
                $obj = new \Payment\Api\Union\Notify\Notify([]);
                break;
            default:
                throw new \Exception('当前不支持' . $channel);
        }
        return $obj->replyNotify($flag);
    }

}
