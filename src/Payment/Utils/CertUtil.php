<?php
/**
 * SuJun (https://github.com/351699382)
 * 银联相关工具函数
 * @link      https://github.com/351699382
 * @copyright Copyright (c) 2017 SuJun
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt (Apache License)
 */
namespace Payment\Utils;

const COMPANY = "中国银联股份有限公司";
class Cert
{
    public $cert;
    public $certId;
    public $key;
}

// 内存泄漏问题说明：
//     openssl_x509_parse疑似有内存泄漏，暂不清楚原因，可能和php、openssl版本有关，估计有bug。
//     windows下试过php5.4+openssl0.9.8，php7.0+openssl1.0.2都有这问题。mac下试过也有问题。
//     不过至今没人来反馈过这个问题，所以不一定真有泄漏？或者因为增长量不大所以一般都不会遇到问题？
//     也有别人汇报过bug：https://bugs.php.net/bug.php?id=71519
//
// 替代解决方案：
//     方案1. 所有调用openssl_x509_parse的地方都是为了获取证书序列号，可以尝试把证书序列号+证书/key以别的方式保存，
//            从其他地方（比如数据库）读序列号，而不直接从证书文件里读序列号。
//     方案2. 代码改成执行脚本的方式执行，这样执行完一次保证能释放掉所有内存。
//     方案3. 改用下面的CertSerialUtil取序列号，
//            此方法仅用了几个测试和生产的证书做过测试，不保证没bug，所以默认注释掉了。如发现有bug或者可优化的地方可自行修改代码。
//            注意用了bcmath的方法，*nix下编译时需要 --enable-bcmath。http://php.net/manual/zh/bc.installation.php

class CertUtil
{

    private static $signCerts      = array();
    private static $encryptCerts   = array();
    private static $verifyCerts    = array();
    private static $verifyCerts510 = array();

    private static function initSignCert($certPath, $certPwd)
    {
        $pkcs12certdata = file_get_contents($certPath);
        if ($pkcs12certdata === false) {
            throw new \Exception($certPath . "file_get_contents fail。", 1);
        }
        if (openssl_pkcs12_read($pkcs12certdata, $certs, $certPwd) == false) {
            throw new \Exception($certPath . ", pwd[" . $certPwd . "] openssl_pkcs12_read fail。", 1);
        }
        $cert     = new Cert();
        $x509data = $certs['cert'];
        if (!openssl_x509_read($x509data)) {
            throw new \Exception($certPath . " openssl_x509_read fail。", 1);
        }
        $certdata     = openssl_x509_parse($x509data);
        $cert->certId = $certdata['serialNumber'];
        $cert->key    = $certs['pkey'];
        $cert->cert   = $x509data;

        CertUtil::$signCerts[$certPath] = $cert;
    }

    public static function getSignKeyFromPfx($certPath = null, $certPwd = null)
    {
        if ($certPath == null) {
            throw new \Exception("certPath不能为空", 1);
        }

        if (!array_key_exists($certPath, CertUtil::$signCerts)) {
            self::initSignCert($certPath, $certPwd);
        }
        return CertUtil::$signCerts[$certPath]->key;
    }

    public static function getSignCertIdFromPfx($certPath = null, $certPwd = null)
    {

        if ($certPath == null) {
            throw new \Exception("certPath不能为空", 1);
        }

        if (!array_key_exists($certPath, CertUtil::$signCerts)) {
            self::initSignCert($certPath, $certPwd);
        }
        return CertUtil::$signCerts[$certPath]->certId;
    }

    private static function initEncryptCert($cert_path)
    {
        $x509data = file_get_contents($cert_path);
        if ($x509data === false) {
            throw new \Exception($cert_path . " file_get_contents fail。", 1);
        }
        if (!openssl_x509_read($x509data)) {
            throw new \Exception($cert_path . " openssl_x509_read fail。", 1);
        }
        $cert                               = new Cert();
        $certdata                           = openssl_x509_parse($x509data);
        $cert->certId                       = $certdata['serialNumber'];
        $cert->key                          = $x509data;
        CertUtil::$encryptCerts[$cert_path] = $cert;
    }

    /**
    ; 验签中级证书（证书位于assets/测试环境证书/文件夹下，请复制到d:/certs文件夹）
    acpsdk.middleCert.path=D:/certs/acp_test_middle.cer
    ; 验签根证书（证书位于assets/测试环境证书/文件夹下，请复制到d:/certs文件夹）
    acpsdk.rootCert.path=D:/certs/acp_test_root.cer
     * @param  [type] $certBase64String [description]
     * @param  [type] $rootCertPath     [description]
     * @param  [type] $middleCertPath   [description]
     * @return [type]                   [description]
     */
    public static function verifyAndGetVerifyCert($certBase64String, $rootCertPath, $middleCertPath, $ifValidateCNName)
    {

        if (array_key_exists($certBase64String, CertUtil::$verifyCerts510)) {
            return CertUtil::$verifyCerts510[$certBase64String];
        }

        if (empty($rootCertPath) || empty($middleCertPath)) {
            throw new \Exception("rootCertPath or middleCertPath is none, exit initRootCert", 1);
        }
        openssl_x509_read($certBase64String);
        $certInfo = openssl_x509_parse($certBase64String);

        $cn = CertUtil::getIdentitiesFromCertficate($certInfo);
        //上线前改
        if (!$ifValidateCNName) {
            if (COMPANY != $cn) {
                throw new \Exception("cer owner is not CUP:" . $cn, 1);
            }
        } else if (COMPANY != $cn && "00040000:SIGN" != $cn) {
            throw new \Exception("cer owner is not CUP:" . $cn, 10);
        }

        $from      = date_create('@' . $certInfo['validFrom_time_t']);
        $to        = date_create('@' . $certInfo['validTo_time_t']);
        $now       = date_create(date('Ymd'));
        $interval1 = $from->diff($now);
        $interval2 = $now->diff($to);
        if ($interval1->invert || $interval2->invert) {
            throw new \Exception("signPubKeyCert has expired", 1);
        }

        $result = openssl_x509_checkpurpose($certBase64String, X509_PURPOSE_ANY,
            array(
                $rootCertPath,
                $middleCertPath,
            ));
        if ($result === false) {
            throw new \Exception("validate signPubKeyCert by rootCert failed", 1);
        } else if ($result === true) {
            CertUtil::$verifyCerts510[$certBase64String] = $certBase64String;
            return CertUtil::$verifyCerts510[$certBase64String];
        } else {
            throw new \Exception("validate signPubKeyCert by rootCert failed with error", 1);
        }
    }

    public static function getIdentitiesFromCertficate($certInfo)
    {

        $cn      = $certInfo['subject'];
        $cn      = $cn['CN'];
        $company = explode('@', $cn);

        if (count($company) < 3) {
            return null;
        }
        return $company[2];
    }

    public static function getEncryptCertId($cert_path = null)
    {
        if ($cert_path == null) {
            // $cert_path = SDKConfig::getSDKConfig()->encryptCertPath;
            throw new \Exception("cert_path error", 1);
        }
        if (!array_key_exists($cert_path, CertUtil::$encryptCerts)) {
            self::initEncryptCert($cert_path);
        }
        if (array_key_exists($cert_path, CertUtil::$encryptCerts)) {
            return CertUtil::$encryptCerts[$cert_path]->certId;
        }
        return false;
    }

    public static function getEncryptKey($cert_path = null)
    {
        if ($cert_path == null) {
            //$cert_path = SDKConfig::getSDKConfig()->encryptCertPath;
            throw new \Exception("cert_path error", 1);
        }
        if (!array_key_exists($cert_path, CertUtil::$encryptCerts)) {
            self::initEncryptCert($cert_path);
        }
        if (array_key_exists($cert_path, CertUtil::$encryptCerts)) {
            return CertUtil::$encryptCerts[$cert_path]->key;
        }
        return false;
    }

    private static function initVerifyCerts($cert_dir = null)
    {

        if ($cert_dir == null) {
            //$cert_dir = SDKConfig::getSDKConfig()->validateCertDir;
            throw new \Exception("validateCertDir error", 1);
        }

        $handle = opendir($cert_dir);
        if (!$handle) {
            throw new \Exception('证书目录 ' . $cert_dir . '不正确', 1);
        }

        while ($file = readdir($handle)) {
            clearstatcache();
            $filePath = $cert_dir . '/' . $file;
            if (is_file($filePath)) {
                if (pathinfo($file, PATHINFO_EXTENSION) == 'cer') {

                    $x509data = file_get_contents($filePath);
                    if ($x509data === false) {
                        //$logger->LogInfo($filePath . " file_get_contents fail。");
                        continue;
                    }
                    if (!openssl_x509_read($x509data)) {
                        //$logger->LogInfo($certPath . " openssl_x509_read fail。");
                        continue;
                    }

                    $cert         = new Cert();
                    $certdata     = openssl_x509_parse($x509data);
                    $cert->certId = $certdata['serialNumber'];

                    $cert->key                            = $x509data;
                    CertUtil::$verifyCerts[$cert->certId] = $cert;
                }
            }
        }
        closedir($handle);
    }

    public static function getVerifyCertByCertId($certId)
    {
        $logger = LogUtil::getLogger();
        if (count(CertUtil::$verifyCerts) == 0) {
            self::initVerifyCerts();
        }
        if (count(CertUtil::$verifyCerts) == 0) {
            throw new \Exception("未读取到任何证书……", 1);
        }
        if (array_key_exists($certId, CertUtil::$verifyCerts)) {
            return CertUtil::$verifyCerts[$certId]->key;
        } else {
            throw new \Exception("未匹配到序列号为[" . certId . "]的证书", 1);
        }
    }

}
