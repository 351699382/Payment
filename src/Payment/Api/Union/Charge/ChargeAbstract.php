<?php
/**
 * SuJun (https://github.com/351699382)
 * 银联支付接口
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Api\Union\Charge;

use Payment\PayException;
use Payment\Utils\ArrayUtil;
use Payment\Utils\CertUtil;

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
        //配置贼值
        $this->config = $config;

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
        return $param;
    }

    /**
     * 签名算法实现
     * @param string $signStr
     * @return string
     */
    protected function makeSign(&$param)
    {
        if ($param['signMethod'] == '01') {
            return $this->signByCertInfo($param, $this->config['app_sign_pfx'], $this->config['app_sign_pwd']);
        } else {
            return $this->signBySecureKey($param, null);
        }
    }

    public static function signBySecureKey(&$params, $secureKey)
    {
        if ($params['signMethod'] == '11') {
            // 转换成key=val&串
            $params_str           = createLinkString($params, true, false);
            $params_before_sha256 = hash('sha256', $secureKey);
            $params_before_sha256 = $params_str . '&' . $params_before_sha256;
            $params_after_sha256  = hash('sha256', $params_before_sha256);
            $params['signature']  = $params_after_sha256;
        } else if ($params['signMethod'] == '12') {
            //TODO SM3
            throw new \Exception("signMethod=12未实现", 1);
        } else {
            throw new \Exception("signMethod不正确", 1);
        }
        return true;
    }
    public static function signByCertInfo(&$params, $cert_path, $cert_pwd)
    {
        if (isset($params['signature'])) {
            unset($params['signature']);
        }
        $result = false;
        if ($params['signMethod'] == '01') {
            //证书ID
            $params['certId'] = CertUtil::getSignCertIdFromPfx($cert_path, $cert_pwd);
            $private_key      = CertUtil::getSignKeyFromPfx($cert_path, $cert_pwd);
            // 转换成key=val&串
            $params_str = ArrayUtil::createLinkStringUnion($params, true, false);

            if ($params['version'] == '5.0.0') {
                $params_sha1x16 = sha1($params_str, false);
                // 签名
                $result = openssl_sign($params_sha1x16, $signature, $private_key, OPENSSL_ALGO_SHA1);
                if ($result) {
                    $signature_base64    = base64_encode($signature);
                    $params['signature'] = $signature_base64;
                } else {
                    throw new \Exception(">>>>>签名失败<<<<<<<", 1);
                }
            } else if ($params['version'] == '5.1.0') {
                //sha256签名摘要
                $params_sha256x16 = hash('sha256', $params_str);
                // 签名
                $result = openssl_sign($params_sha256x16, $signature, $private_key, 'sha256');
                if ($result) {
                    $signature_base64    = base64_encode($signature);
                    $params['signature'] = $signature_base64;
                } else {
                    throw new \Exception(">>>>>签名失败<<<<<<<", 1);
                }
            } else {
                throw new \Exception("wrong version: "+$params['version'], 1);
            }
        } else {
            throw new \Exception("wrong version: "+$params['version'], 1);
        }
        return true;
    }
}
