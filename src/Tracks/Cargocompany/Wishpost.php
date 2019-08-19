<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/23
 * Time: 13:55
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;


use Burning\YibaiLogistics\core\Httphelper;

/**
 * wish邮官方接口类
 * 接口文档：https://wishpost.wish.com/documentation/api/v2#tracking
 *
 * Class Wishpost
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Wishpost extends ATracks
{
    /**
     * 调用接口的安全验证凭据
     * @var string
     */
    protected $accessToken = '';

    /**
     * 轨迹最大查询跟踪号个数
     * @var int
     */
    protected $maxTracksQueryNumber = 20;


    /**
     * 初始化配置
     * Wishpost constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->accessToken = $apiConfig['accessToken'];

        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = 'https://wishpost.wish.com/api/v2/tracking';
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
            1=>0,// Wishpost订单已生成
            2=>0,// 揽件
            3=>2,//到达头程分拣中心
            4=>2,//在头程分拣中心处处理
            5=>2,// 离开头程分拣中心
            6=>2,// 头程分拣中心退回
            7=>2,// 到达物流服务商处
            8=>2,// 在物流服务商处处理
            9=>2,// 从物流服务商处发出
            10=>6,// 物流服务商退回
            11=>2,// 到达始发国海关
            12=>4,// 始发国海关清关失败
            13=>2,// 离开始发国海关
            14=>2,// 计划交航
            15=>2,// 到达始发国机场
            16=>2,// 从机场发往目的国
            17=>2,// 到达目的国海关
            18=>2,// 目的国海关清关失败
            19=>2,// 离开目的国海关
            20=>2,// 到达目的国
            21=>2,// 到达收寄站
            22=>2,// 尝试投递
            23=>1,// 妥投
            24=>1,// 签收
            25=>6,// 海外退回
            26=>2,// Your parcel is being shipped.
            27=>1,// 跟踪结束
            28=>2,// 到达中转中心
            29=>2,// 离开中转中心
            30=>3,// 到达目的国机场
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
            1=>'Wishpost订单已生成',
            2=>'揽件',
            3=>'到达头程分拣中心',
            4=>'在头程分拣中心处处理',
            5=>'离开头程分拣中心',
            6=>'头程分拣中心退回',
            7=>'到达物流服务商处',
            8=>'在物流服务商处处理',
            9=>'从物流服务商处发出',
            10=>'物流服务商退回',
            11=>'到达始发国海关',
            12=>'始发国海关清关失败',
            13=>'离开始发国海关',
            14=>'计划交航',
            15=>'到达始发国机场',
            16=>'从机场发往目的国',
            17=>'到达目的国海关',
            18=>'目的国海关清关失败',
            19=>'离开目的国海关',
            20=>'到达目的国',
            21=>'到达收寄站',
            22=>'尝试投递',
            23=>'妥投',
            24=>'签收',
            25=>'海外退回',
            26=>'Your parcel is being shipped.',
            27=>'跟踪结束',
            28=>'到达中转中心',
            29=>'离开中转中心',
            30=>'到达目的国机场'
        ];

        $description = isset($arr[$key])?$arr[$key]:'未知状态，请注意';
        return '['.$key.']'.$description;
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $numbers 追踪号，多跟踪号用逗号分隔
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
        $trackXmlStr = $this->assembleTrackXmlStr($numbersArr);

        //xml请求
        $postXmlStr = '<?xml version="1.0" ?>'.
            '<tracks>'.
                '<access_token>'.$this->accessToken.'</access_token>'.
                '<language>cn</language>'.$trackXmlStr.
            '</tracks>';

        $result = $this->getResult($requestUrl, '', $postXmlStr, 'POST', $headerArr);
        if (isset($result['tracks'])){
            $this->tracksContent = $this->parseTrackingInfo($numbersArr, $result['tracks']);
        }

        return $this->tracksContent;
    }

    /**
     * 多跟踪号获取轨迹
     * @param array $numbersArr
     * @return string
     */
    protected function assembleTrackXmlStr($numbersArr = [])
    {
        $xmlStr = '';
        foreach ($numbersArr as $numbers){
            $xmlStr .= '<track>'.
                            '<barcode>'.$numbers.'</barcode>'.
                        '</track>';
        }

        return $xmlStr;
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
        $numbersCount = count($numbersArr);
        //是否是多个跟踪号
        if ($numbersCount > 1){
            foreach ($tracksContent as $key=>$oneTracksVal){
                $numberTracksData = $this->numberTracksData($numbersArr[$key], $oneTracksVal);
                array_push($allNumbersData, $numberTracksData);
            }
        }else{
            $numberTracksData = $this->numberTracksData($numbersArr[0], $tracksContent);
            array_push($allNumbersData, $numberTracksData);
        }

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
        if (isset($oneNumberTracksContent['@attributes']['error_message'])){
            //Invalid Barcode 无效的跟踪号
            $data['error'] = 1;
            $data['msg'] = 'wish邮接口请求异常：'.$oneNumberTracksContent['@attributes']['error_message'];
            return $data;
        }

        $trackingInfo = [];
        //轨迹
        $trackInfo = $oneNumberTracksContent['track'];
        if (isset($trackInfo['status_number'])){
            //当前只有一条轨迹，轨迹结构和多条轨迹不同
            $data['logisticsStatus'] = self::getLogisticsStatusByText($trackInfo['status_number']);
            $data['logisticsState'] = self::getLogisticsStatusMeaning($trackInfo['status_number']);
            array_push($trackingInfo, $this->oneTracksData($trackInfo));
            $data['trackingInfo'] = $trackingInfo;
            return $data;
        }

        //多条轨迹
        $endOne = end($trackInfo);
        $data['logisticsStatus'] = self::getLogisticsStatusByText($endOne['status_number']);
        $data['logisticsState'] = self::getLogisticsStatusMeaning($endOne['status_number']);
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
        return $oneTracksData = [
            "eventTime"     => $val['date'],
            "eventDetail"   => null,
            "eventThing"    => $val['status_desc'],
            "place"         => is_string($val['remark'])?$val['remark']:'',//remark为空时会是个空数组，不为空时会是个字符串
            "eventCity"     => null,
            "eventCountry"  => null,
            "eventState"    => null,
            "eventZIPCode"  => "",
            "flowType"      => "0",
            "sort"          => "0"
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

        $xmlObj = simplexml_load_string($response);
        $xmlJsonStr = json_encode($xmlObj);
        if (JSON_ERROR_NONE !== json_last_error()){
            $this->errorMsg = "json_encode error:".json_last_error_msg()."({$response})";
            return false;
        }
        $result = json_decode($xmlJsonStr, true);
        if (JSON_ERROR_NONE !== json_last_error()){
            $this->errorMsg = "json_decode error:".json_last_error_msg()."({$xmlJsonStr})";
            return false;
        }

        if (!isset($result['status'])){
            $this->errorMsg = $response;
            return false;
        }
        if (isset($result['status']) && $result['status'] != 0){
            //请求异常
            $this->errorMsg = "[{$result['status']}]{$result['error_message']}";
            return false;
        }

        $this->errorCode = 0;
        return $result;
    }
}