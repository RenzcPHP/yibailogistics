<?php


namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * 速卖通轨迹
 * 接口文档：https://developers.aliexpress.com/doc.htm?docId=30120&docType=2
 * 对接接口：aliexpress.logistics.redefining.querytrackingresult( 查询物流追踪信息 )
 * Class Aliexpress
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Aliexpress extends ATracks
{
    protected $apiUrl;
    public $account_id;
    public $logisticsNo;
    public $platform_order_id;
    public $serviceName;    //carrier_key 平台对应的物流CODE
    public $ship_country;

    /**
     * 实例化
     * Shopee constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->account_id = (int) $apiConfig['account_id'];
        $this->logisticsNo = (int) $apiConfig['logisticsNo'];
        $this->platform_order_id = $apiConfig['platform_order_id'];
        $this->serviceName = $apiConfig['serviceName'];
        $this->ship_country = $apiConfig['ship_country'];
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
            'account_id'=>$this->account_id,
            'logisticsNo'=>$this->logisticsNo,
            'platform_order_id'=>$this->platform_order_id,
            'serviceName'=>$this->serviceName,//'YANWEN_JYT',
            'ship_country'=>$this->ship_country //'DE',
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
        }

        $trackingInfo = [];
        //轨迹
        $trackInfo = $oneNumberTracksContent['details']['details'];
        foreach ($trackInfo as $val){
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
address: "",
event_date: "2019-11-30 03:42:00",
event_desc: "【8】Arrive at destination country"*/
        return $oneTracksData = [
            "eventTime"     => $val['event_date'],
            "eventDetail"   => '',
            "eventThing"    => $val['event_desc'],
            "place"         => $val['address'],
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
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }

        $result = json_decode($response,true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->errorMsg = 'json_decode error: ' . json_last_error_msg()."({$response})";
            return false;
        }
        if (!isset($result['ack'])){
            $this->errorMsg = json_encode($result, JSON_UNESCAPED_UNICODE);
            return false;
        }elseif($result['ack'] == 0){
            $this->errorMsg = $result['errorMsg'];
        }elseif(!empty($result['data'])){
            /*{
                ack: 1,
                data: {
                    details: {},
                    official_website: "http://intmail.11185.cn/",
                    result_success: true,
                    request_id: "slvafg1s01xt"
                },
                errorMsg: ""
            }*/
            if ($result['data']['result_success'] === true){
                return $result['data'];
            }else{
                $this->errorMsg = json_encode($result, JSON_UNESCAPED_UNICODE);
                return false;
            }
        }

        $this->errorMsg = json_encode($result, JSON_UNESCAPED_UNICODE);
        return false;
    }

}