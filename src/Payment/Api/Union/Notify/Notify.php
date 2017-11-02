<?php
/**
 * SuJun (https://github.com/351699382)
 * 回调处理
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */

namespace Payment\Api\Union\Notify;

use Payment\Api\Union\Config;
use Payment\PayException;
use Payment\Utils\ArrayUtil;
use Payment\Utils\CertUtil;

class Notify
{
    /**
     * 配置
     * @var [type]
     */
    protected $config;

    /**
     * @param array $config
     * @throws PayException
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * 主要任务，验证返回的数据是否正确
     * @param PayNotifyInterface $notify
     * @return mixed
     */
    final public function handle()
    {
        // 获取异步通知的数据
        $notifyData = $this->getNotifyData();
        if ($notifyData === false) {
            // 失败，就返回错误
            return $this->replyNotify(false, '获取通知数据失败');
        }

        // 检查异步通知返回的数据是否有误
        $checkRet = $this->checkNotifyData($notifyData);
        if ($checkRet === false) {
            // 失败，就返回错误
            return $this->replyNotify(false, '返回数据验签失败，可能数据被篡改');
        }

        return $notifyData;
        // 返回响应值
        //return $this->replyNotify($flag, $msg);
    }

    /**
     * 获取返回的异步通知数据
     * @return array|bool
     */
    public function getNotifyData()
    {
/*        // php://input 带来的内存压力更小
$data = @file_get_contents('php://input'); // 等同于微信提供的：$GLOBALS['HTTP_RAW_POST_DATA']
// 将xml数据格式化为数组
$arrData = DataParser::toArray($data);
if (empty($arrData)) {
return false;
}

// 移除值中的空格  xml转化为数组时，CDATA 数据会被带入额外的空格。
$arrData = ArrayUtil::paraFilter($arrData);*/

        return $_POST;
    }

    /**
     * 检查异步通知的数据是否正确
     * @param array $data
     * @return boolean
     */
    public function checkNotifyData(array $data)
    {

        if ($data["respCode"] == "03"
            || $data["respCode"] == "04"
            || $data["respCode"] == "05") {
            //后续需发起交易状态查询交易确定交易状态
            //TODO

        } else {
            //return false;
        }

        // 检查返回数据签名是否正确
        return $this->verifySign($data);

    }

    /**
     * 检查返回的数据是否被篡改过
     * @param array $retData
     * @return boolean
     */
    protected function verifySign(array $retData)
    {
        return $this->validate($retData);
    }

    /**
     * 验签
     * @param $params 应答数组
     * @return 是否成功
     */
    public function validate($params)
    {
        $isSuccess = false;
        if ($params['signMethod'] == '01') {
            $signature_str = $params['signature'];
            unset($params['signature']);
            $params_str = ArrayUtil::createLinkStringUnion($params, true, false);
            if ($params['version'] == '5.0.0') {
                // 公钥
                $public_key     = CertUtil::getVerifyCertByCertId($params['certId']);
                $signature      = base64_decode($signature_str);
                $params_sha1x16 = sha1($params_str, false);
                $isSuccess      = openssl_verify($params_sha1x16, $signature, $public_key, OPENSSL_ALGO_SHA1);
            } else if ($params['version'] == '5.1.0') {
                $strCert = $params['signPubKeyCert'];
                $strCert = CertUtil::verifyAndGetVerifyCert($strCert, $this->config['app_root_cert'], $this->config['app_middle_cert'], $this->config['use_sandbox']);
                if ($strCert == null) {
                    $isSuccess = false;
                } else {
                    $params_sha256x16 = hash('sha256', $params_str);
                    $signature        = base64_decode($signature_str);
                    $isSuccess        = openssl_verify($params_sha256x16, $signature, $strCert, "sha256");
                }
            } else {
                $isSuccess = false;
            }
        } else {
            $isSuccess = $this->validateBySecureKey($params, $this->config['secure_key']);
        }
        return $isSuccess;
    }

    public function validateBySecureKey($params, $secureKey)
    {
        $isSuccess     = false;
        $signature_str = $params['signature'];
        unset($params['signature']);
        $params_str = ArrayUtil::createLinkStringUnion($params, true, false);
        if ($params['signMethod'] == '11') {
            $params_before_sha256 = hash('sha256', $secureKey);
            $params_before_sha256 = $params_str . '&' . $params_before_sha256;
            $params_after_sha256  = hash('sha256', $params_before_sha256);
            $isSuccess            = $params_after_sha256 == $signature_str;
        } else if ($params['signMethod'] == '12') {
            //TODO SM3
            //$logger->LogError("sm3没实现");
            $isSuccess = false;
        } else {
            //$logger->LogError("signMethod不正确");
            $isSuccess = false;
        }

        return $isSuccess;
    }

    /**
     * 获取向客户端返回的数据
     * @param array $data
     *
     * @return array
     */
    protected function getRetData(array $data)
    {

    }

    /**
     * 处理完后返回的数据格式
     * @param bool $flag
     * @param string $msg 通知信息，错误原因
     * @return string
     */
    public function replyNotify($flag, $msg = 'OK')
    {
        // 默认为成功
        $result = [
            'return_code' => 'SUCCESS',
            'return_msg'  => 'OK',
        ];
        if (!$flag) {
            // 失败
            $result = [
                'return_code' => 'FAIL',
                'return_msg'  => $msg,
            ];
        }
        //header("status: 500 Not Found");
        //状态码为200即表示成功
        return $result;
    }
}
