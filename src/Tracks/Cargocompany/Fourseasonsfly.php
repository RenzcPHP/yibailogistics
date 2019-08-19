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
 * 四季正扬官方接口类
 * 接口文档：https://wishpost.wish.com/documentation/api/v2#tracking
 *
 * Class Fourseasonsfly
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Fourseasonsfly extends ATracks
{
    /**
     * 调用接口的安全验证凭据
     * @var string
     */
    protected $ticket = '';

    /**
     * 轨迹最大查询跟踪号个数
     * @var int
     */
    protected $maxTracksQueryNumber = 20;

    /**
     * 初始化配置
     * Fourseasonsfly constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = 'http://api.fourseasonsfly.net';
        }
        $this->ticket = $apiConfig['ticket'];
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
            'CHECKIN'           => 2,//已入库
            'CHECKOUT'          => 2,//已出库
            'GIVE_AVIATION'     => 2,//已交航
            'REACH_COUNTRY'     => 3,//到达目的国
            'STORED'            => 0,//已揽件
            'TRANSPORTING'      => 2,//运输途中
            'AWAITING'          => 3,//到达待取
            'DELIVERY_FAILED'   => 4,//投递失败
            'DELIVERED'         => 1,//成功签收
            'ERROR_TRACKING'    => 0,//查询不到
            'NO_TRACKING'       => 0,//未上网
            'EX_TRACKING'       => -2,//追踪异常
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
            'CHECKIN'           => '已入库',
            'CHECKOUT'          => '已出库',
            'GIVE_AVIATION'     => '已交航',
            'REACH_COUNTRY'     => '到达目的国',
            'STORED'            => '已揽件',
            'TRANSPORTING'      => '运输途中',
            'AWAITING'          => '到达待取',
            'DELIVERY_FAILED'   => '投递失败',
            'DELIVERED'         => '成功签收',
            'ERROR_TRACKING'    => '查询不到',
            'NO_TRACKING'       => '未上网',
            'EX_TRACKING'       => '追踪异常'
        ];

        $description = isset($arr[$key])?$arr[$key]:'未知状态，请注意';
        return '['.$key.']'.$description;
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * STORED:已揽件 TRANSPORTING:运输途中 GIVE_AVIATION:已交航 REACH_COUNTRY:到达目的国
     * DELIVERED:成功签收 ERROR_TRACKING:查询不到,一次查询最多20条（未查询到订单会返回ERROR_TRACKING
     * @param string $numbers 追踪号或尾程单号，多个用逗号隔开（eg:SJSAD18032710835YQ,RY923089120CN）
     * @return array|mixed
     */
    public function getTrackingInfo($numbers = '')
    {
        $this->initAttributeParams();

        $numbers = trim($numbers, ',');
        $numbersArr = explode(',', $numbers);
        if (count($numbersArr) > $this->maxTracksQueryNumber){
            $this->errorCode = 1;
            $this->errorMsg = '最多查询'.$this->maxTracksQueryNumber.'个物流单号';
            return false;
        }

        $requestUrl = $this->apiUrl.'/customer/tracking/getTrackingInfo.json?numbers='.$numbers;
        $headerArr = $this->requestHeaderArr();
        $result = $this->getResult($requestUrl, '', '', 'POST', $headerArr);
        if (isset($result['trackingList'])){
            $this->tracksContent = $this->parseTrackingInfo($numbersArr, $result['trackingList']);
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
            'trackingNumber'=>$trackingNumber,
            'trackingInfo'=>'',
            'logisticsStatus'=>0,
            'logisticsState'=>''
        ];

        $data['logisticsStatus'] = self::getLogisticsStatusByText($oneNumberTracksContent['status']);
        $data['logisticsState'] = self::getLogisticsStatusMeaning($oneNumberTracksContent['status']).'('.$oneNumberTracksContent['nearestNode'].')';
        $trackingInfo = [];
        foreach ($oneNumberTracksContent['trackNodes'] as $val){
            $oneTracksData = [
                "eventTime"=>$val['nodeTime'],
                "eventDetail"=>null,
                "eventThing"=>$val['node'],
                "place"=>"",
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
        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl, $params, $httpMethod, $headerArr);
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }
        if ($httpClient->getHttpStatusCode() != 200){
            //响应http状态码不是200，请求失败
            $this->errorMsg = $response;
            return false;
        }

        $resultArr = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error()){
            $this->errorMsg = "json_decode error:".json_last_error_msg()."({$response})";
            return false;
        }
        //0表示成功，其他为错误代码
        if (!isset($resultArr['code'])){
            //["code"]=> string(6) "user-3" ["msg"]=> string(20) "test 用户不存在" ["content"]=> array(0) { }
            $this->errorMsg = json_encode($resultArr, JSON_UNESCAPED_UNICODE);
            return false;
        }
        if ($resultArr['code']){
            //["code"]=> string(6) "user-3" ["msg"]=> string(20) "test 用户不存在" ["content"]=> array(0) { }
            $this->errorMsg = "[{$resultArr['code']}]{$resultArr['msg']}";
            return false;
        }

        //返回轨迹数据
        $this->errorCode = 0;
        return $resultArr['content'];
    }

    /**
     * 设置请求头信息
     * @return array
     */
    public function requestHeaderArr()
    {
        $headerArr = [
            "Content-Type: application/json",
            "Accept: application/json"
        ];

        //认证凭据
        if ($this->ticket != ''){
            $headerArr[] = "ticket: {$this->ticket}";
        }

        return $headerArr;
    }
}