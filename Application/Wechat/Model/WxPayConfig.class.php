<?php
/**
 * Created by PhpStorm.
 * User: Aaron
 * Date: 5/9/16
 * Time: 2:18 PM
 */

namespace Wechat\Model;


class WxPayConfig
{

    public $config = array();

    public function getConfig()
    {
        return $this->config;
    }

    public function setAppId($appId)
    {
        $this->config['APPID'] = $appId;
        return $this;
    }

    public function setMchId($mchId)
    {
        $this->config['MCHID'] = $mchId;
        return $this;
    }

    public function setKey($key)
    {
        $this->config['KEY'] = $key;
        return $this;
    }

    public function setAppSecret($appSecret)
    {
        $this->config['APPSECRET'] = $appSecret;
        return $this;
    }

    public function setSslCertPath($sslCertPath)
    {
        $this->config['SSLCERT_PATH'] = $sslCertPath;
        return $this;
    }

    public function setSslKeyPath($sslKeyPath)
    {
        $this->config['SSLKEY_PATH'] = $sslKeyPath;
        return $this;
    }

    public function setCurlProxyHost($curlProxyHost)
    {
        $this->config['CURL_PROXY_HOST'] = $curlProxyHost;
        return $this;
    }

    public function setCurlProxyPort($curlProxyPort)
    {
        $this->config['CURL_PROXY_PORT'] = $curlProxyPort;
        return $this;
    }

    public function setReportLevel($reportLevel)
    {
        $this->config['REPORT_LEVENL'] = $reportLevel;
        return $this;
    }

    public function setNotifyUrl($notifyUrl)
    {
        $this->config['NOTIFY_URL'] = $notifyUrl;
        return $this;
    }

    public function getAppId()
    {
        return $this->config['APPID'];
    }

    public function getMchId()
    {
        return $this->config['MCHID'];
    }

    public function getKey()
    {
        return $this->config['KEY'];
    }

    public function getAppSecret()
    {
        return $this->config['APPSECRET'];
    }

    public function getSslCertPath()
    {
        return $this->config['SSLCERT_PATH'];
    }

    public function getSslKeyPath()
    {
        return $this->config['SSLKEY_PATH'];
    }

    public function getCurlProxyHost()
    {
        return $this->config['CURL_PROXY_HOST'];
    }

    public function getCurlProxyPort()
    {
        return $this->config['CURL_PROXY_PORT'];
    }

    public function getReportLevel()
    {
        return $this->config['REPORT_LEVENL'];
    }

    public function getNotifyUrl()
    {
        return $this->config['NOTIFY_URL'];
    }

}