<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/20
 * Time: 11:38
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;
use Exception;

/**
 * 递四方
 * Class D4px
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class D4px extends ATracks
{
    /**
     * 客户代码
     * @var mixed|string
     */
    protected $customerId = '';
    /**
     * 调用接口的安全验证凭据
     * @var string
     */
    protected $accessToken = '';

    /**
     * 初始化配置
     * D4px constructor.
     * @param array $apiConfig eg.
                            $apiConfig = [
                                'apiUrl'=>'http://openapi.4px.com/api/service/woms/receiving/getTrackList',
                                'customerId'=>'901394',
                                'accessToken'=>'LlbCBCCd************iriKbZ+wy02x',
                            ];
     */
    public function __construct($apiConfig = [])
    {
        if (!empty($apiConfig['apiUrl'])){
            $serverApiUrl = $apiConfig['apiUrl'];
        }else{
//            $serverApiUrl = "http://apisandbox.4px.com/api/service/woms/receiving/getTrackList";//测试环境地址
            $serverApiUrl = "http://openapi.4px.com/api/service/woms/receiving/getTrackList";//生产环境地址
        }
        $serverApiUrl .= "?customerId=%s&token=%s&language=%s";

        $this->customerId = !empty($apiConfig['customerId'])?trim($apiConfig['customerId']):'901394';//非必填(在操作客户数据时必填,详见各个接口)，需要通过客户授权接口获取
        $this->accessToken = $apiConfig['accessToken'];
        $language = (isset($apiConfig['language'])&& !empty($apiConfig['language']))?$apiConfig['language']:'en_US';
        $this->apiUrl = sprintf($serverApiUrl, $this->customerId, $this->accessToken, $language);
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $numbers 追踪号，多跟踪号用逗号分隔
     * @return array|mixed
     */
    public function getTrackingInfo($numbers = '')
    {
        $this->initAttributeParams();
        $numbers = trim($numbers);

        $numbersArr = explode($this->glueFlag, trim($numbers, $this->glueFlag));
        if (count($numbersArr) > $this->maxTracksQueryNumber){
            $this->errorCode = 1;
            $this->errorMsg = '最多查询'.$this->maxTracksQueryNumber.'个物流单号';
            return false;
        }

        $requestUrl = $this->apiUrl;
        $headerArr = $this->requestHeaderArr();

        $params = ['receivingCode'=>$numbers];

        $result = $this->getResult($requestUrl, '', $params, 'POST', $headerArr);
        $tracksData = !empty($result['data'])?$result['data']:[];
        $this->tracksContent = $this->parseTrackingInfo($numbersArr, $tracksData);

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
        $allNumbersData = [];
        $numberTracksData = $this->numberTracksData($numbersArr[0], $tracksContent);
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

        $trackingInfo = [];
        $trackInfo = $oneNumberTracksContent;
        if (!empty($trackInfo)){
            foreach ($trackInfo as $val){
                array_unshift($trackingInfo, $this->oneTracksData($val));
            }
        }else{
            $data['error'] = 1;
            $data['msg'] = '没查询到轨迹信息，请确认跟踪号是否存在';
        }
        $data['trackingInfo'] = $trackingInfo;
        return $data;
    }

    /**
     * 组装一条轨迹
     * @param $val
     * @return array
                    occurDate       String 轨迹发生时间（ YYYY-MM-DD HH24:MI:SS ）
                    occurAddress    String 轨迹发生地点以及发生事件
                    trackCode       String 轨迹代码
                    trackContent    String 轨迹代码补充
     */
    protected function oneTracksData($val)
    {
        /*
occurAddress: "SEMINOLE-ORLANDO FL DISTRIBUTION CENTER",
occurDate: "2018-08-25 12:11:00",
trackCode: null,
trackContent: "Departed USPS Regional Destination Facility"*/

        $eventThing = $val['trackContent'];
        if (!empty($val['trackCode'])){
            $eventThing = "[{$val['trackCode']}]".$eventThing;
        }

        $eventTime = date('Y-m-d H:i:s', $val['occurDate']/1000);

        return $oneTracksData = [
            "eventTime"     => $eventTime,//轨迹发生地点以及发生事件
            "eventDetail"   => null,
            "eventThing"    => $eventThing,
            "place"         => $val['occurAddress'],
            "eventCity"     => null,
            "eventCountry"  => null,
            "eventState"    => null,
            "eventZIPCode"  => "",
            "flowType"      => "0",
            "sort"          => "0",
            "originTrackData"=>json_encode($val, JSON_UNESCAPED_UNICODE)
        ];
    }

    /**
     * 发送请求，并获取响应数据
     * @param $requestUrl
     * @param string $requestAction
     * @param array|string $params
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed|string
     * @return array|bool|mixed
     */
    public function getResult($requestUrl, $requestAction, $params, $httpMethod = 'GET', $headerArr = [])
    {
        $this->errorCode = 1;
        $requestParams = json_encode($params);

        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl, $requestParams, $httpMethod, $headerArr);
        try{
            if($response === false){
                throw new Exception($httpClient->getErrorMessage());
            }
            $resultArr = json_decode($response, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new Exception('json_decode error: ' . json_last_error_msg()."({$response})");
            }
            if (!isset($resultArr['errorCode'])){
                throw new Exception($response);
            }
            if ($resultArr['errorCode'] == 0){
                $this->errorCode = 0;
                return $resultArr;
            }
            $this->errorMsg = "[{$resultArr['errorCode']}]{$resultArr['errorMsg']}";
            return false;
        }catch (Exception $exception){
            $this->errorMsg = $exception->getMessage();
            return false;
        }
    }

    /**
     * 设置请求头信息
     * @return array
     */
    public function requestHeaderArr()
    {
        return [
            "Content-Type: application/json",
        ];
    }
}