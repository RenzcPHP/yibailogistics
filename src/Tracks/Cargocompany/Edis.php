<?php

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * speedpark eBay线上物流
 * 接口文档：https://open.edisebay.com/open/api-document-detail
 * 对接接口：GetTrackingDetail (获取包裹物流跟踪信息)
 * Class Edis
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Edis extends ATracks
{
    protected $apiUrl;
    protected $platform_code = null;
    public $account_id;
    public $tracking_number;
    public $platform_order_id;
    public $serviceName;    //carrier_key 平台对应的物流CODE
    public $ship_country;

    /**
     * 实例化
     * Edis constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->platform_code = $apiConfig['platform_code'];
        $this->account_id = (int)$apiConfig['account_id'];
        $this->tracking_number = trim($apiConfig['tracking_number']);
        $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $trackingNumber 追踪号
     * @return array|mixed
     */
    public function getTrackingInfo($trackingNumber = '')
    {
        $this->initAttributeParams();

        //测试数据
        $params = [
            'platform_code' => $this->platform_code,
            'account_id' => $this->account_id,
            'tracking_number' => $this->tracking_number,
        ];
        $result = $this->getResult($this->apiUrl, '', $params, $httpMethod = 'POST', $headerArr = []);
        return $this->parseTrackingInfo($trackingNumber, $result);
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
            'error' => 0,
            'msg' => '',
            'trackingNumber' => $trackingNumber,
            'trackingInfo' => '',
            'logisticsStatus' => 0,
            'logisticsState' => ''
        ];
        if ($oneNumberTracksContent === false) {
            $data['error'] = 1;
            $data['msg'] = $this->errorMsg;
            return $data;
        }

        $trackingInfo = [];
        //轨迹
        $trackInfo = $oneNumberTracksContent;
        foreach ($trackInfo as $val) {
            array_push($trackingInfo, $this->oneTracksData($val));
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
        /*
                "status":"DELIVERED",
                "descriptionZh":"Package delivered by post office",
                "descriptionEn":"Package delivered by post office",
                "eventTime":"2019-11-15T15:14:00.000+0800",
                "country":"US",
                "province":"NC",
                "city":"RALEIGH",
                "district":"",
                "eventPostalCode":"27604"*/
        return $oneTracksData = [
            "eventTime" => date('Y-m-d H:i:s', strtotime($val['eventTime'])),
            "eventDetail" => '',
            "eventThing" => $val['descriptionZh']."({$val['descriptionEn']})",
            "place" => '',
            "eventCity" => $val['city'],
            "eventCountry" => $val['country'],
            "eventState" => $val['province'],
            "eventZIPCode" => $val['eventPostalCode'],
            "flowType" => "0",
            "sort" => "0",
            "originTrackData" => json_encode($val, JSON_UNESCAPED_UNICODE)
        ];
    }

    /**
     * 描述:发送请求
     * @param $requestUrl
     * @param string $requestAction
     * @param $params
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed
     */
    public function getResult($requestUrl, $requestAction, $params, $httpMethod = 'POST', $headerArr = [])
    {
        $httpClient = new Httphelper();
        //新接口用json格式请求数据
        $headerArr = ["Content-Type: application/json"];
        $response = $httpClient->sendRequest($requestUrl, json_encode($params), $httpMethod, $headerArr);
        if ($response === false) {
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }

        $result = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->errorMsg = 'json_decode error: ' . json_last_error_msg() . "({$response})";
            return false;
        }
        if (!isset($result['ack'])) {
            $this->errorMsg = json_encode($result, JSON_UNESCAPED_UNICODE);
            return false;
        } elseif ($result['ack'] == 0) {
            $this->errorMsg = $result['errorMsg'];
        } elseif (!empty($result['data'])) {
            /*{
    "ack":1,
    "data":{
        "status":{
            "resultCode":200,
            "message":"success",
            "timestamp":1577364892128,
            "messageId":"3e416c75-7346-41f7-959c-de51cf0c81d9"
        },
        "data":Array[14]
    },
    "errorMsg":""
}*/
            if (!isset($result['data']['status']['resultCode'])){
                $this->errorMsg = json_encode($result, JSON_UNESCAPED_UNICODE);
                return false;
            }
            if ($result['data']['status']['resultCode'] == 200) {
                return $result['data']['data'];
            } else {
                $this->errorMsg = "[{$result['data']['status']['resultCode']}]{$result['data']['status']['message']}";
                return false;
            }
        }

        $this->errorMsg = json_encode($result, JSON_UNESCAPED_UNICODE);
        return false;
    }

}