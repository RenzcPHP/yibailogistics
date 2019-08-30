<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/30
 * Time: 10:36
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * 云途
 * 查询物流跟踪信息
 * Class YunExpresst
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class YunExpresst extends ATracks
{
    /**
     * 调用接口的安全验证凭据
     * @var string
     */
    protected $accessToken = '';

    /**
     * 初始化配置
     * YunExpresst constructor.
     * @param array $apiConfig eg.
    $apiConfig = [
    'apiUrl'=>'http://api.yunexpress.com/LMS.API/api',
    'accessToken'=>'LlbCBCCd************iriKbZ+wy02x',
    ];
     */
    public function __construct($apiConfig = [])
    {
        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = "http://api.yunexpress.com/LMS.API/api";//生产环境地址
        }

        $this->accessToken = $apiConfig['accessToken'];
        $this->maxTracksQueryNumber = 1;
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
        $requestAction = "/WayBill/GetTrackingNumber?trackingNumber={$numbersArr[0]}";
        $result = $this->getResult($requestUrl, $requestAction, '', 'GET');
        $this->tracksContent = $this->parseTrackingInfo($numbersArr, $result);

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
            'logisticsStatus'=>0,
            'logisticsState'=>'',
            'trackingInfo'=>''
        ];

        $trackingInfo = [];
        if (!empty($oneNumberTracksContent['OrderTrackingDetails'])){
            $trackInfo = $oneNumberTracksContent['OrderTrackingDetails'];
            $data['logisticsStatus'] = self::getLogisticsStatusByText($oneNumberTracksContent['PackageState']);
            $data['logisticsState'] = self::getLogisticsStatusMeaning($oneNumberTracksContent['PackageState']);
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
     */
    protected function oneTracksData($val)
    {
        /*"ProcessDate":"2019-08-15T12:56:09",
        "ProcessContent":"Shipment information received",
        "ProcessLocation":""*/
        return $oneTracksData = [
            "eventTime"     => date('Y-m-d H:i:s', strtotime($val['ProcessDate'])),
            "eventDetail"   => null,
            "eventThing"    => $val['ProcessContent'],
            "place"         => $val['ProcessLocation'],
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
     * 发送请求，并获取响应数据
     * @param $requestUrl
     * @param string $requestAction
     * @param array|string $requestParams
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed|string
     * @return array|bool|mixed
     */
    public function getResult($requestUrl, $requestAction, $requestParams, $httpMethod = 'GET', $headerArr = [])
    {
        $this->errorCode = 1;

        $headerArr = $this->requestHeaderArr();
        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl.$requestAction, $requestParams, $httpMethod, $headerArr);
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }
        $resultArr = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->errorMsg = 'json_decode error: ' . json_last_error_msg()."({$response})";
            return false;
        }
        if (!isset($resultArr['ResultCode'])){
            $this->errorMsg = $response;
            return false;
        }
        if ($resultArr['ResultCode'] == "0000"){
            $this->errorCode = 0;
            return $resultArr['Item'];
        }elseif(!empty($resultArr['ResultDesc'])){
            $this->errorMsg = "[{$resultArr['ResultCode']}]{$resultArr['ResultDesc']}";
        }else{
            $this->errorMsg = json_encode($resultArr, JSON_UNESCAPED_UNICODE);
        }
        return false;
    }

    /**
     * 设置请求头信息
     * @return array
     */
    public function requestHeaderArr()
    {
        return array(
            'Accept: text/json',
            'Content-Type: application/json',
            'Accept-Language: zh-cn',
            'Authorization: Basic' . ' ' . $this->accessToken
        );
    }

    /**
     * 云途只支持下面这些渠道的轨迹查询
     * @return array
     */
    protected function canQueryTracksShipCode()
    {
        $canRequestApiOfShipCodeArr = [
            //"渠道Code"=>"渠道中文名",
            "GBZXR"=>"英国专线挂号",
            "GBZXA"=>"英国专线平邮",
            "EUDDP"=>"中欧专线DDP挂号",
            "EUDDPG"=>"中欧专线DDP平邮",
            "EUZXDDP"=>"中欧双清专线",
            "EUZXSW"=>"中欧独轮车专线",
            "ITZX"=>"意大利专线挂号",
            "ITZXA"=>"意大利专线平邮",
            "USZXSW"=>"中美独轮车专线",
            "USZXR"=>"中美专线",
            "USZXMP"=>"中美免泡专线",
            "USZXVIP"=>"中美独轮车专线VIP",
            "SGRDGM"=>"德国邮政挂号（特惠11国）",
            "SGADGM"=>"德国邮政平邮（特惠11国）",
            "DEZXR"=>"德国专线VIP挂号",
            "DEZXA"=>"德国专线VIP平邮",
            "CNDWA"=>"华南快速小包平邮",
            "CNPOST-FYB"=>"国际小包优+",
        ];

        return $canRequestApiOfShipCodeArr;
    }

    /**
     * 物流状态
     * @param $key 包裹状态 0-未知，1-不存在 2-运输中 3-已签收4-已收货，5-发货运输中，6,已删除，7已退回，8待转单，9退货在仓，11-已提交
     * @return int|mixed
     */
    public static function getLogisticsStatusByText($key)
    {
        //0暂无数据;1已签收;2运输途中;-2异常;3到达待取;4投递失败;5运输过久;6退件
        $arr = [
            0=>0,
            1=>0,
            2=>2,
            3=>1,
            4=>0,
            5=>2,
            6=>0,
            7=>6,
            8=>0,
            9=>0,
            11=>0,
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
            0=>'未知',
            1=>'不存在',
            2=>'运输中',
            3=>'已签收',
            4=>'已收货',
            5=>'发货运输中',
            6=>'已删除',
            7=>'已退回',
            8=>'待转单',
            9=>'退货在仓',
            11=>'已提交',
        ];

        $description = isset($arr[$key])?$arr[$key]:'未知状态，请注意';
        return '['.$key.']'.$description;
    }
}