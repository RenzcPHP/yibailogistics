<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/16
 * Time: 17:35
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;


use Burning\YibaiLogistics\core\Httphelper;

/**
 * 纬狮物流官方接口类
 * 官方api文档：http://ec.wiki.eccang.com/docs/show/819
 *
 * Class Weis
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Weis extends ATracks
{
    /**
     * 用户
     * @var string
     */
    protected $accountName;
    /**
     * 客户代码
     * @var string
     */
    protected $clientId;

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
     * 轨迹最大查询跟踪号个数
     * @var int
     */
    protected $maxTracksQueryNumber = 20;

    /**
     * 初始化配置
     * Weis constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->accountName = $apiConfig['accountName'];
        $this->clientId = $apiConfig['clientId'];
        $this->appToken = $apiConfig['appToken'];
        $this->appKey = $apiConfig['appKey'];

        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = 'http://120.25.2.76:8080/default/svc/web-service';
        }
    }

    /**
     * 物流状态
     * @param $key
     * @return int|mixed
     */
    public static function getLogisticsStatusByText($key)
    {
        //0暂无数据;1已签收;2运输途中;-2异常;3到达待取;4投递失败;5运输过久;6退件
        $arr = [
            'NO'=>0,//快件电子信息已经收到 Shipment information received
            'IR'=>0,//'已预报',
            'AF'=>0,//'签入',
            'DF'=>2,//'签出',
            'ND'=>3,//'派送中',
            'CC'=>1,//'妥投'
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
            'NO'=>'快件电子信息已经收到 Shipment information received',
            'IR'=>'已预报',
            'AF'=>'签入',
            'DF'=>'签出',
            'ND'=>'派送中',
            'CC'=>'妥投'
        ];

        $description = isset($arr[$key])?$arr[$key]:'未知状态，请注意';
        return '['.$key.']'.$description;
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
        $requestAction = 'getCargoTrack';
        $postData = ['codes'=>$numbersArr];
        $result = $this->getResult($requestUrl, $requestAction, $postData, 'POST', $headerArr);
        if (isset($result['Data'])){
            $this->tracksContent = $this->parseTrackingInfo($numbersArr, $result['Data']);
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
            return '';
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
            'trackingNumber'=>($oneNumberTracksContent['Code']==$trackingNumber)?$trackingNumber:'',
            'trackingInfo'=>'',
            'logisticsStatus'=>0,
            'logisticsState'=>''
        ];

        $data['logisticsStatus'] = self::getLogisticsStatusByText($oneNumberTracksContent['Status']);
        $data['logisticsState'] = self::getLogisticsStatusMeaning($oneNumberTracksContent['Status']);
        $trackingInfo = [];
        foreach ($oneNumberTracksContent['Detail'] as $val){
            $oneTracksData = [
                "eventTime"=>$val['Occur_date'],
                "eventDetail"=>null,
                "eventThing"=>$val['Comment'],
                "place"=>$val['track_area'],
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
        if ($responseResult['ask'] == 'Failure'){
            //说明请求异常
            $this->errorMsg = $responseResult['Error']['errMessage'];
            return false;
        }

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
}