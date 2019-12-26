<?php


namespace Burning\YibaiLogistics\Tracks\Cargocompany;


use Burning\YibaiLogistics\core\Httphelper;

/**
 * 万邑通海外仓头程订单轨迹接口对接
 * 接口文档地址：http://developer.winit.com.cn/document/detail/id/94.html
 * Class WYT
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class WytHwcTc extends ATracks
{
    public $token;
    public $app_key;
    public $url;
    public $track_url;
    public $client_id;
    public $client_secret;
    public $sign_method = 'md5';
    public $format = 'json';
    public $version = "1.0";
    public $platforms = '';

    /**
     * 实例化
     * Shopee constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->token = $apiConfig['token'];
        $this->app_key = $apiConfig['app_key'];
        $this->client_id = $apiConfig['client_id'];
        $this->client_secret = $apiConfig['client_secret'];
        $this->platforms = $apiConfig['platforms'];
        $this->url = $apiConfig['url'];//'http://api.winit.com.cn/ADInterface/api';
        $this->track_url = $apiConfig['track_url'];//'http://openapi.winit.com.cn/openapi/service';
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $orderNo
     * @return array|mixed
     */
    public function getTrackingInfo($orderNo = '')
    {
        $this->initAttributeParams();

        $requestAction = 'wh.tracking.queryOrderTracking';
        $params = [
            'orderNo' => $orderNo
        ];
        $result = $this->getResult($this->track_url, $requestAction, $params, 'POST');
        return $this->parseTrackingInfo($orderNo, $result);
    }

    /**
     * 解析轨迹 将轨迹信息解析组装成小瓜系统轨迹一样的格式（统一的格式进行存储）
     * @param string $trackingNumber 跟踪号
     * @param array $tracksContent
     * @return mixed|string
     */
    public function parseTrackingInfo($trackingNumber, $tracksContent = [])
    {
        $allNumbersData = [];
        $numberTracksData = $this->numberTracksData($trackingNumber, $tracksContent);
        array_push($allNumbersData, $numberTracksData);

        return $allNumbersData;
    }

    /**
     * 单个跟踪号轨迹
     * @param $trackingNumber
     * @param array $oneNumberTracksContent
     * @return array
     */
    protected function numberTracksData($trackingNumber, $oneNumberTracksContent = [])
    {
        $data = [
            'error'=>0,
            'msg'=>'',
            'trackingNumber'=>$trackingNumber,
            'trackingInfo'=>'',
            'logisticsStatus'=>0,
            'logisticsState'=>''
        ];
        if ($oneNumberTracksContent === false){
            $data['error'] = 1;
            $data['msg'] = $this->errorMsg;
            return $data;
        }elseif(!isset($oneNumberTracksContent['trackingList']) || empty($oneNumberTracksContent['trackingList'])){
            $this->errorMsg = "获取轨迹失败：".json_encode($oneNumberTracksContent);
            $data['error'] = 1;
            $data['msg'] = $this->errorMsg;
            return $data;
        }

        $trackingInfo = [];
        //轨迹
        $trackInfo = $oneNumberTracksContent['trackingList'];
        foreach ($trackInfo as $val){
            array_unshift($trackingInfo, $this->oneTracksData($val));
        }
        $data['trackingInfo'] = $trackingInfo;
        return $data;
    }

    /**
     * 组装一条轨迹
     * @param $val
     * @return array
     */
    protected function oneTracksData($val)
    {
        return $oneTracksData = [
            "eventTime"     => $val['date'],
            "eventDetail"   => '',
            "eventThing"    => $val['trackingDesc'],
            "place"         => $val['location'],
            "eventCity"     => null,
            "eventCountry"  => null,
            "eventState"    => '',
            "eventZIPCode"  => "",
            "flowType"      => "0",
            "sort"          => "0",
            "originTrackData"=>json_encode($val, JSON_UNESCAPED_UNICODE)
        ];
    }

    /**
     * 应用签名
     *
     * @param string $action 请求方法
     * @param array $data 请求数据
     * @param string $timestamp 时间戳
     * @return void
     */
    private function clientSign($action = '', $data = array(), $timestamp = '')
    {
//        if (empty($data)) {
//            $data = json_encode(array(), JSON_FORCE_OBJECT);
//        } else {
//            $data = json_encode($data, 320);
//        }
        $data = json_encode($data);
        $signStr = $this->client_secret . 'action' . $action . 'app_key' . $this->app_key . 'data' . $data . 'format' . $this->format . 'platform' . $this->platforms .
            'sign_method' . $this->sign_method . 'timestamp' . $timestamp . 'version' . $this->version . $this->client_secret;
        return strtoupper(md5($signStr));
    }

    /**
     * 数据签名
     *
     * @param string $action 请求方法
     * @param array $data 请求数据
     * @param string $timestamp 时间戳
     * @return void
     */
    private function getNewSign($action = '', $data = array(), $timestamp = '')
    {
//        if (empty($data)) {
//            $data = json_encode(array(), JSON_FORCE_OBJECT);
//        } else {
//            $data = json_encode($data, 320);
//        }
        $data = json_encode($data);
        $signStr = $this->token . 'action' . $action . 'app_key' . $this->app_key . 'data' . $data . 'format' . $this->format . 'platform' . $this->platforms .
            'sign_method' . $this->sign_method . 'timestamp' . $timestamp . 'version' . $this->version . $this->token;
        return strtoupper(md5($signStr));
    }

    /**
     * @param $requestUrl
     * @param string $requestAction
     * @param $requestParams
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed
     */
    public function getResult($requestUrl, $requestAction, $requestParams, $httpMethod = 'GET', $headerArr = [])
    {
        $timestamp = date('Y-m-d H:i:s', time());
        $sign = $this->getNewSign($requestAction, $requestParams, $timestamp);
        $client_sign = $this->clientSign($requestAction, $requestParams, $timestamp);
        $pushData = [
            "action" => $requestAction,
            "app_key" => $this->app_key,
            "client_id" => $this->client_id,
            "client_sign" => $client_sign,
            'data' => $requestParams,
            "format" => $this->format,
            "language" => "zh_CN",
            "platform" => $this->platforms,
            "sign" => $sign,
            "sign_method" => $this->sign_method,
            "timestamp" => $timestamp,
            "version" => $this->version,
        ];

        $httpClient = new Httphelper();
        //新接口用json格式请求数据
        $headerArr = ["Content-Type: application/json"];
        $response = $httpClient->sendRequest($requestUrl, json_encode($pushData), $httpMethod, $headerArr);
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }

        $resultArr = json_decode($response,true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->errorMsg = 'json_decode error: ' . json_last_error_msg()."({$response})";
            return false;
        }
        if (!isset($resultArr['code']) || $resultArr['code'] != 0){
            $this->errorMsg = json_encode($resultArr, JSON_UNESCAPED_UNICODE);
            return false;
        }elseif ($resultArr['code'] == 0){
            return $resultArr['data'];
        }
    }
}