<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/16
 * Time: 17:21
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

/**
 * 官方物流接口——物流轨迹接口
 * Class ATracks
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
abstract class ATracks
{
    /**
     * 接口请求URL
     * @var
     */
    protected $apiUrl;

    /**
     * 订单轨迹状态 0暂无数据;1已签收;2运输途中;-2异常;3到达待取;4投递失败;5运输过久;6退件
     * @var int
     */
    protected $logisticsStatus = 0;

    /**
     * 订单物流轨迹动态信息
     * @var
     */
    protected $tracksContent;

    /**
     * 请求错误信息
     * @var string
     */
    protected $errorMsg = '';
    /**
     * 接口请求是否正常 0-正常，1-失败
     * @var int
     */
    protected $errorCode = 0;

    /**
     * 重置必要参数
     */
    public function initAttributeParams()
    {
        $this->logisticsStatus = 0;
        $this->errorCode = 0;
        $this->errorMsg = '';
        $this->tracksContent = [];
    }

    /**
     * 默认请求头信息为空
     * @return array
     */
    public function requestHeaderArr()
    {
        return [];
    }

    /**
     * 获取轨迹
     * @param $trackingNumber 跟踪号
     * @return mixed
     */
    abstract public function getTrackingInfo($trackingNumber);

    /**
     * 获取轨迹状态
     * @return mixed
     */
    public function getLogisticsStatus()
    {
        return $this->logisticsStatus;
    }

    /**
     * 解析轨迹
     * @param array $numbersArr 跟踪号
     * @param array $tracksContent 一个订单轨迹信息
     * @return mixed
     */
    abstract public function parseTrackingInfo($numbersArr, $tracksContent = []);

    /**
     * 请求
     * @param $requestUrl
     * @param string $requestAction
     * @param $params
     * @param string $httpMethod
     * @param array $headerArr
     * @param bool $returnResponseFailedFlag
     * @return mixed
     */
    abstract public function getResult($requestUrl, $requestAction, $params, $httpMethod = 'GET', $headerArr = []);

    /**
     * 返回接口请求错误码
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * 返回接口请求错误提示内容
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }
}