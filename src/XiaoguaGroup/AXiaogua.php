<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/14
 * Time: 16:19
 */

namespace Burning\YibaiLogistics\XiaoguaGroup;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * 小瓜科技抽象类
 *
 * Class AXiaogua
 * @package Burning\YibaiLogistics\XiaoguaGroup
 */
abstract class AXiaogua
{
    /**
     * 接口秘钥
     * @var
     */
    protected $appKey;
    /**
     * 接口请求URL
     * @var
     */
    protected $apiUrl;

    protected $errorMsg;
    /**
     * 小瓜接口返回code
     * @var
     */
    protected $errorCode;

    /**
     * 初始化配置
     * AXiaogua constructor.
     * @param array $xiaoguaGroupConfig 小瓜配置
     */
    public function __construct($xiaoguaGroupConfig = [])
    {
        if (empty($xiaoguaGroupConfig)){
            die('缺少小瓜科技接口配置');
        }
        $this->appKey = $xiaoguaGroupConfig['appKey'];
        $this->apiUrl = $xiaoguaGroupConfig['apiUrl'];
    }

    /**
     * Api请求URL
     * @return string
     */
    abstract public function getApiRequestUrl();

    /**
     * 组装post请求信息
     * @param $params
     * @return string
     */
    abstract public function assemblyPostData($params);

    abstract public function getResult($params);

    /**
     * 请求数据
     * @param $params
     * @return mixed
     */
    public function sendRequest($params)
    {
        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($this->getApiRequestUrl(), $this->assemblyPostData($params), 'POST', $this->requestHeaderArr());
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }
        if ($httpClient->getHttpStatusCode() != 200){
            //响应http状态码不是200，请求失败
            $this->errorMsg = $response;
            return false;
        }

        return $response;
    }

    /**
     * 请求失败的详情
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * 请求失败的code
     * @return string
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * 请求header
     * @return array
     */
    protected function requestHeaderArr()
    {
        return [
            "user-agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36",
            "Content-Type: application/json"
        ];
    }
}