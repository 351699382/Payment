<?php
/**
 * SuJun (https://github.com/351699382)
 * 支付宝移动支付接口
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Ali\Charge;

use Payment\Api\Ali\Config;
use Payment\PayException;
use Payment\Utils\Rsa2Encrypt;
use Payment\Utils\RsaEncrypt;
use Payment\Utils\StrUtil;
use Payment\Utils\ArrayUtil;

abstract class ChargeAbstract
{
    /**
     * 配置信息
     * @var [type]
     */
    protected $config;

    protected $sendData;

    /**
     * BaseData constructor.
     * @param ConfigInterface $config
     * @param array $reqData
     * @throws PayException
     */
    public function __construct(array $config, array $param)
    {
        //检测配置
        $this->initConfig($config);

        $config = new Config($config);

        //配置贼值
        $this->config = $config->toArray();

        $param          = $this->initData($param);
        $this->sendData = $param;
    }

    /**
     * 初始化配置文件
     * @param array $config
     * @throws PayException
     */
    abstract protected function initConfig(array $config);

    /**
     * 检测最基本需要传入的参数
     * 检查传入的支付业务参数是否正确
     * 如果输入参数不符合规范，直接抛出异常
     * @param  array  $param [description]
     * @return [type]        [description]
     */
    protected function initData(array $param)
    {
        // 检查 商品名称 与 商品描述
        if (empty($param['subject'])) {
            throw new PayException('必须提供 商品的标题/交易标题/订单标题/订单关键字 等');
        }

        // 检查订单号是否合法
        if (empty($param['order_no']) || mb_strlen($param['order_no']) > 64) {
            throw new PayException('订单号不能为空，并且长度不能超过64位');
        }

        // 检查金额不能低于0.01
        if ($param['amount'] < 0.01) {
            throw new PayException('支付金额不能低于 ' . '0.01' . ' 元');
        }

        // 检查商品类型
        if (empty($param['goods_type'])) {
        } elseif (!in_array($param['goodsType'], [0, 1])) {
            throw new PayException('商品类型可取值为：0-虚拟类商品  1-实物类商品');
        }

        // 返回参数进行urlencode编码
        if (!empty($param['return_param']) && !is_string($param['return_param'])) {
            throw new PayException('回传参数必须是字符串');
        } else {
            $param['return_param'] = urlencode($param['return_param']);
        }

        return $param;
    }

    /**
     * 签名算法实现
     * @param string $signStr
     * @return string
     */
    protected function makeSign(array $param)
    {

        $values = ArrayUtil::removeKeys($param, ['sign']);

        $values = ArrayUtil::arraySort($values);

        // 支付宝新版本  需要转码
        foreach ($values as &$value) {
            $value = StrUtil::characet($value, $this->config['charset']);
        }

        $signStr = ArrayUtil::createLinkstring($values);

        switch ($this->config['signType']) {
            case 'RSA':
                $rsa = new RsaEncrypt($this->config['rsaPrivateKey']);

                $sign = $rsa->encrypt($signStr);
                break;
            case 'RSA2':
                $rsa = new Rsa2Encrypt($this->config['rsaPrivateKey']);

                $sign = $rsa->encrypt($signStr);
                break;
            default:
                $sign = '';
        }

        return $sign;
    }
}
