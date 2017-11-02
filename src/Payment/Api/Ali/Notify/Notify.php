<?php
/**
 * SuJun (https://github.com/351699382)
 * 支付宝回调通知
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */

namespace Payment\Api\Ali\Notify;

use Payment\Api\Ali\Config;
use Payment\PayException;
use Payment\Utils\ArrayUtil;
use Payment\Utils\Rsa2Encrypt;
use Payment\Utils\RsaEncrypt;

class Notify
{
    /**
     * 配置
     * @var [type]
     */
    protected $config;

    /**
     * AliNotify constructor.
     * @param array $config
     * @throws PayException
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);
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
            throw new \Exception("获取原始数据为空", 1);
            return false;
        }

        // 检查异步通知返回的数据是否有误
        $checkRet = $this->checkNotifyData($notifyData);
        if ($checkRet === false) {
            // 失败，就返回错误
            throw new \Exception("验证数据失败", 1);
            return false;
        }
        return $notifyData;

        // // 回调商户的业务逻辑
        // $flag = $this->callback($notify, $notifyData);
        // if ($flag) {
        //     $msg = 'OK';
        // } else {
        //     $msg = '商户逻辑调用出错';
        // }
        // // 返回响应值
        // return $this->replyNotify($flag, $msg);
    }

    /**
     * 获取移除通知的数据  并进行简单处理（如：格式化为数组）
     *
     * 如果获取数据失败，返回false
     *
     * @return array|boolean
     */
    public function getNotifyData()
    {
        $data = empty($_POST) ? $_GET : $_POST;
        if (empty($data) || !is_array($data)) {
            return false;
        }

        return $data;
    }

    /**
     * 检查异步通知的数据是否合法
     *
     * 如果检查失败，返回false
     *
     * @param array $data  由 $this->getNotifyData() 返回的数据
     * @return boolean
     */
    public function checkNotifyData(array $data)
    {
        //$data['trade_status']
        $status = $this->getTradeStatus($data);
        if ($status !== 'success') {
            // 如果不是交易成功状态，直接返回错误，
            return false;
        }

        // 检查签名
        $flag = $this->verifySign($data);

        return $flag;
    }

    /**
     * 向客户端返回必要的数据
     * @param array $data 回调机构返回的回调通知数据
     * @return array|false
     */
    protected function getRetData(array $data)
    {
        if ($this->config->returnRaw) {
            $data['channel'] = Config::ALI_CHARGE;
            return $data;
        }

        $retData = [
            'notify_time'       => ArrayUtil::get($data, 'notify_time'),
            'notify_type'       => ArrayUtil::get($data, 'notify_type'),
            'notify_id'         => ArrayUtil::get($data, 'notify_id'),
            'app_id'            => ArrayUtil::get($data, 'app_id'),
            'transaction_id'    => ArrayUtil::get($data, 'trade_no'),
            'order_no'          => ArrayUtil::get($data, 'out_trade_no'),
            'out_biz_no'        => ArrayUtil::get($data, 'out_biz_no'),
            'buyer_id'          => ArrayUtil::get($data, 'buyer_id'),
            'buyer_account'     => ArrayUtil::get($data, 'buyer_logon_id'),
            'seller_id'         => ArrayUtil::get($data, 'seller_id'),
            'seller_email'      => ArrayUtil::get($data, 'seller_email'),
            'trade_state'       => $this->getTradeStatus($data['trade_status']),
            'amount'            => ArrayUtil::get($data, 'total_amount'),
            'receipt_amount'    => ArrayUtil::get($data, 'receipt_amount'), // 商家在交易中实际收到的款项，单位为元
            'invoice_amount'    => ArrayUtil::get($data, 'invoice_amount'), // 用户在交易中支付的可开发票的金额
            'pay_amount'        => ArrayUtil::get($data, 'buyer_pay_amount'), // 用户在交易中支付的金额
            'point_amount'      => ArrayUtil::get($data, 'point_amount'), // 使用集分宝支付的金额
            'refund_fee'        => ArrayUtil::get($data, 'refund_fee'), // 总退款金额
            'subject'           => ArrayUtil::get($data, 'subject'),
            'body'              => ArrayUtil::get($data, 'body'),
            'trade_create_time' => ArrayUtil::get($data, 'gmt_create'), // 交易创建时间
            'pay_time'          => ArrayUtil::get($data, 'gmt_payment'), // 交易付款时间
            'trade_refund_time' => ArrayUtil::get($data, 'gmt_refund'), // 交易退款时间
            'trade_close_time'  => ArrayUtil::get($data, 'gmt_close'), // 交易关闭时间
            'channel'           => Config::ALI_CHARGE,
        ];

        // 检查是否存在用户自定义参数
        if (isset($data['passback_params']) && !empty($data['passback_params'])) {
            $retData['return_param'] = $data['passback_params'];
        }
        // 支付成功的各个渠道金额信息
        if (isset($data['fund_bill_list']) && !empty($data['fund_bill_list'])) {
            $retData['fund_bill_list'] = \GuzzleHttp\json_decode($data['fund_bill_list'], true);
        }
        // 优惠券信息
        if (isset($data['voucher_detail_list']) && !empty($data['voucher_detail_list'])) {
            $retData['voucher_detail_list'] = \GuzzleHttp\json_decode($data['voucher_detail_list'], true);
        }

        return $retData;
    }

    /**
     * 支付宝，成功返回 ‘success’   失败，返回 ‘fail’
     * @param boolean $flag 每次返回的bool值
     * @param string $msg 错误原因  后期考虑记录日志
     * @return string
     */
    public function replyNotify($flag, $msg = '')
    {
        if ($flag) {
            return 'success';
        } else {
            return 'fail';
        }
    }

    /**
     * 返回统一的交易状态
     * @param $status
     * @return string
     */
    protected function getTradeStatus($status)
    {
        if (in_array($status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return 'success';
        } else {
            return 'fail';
        }
    }

    /**
     * 检查支付宝数据 签名是否被篡改
     * @param array $data
     * @return boolean
     */
    protected function verifySign(array $data)
    {
        $signType = strtoupper($data['sign_type']);
        $sign     = $data['sign'];

        // 1. 剔除sign与sign_type参数
        $values = ArrayUtil::removeKeys($data, ['sign', 'sign_type']);
        //  2. 移除数组中的空值
        $values = ArrayUtil::paraFilter($values);
        // 3. 对待签名参数数组排序
        $values = ArrayUtil::arraySort($values);
        // 4. 将排序后的参数与其对应值，组合成“参数=参数值”的格式,用&字符连接起来
        $preStr = ArrayUtil::createLinkstring($values);

        if ($signType === 'RSA') {
            // 使用rsa方式
            $rsa = new RsaEncrypt($this->config->rsaAliPubKey);
            return $rsa->rsaVerify($preStr, $sign);
        } elseif ($signType === 'RSA2') {
            $rsa = new Rsa2Encrypt($this->config->rsaAliPubKey);
            return $rsa->rsaVerify($preStr, $sign);
        } else {
            return false;
        }
    }
}
