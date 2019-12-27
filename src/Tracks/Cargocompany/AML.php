<?php

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * 艾姆勒物流官方接口类
 * 官方api文档：http://oms.imlb2c.com/api-doc/index.php
 * Class AML
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class AML extends ATracks
{
    /**
     * API账号 登陆网站获取
     * @var string
     */
    protected $appToken;

    /**
     * API密码 登陆网站获取
     * @var string
     */
    protected $appKey;

    /**
     * 初始化配置
     * AML constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->appToken = $apiConfig['appToken'];
        $this->appKey = $apiConfig['appKey'];

        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = 'http://imlb2c.com/default/svc/wsdl';
        }

        //可用逗号,空格或者回车隔开,最多支持20个订单
        $this->maxTracksQueryNumber = 20;
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $numbers 追踪号 多跟踪号用逗号分隔（eg:105587334268C700010599）
     * @return array|mixed
     */
    public function getTrackingInfo($numbers = '')
    {
        $this->initAttributeParams();

        $numbersArr = explode(',', trim($numbers, ','));
        if (count($numbersArr) > $this->maxTracksQueryNumber){
            $this->errorCode = 1;
            $this->errorMsg = '最多查询'.$this->maxTracksQueryNumber.'个物流单号';
            return false;
        }

        $requestUrl = $this->apiUrl;
        $headerArr = $this->requestHeaderArr();
        $requestAction = 'getOrderTracking';
        $postData = ['order_numbers'=>implode(',', $numbersArr)];
        $result = $this->getResult($requestUrl, $requestAction, $postData, 'POST', $headerArr);
        if (isset($result['data'])){
            $this->tracksContent = $this->parseTrackingInfo($numbersArr, $result['data']);
        }

        return $this->tracksContent;
    }

    /**
     * 解析轨迹 将轨迹信息解析组装成小瓜系统轨迹一样的格式（统一的格式进行存储）
     * @param array $numbersArr 跟踪号
     * @param array $tracksContent
     * @return mixed|string
     */
    public function parseTrackingInfo($numbersArr, $tracksContent = [])
    {
        if (empty($tracksContent)){
            return [];
        }

        $allNumbersData = [];
        foreach ($tracksContent as $key=>$oneTracksVal){
            $numberTracksData = $this->numberTracksData($numbersArr[$key], $oneTracksVal);
            array_push($allNumbersData, $numberTracksData);
        }

        return $allNumbersData;
    }

    /**
     * 单个跟踪号轨迹
     * @param string $trackingNumber
     * @param array $oneNumberTracksContent
     * @return array
     */
    protected function numberTracksData($trackingNumber, $oneNumberTracksContent = [])
    {
        $data = [
            'error'=>0,
            'msg'=>'',
            'trackingNumber'=>isset($oneNumberTracksContent['trackNumber'])?$oneNumberTracksContent['trackNumber']:'',
            'trackingInfo'=>'',
            'logisticsStatus'=>0,
            'logisticsState'=>''
        ];

        /*
            "trackNumber":"7181773083414",
            "list":{
                "trackNumber":"7181773083414",
                "orderCode":"90747-191114-070568",
                "deliveryDate":"2019-12-04 17:01:47",
                "locationCode":"",
                "status":"Завершено",
                "shipStatus":0
            },
            "detail":[
                {
                    "trackNumber":"7181773083414",
                    "orderCode":"90747-191114-070568",
                    "deliveryDate":"2019-12-04 17:01:47",
                    "locationCode":"",
                    "status":"Завершено",
                    "shipStatus":0
                }
            ]*/
        if (empty($oneNumberTracksContent['list']) || empty($oneNumberTracksContent['detail'])){
            $data['error'] = 1;
            $data['msg'] = "暂无轨迹".json_encode($oneNumberTracksContent, JSON_UNESCAPED_UNICODE);
            return $data;
        }

        $data['trackingNumber'] = $oneNumberTracksContent['trackNumber'];
        $data['logisticsStatus'] = self::getLogisticsStatusByText($oneNumberTracksContent['list']['shipStatus']);
        $data['logisticsState'] = self::getLogisticsStatusMeaning($oneNumberTracksContent['list']['status']);
        $trackingInfo = [];
        foreach ($oneNumberTracksContent['detail'] as $val){
            $oneTracksData = [
                "eventTime"=>$val['deliveryDate'],
                "eventDetail"=>null,
                "eventThing"=>"【{$val['status']}】{$val['locationCode']}",
                "place"=>'',
                "eventCity"=>null,
                "eventCountry"=>null,
                "eventState"=>null,
                "eventZIPCode"=>"",
                "flowType"=>"0",
                "sort"=>"0",
                "originTrackData"=>json_encode($val, JSON_UNESCAPED_UNICODE)
            ];

            array_unshift($trackingInfo, $oneTracksData);
        }
        $data['trackingInfo'] = $trackingInfo;

        return $data;
    }

    /**
     * 发送请求，并获取响应数据
     * @param $requestUrl
     * @param string $requestAction
     * @param array|string $params
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed|string
     */
    public function getResult($requestUrl, $requestAction, $params, $httpMethod = 'GET', $headerArr = [])
    {
        $this->errorCode = 1;
        $postData = $this->assemblePostData($requestAction, $params);

        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl, $postData, $httpMethod, $headerArr);
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }
        if ($httpClient->getHttpStatusCode() != 200){
            //响应http状态码不是200，请求失败
            $this->errorMsg = $response;
            return false;
        }

        $responseData = $this->parseSoapResponseData($response);
        if (empty($responseData)){
            //请求响应数据为空
            $this->errorMsg = $response;
            return false;
        }

        //请求正常，解析
        $responseResult = json_decode($responseData, true);
        if (JSON_ERROR_NONE !== json_last_error()){
            $this->errorMsg = "json_decode error:".json_last_error_msg()."({$responseData})";
            return false;
        }
        if (!isset($responseResult['ask'])){
            $this->errorMsg = json_encode($responseResult, JSON_UNESCAPED_UNICODE);
            return false;
        }
//        if ($responseResult['ask'] == 'Failure'){
//            //说明请求异常
//            $this->errorMsg = !empty($responseResult['message'])?$responseResult['message']:json_encode($responseResult, JSON_UNESCAPED_UNICODE);
//            return false;
//        }

        $this->errorCode = 0;
        return $responseResult;
    }

    /**
     * 组装请求参数
     * @param $service
     * @param $postData
     * @return string
     */
    protected function assemblePostData($service, $postData)
    {
        $paramsJson = json_encode($postData);
        $xmlArray = '<?xml version="1.0" encoding="UTF-8"?>';
        $xmlArray.= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">';
        $xmlArray.= "<SOAP-ENV:Body>";
        $xmlArray.= "<ns1:callService>";
        $xmlArray.= "<paramsJson>{$paramsJson}</paramsJson>";
        $xmlArray.= "<appToken>{$this->appToken}</appToken>";
        $xmlArray.= "<appKey>{$this->appKey}</appKey>";
        $xmlArray.= "<service>{$service}</service>";
        $xmlArray.= "</ns1:callService>";
        $xmlArray.= "</SOAP-ENV:Body>";
        $xmlArray.= "</SOAP-ENV:Envelope>";
        return $xmlArray;
    }

    /**
     * 去除soap头尾xml字符串，提取接口返回的json信息
     * @param string $responseData
     * @return mixed
     */
    protected function parseSoapResponseData($responseData = '')
    {
        $findArr = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">',
            '<SOAP-ENV:Body>',
            '<ns1:callServiceResponse>',
            '<response>',
            '</response>',
            '</ns1:callServiceResponse>',
            '</SOAP-ENV:Body>',
            '</SOAP-ENV:Envelope>',
            "\r\n", "\n", "\r"
        ];
        $replaceStr = '';
        $response = str_replace($findArr, $replaceStr, $responseData);
        return trim($response);
    }


    /**
     * 物流状态 0无 1派送中 2已妥投 3服务商未妥投
     * @param $key
     * @return int|mixed
     */
    public static function getLogisticsStatusByText($key)
    {
        //0暂无数据;1已签收;2运输途中;-2异常;3到达待取;4投递失败;5运输过久;6退件
        $arr = [
            '0'=>0,//
            '1'=>3,//'派送中',
            '2'=>1,//'已妥投',
            '3'=>2,//'服务商未妥投',
        ];

        return isset($arr[$key])?$arr[$key]:0;
    }

    /**
     * 官方状态代表含义
     * @param $key
     * @return int|mixed
     */
    public static function getLogisticsStatusMeaning($key)
    {
        $arr = [
            'Завершено'=>'已完成',
        ];

        $description = isset($arr[$key])?$arr[$key]:'';
        return '['.$key.']'.$description;
    }
}