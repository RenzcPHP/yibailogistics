<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/18
 * Time: 20:54
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * E邮宝官方接口类
 *
 * Class Ems
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Ems extends ATracks
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
     * Ems constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = 'http://shipping.ems.com.cn/partner/api/public/p/area/cn/province/list';
        }
        $this->accessToken = $apiConfig['accessToken'];
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

        $result = $this->getResult($requestUrl, '', '', 'GET', $headerArr);
        if (isset($result['tracks'])){
            $this->tracksContent = $this->parseTrackingInfo($numbersArr, $result['tracks']);
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
            $data['msg'] = 'E邮宝接口请求异常：'.$oneNumberTracksContent['@attributes']['error_message'];
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
        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl, $params, $httpMethod, $headerArr);
        if($response === false){
            $this->errorCode = 1;
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }
        if ($httpClient->getHttpStatusCode() != 200){
            //响应http状态码不是200，请求失败
            $this->errorCode = 1;
            $this->errorMsg = $response;
            return false;
        }

        echo $response;die;

        $xmlObj = simplexml_load_string($response);
        $xmlJsonStr = json_encode($xmlObj);
        $result = json_decode($xmlJsonStr, true);

        if (empty($result)){
            $this->errorCode = 1;
            $this->errorMsg = 'E邮宝接口请求数据响应为空';
            return false;
        }
        if ($result['status'] != 0){
            $this->errorCode = 1;
            //请求异常
            $this->errorMsg = 'E邮宝接口请求异常：【status='.$result['status'].'】'.$result['error_message'];
            //获取轨迹请求参数
            $data = [
                'errorMsg'      => $this->errorMsg,
                'httpMethod'    => $httpMethod,
                'requestParams' => $params,
            ];
            Helper::triggerAlarm('E邮宝接口请求响应异常代码-'.$result['status'], $data, true, 60);
            return false;
        }

        return $result;
    }

    /**
     * 设置请求头信息
     * @return array
     */
    public function requestHeaderArr()
    {
        return [
            "version: international_eub_us_1.1",
            "authenticate: EB104919_059528b86cdb3c2293c1632c91f39d2a"
        ];
    }
}