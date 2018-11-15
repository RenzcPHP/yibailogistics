<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/11
 * Time: 17:52
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * 出口易官方接口类
 * 官方接口文档：
        * 1、测试环境开发者登陆/注册地址：http://developers-release.chukou1.cn
        * 2、正式环境开发者登陆/注册地址：http://developers.chukou1.cn
 *
 *      POST https://openapi-release.chukou1.cn/v1/trackings?lang={lang}   lang 默认是中文轨迹"zh"，英文轨迹"en"
 *
 * Class Chukou1
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Chukou1 extends ATracks
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
     * Chukou1 constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->accessToken = $apiConfig['accessToken'];

        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = 'https://openapi.chukou1.cn/v1';
        }
    }

    /**
     * 物流状态
     * @param $key
            轨迹状态：PickUp、Processing、InTransit、Delivered
     * @return int|mixed
     */
    public static function getLogisticsStatusByText($key)
    {
        //0暂无数据;1已签收;2运输途中;-2异常;3到达待取;4投递失败;5运输过久;6退件
        $arr = [
            'PickUp'        => 0,
            'Processing'    => 2,
            'InTransit'     => 3,
            'Delivered'     => 1
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
            'PickUp'        => '揽货',
            'Processing'    => '处理中',
            'InTransit'     => '投递中',
            'Delivered'     => '已投递'
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

        $requestUrl = rtrim($this->apiUrl, '/');
        $requestAction = '/trackings';
        $headerArr = $this->requestHeaderArr();
        $postData = $this->assembleTrackingsPostData($numbersArr);
        $result = $this->getResult($requestUrl, $requestAction, $postData, 'POST', $headerArr);
        return $this->parseTrackingInfo($numbersArr, $result);
    }

    /**
     * 多跟踪号获取轨迹
     * @param array $numbersArr
     * @return string
     */
    protected function assembleTrackingsPostData($numbersArr = [])
    {
        $data = [
            'TrackingNumbers' => $numbersArr
        ];

        return json_encode($data);
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
            $numberTracksData = $this->numberTracksData($oneTracksVal);
            array_push($allNumbersData, $numberTracksData);
        }

        return $allNumbersData;
    }

    /**
     * 单个跟踪号轨迹
     * @param array $oneNumberTracksContent
     * @return array
     */
    protected function numberTracksData($oneNumberTracksContent = [])
    {
        $data = [
            'error'=>0,
            'msg'=>'',
            'trackingNumber'=>$oneNumberTracksContent['TrackingNumber'],
            'trackingInfo'=>'',
            'logisticsStatus'=>0,
            'logisticsState'=>''
        ];

        $trackingInfo = [];
        //轨迹
        if (isset($oneNumberTracksContent['Checkpoints'])){
            $trackInfo = $oneNumberTracksContent['Checkpoints'];
            $endOne = end($trackInfo);
            $data['logisticsStatus'] = self::getLogisticsStatusByText($endOne['TrackingStatus']);
            $data['logisticsState'] = self::getLogisticsStatusMeaning($endOne['TrackingStatus']);
            foreach ($trackInfo as $val){
                array_unshift($trackingInfo, $this->oneTracksData($val));
            }
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
            "eventTime"     => date('Y-m-d H:i:s', strtotime($val['DateTime'])),
            "eventDetail"   => null,
            "eventThing"    => $val['Message'],
            "place"         => $val['Location'],//remark为空时会是个空数组，不为空时会是个字符串
            "eventCity"     => null,
            "eventCountry"  => null,
            "eventState"    => $val['TrackingStatus'],
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
        $response = $httpClient->sendRequest($requestUrl.$requestAction, $params, $httpMethod, $headerArr);
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

        $result = json_decode($response, true);

        if (empty($result)){
            $this->errorCode = 1;
            Helper::triggerAlarm('出口易请求参数', $params);
            $this->errorMsg = $response;//'出口易接口请求数据响应为空';
            return false;
        }
        if (isset($result['Errors'])){
            $errorArr = $result['Errors'][0];
            $this->errorCode = 1;
            //请求异常
            $this->errorMsg = '出口易接口请求异常：【Code='.$errorArr['Code'].'】'.$errorArr['Message'];
            return false;
        }
        if (isset($result['Code'])){
            $this->errorCode = 1;
            //请求异常
            $this->errorMsg = '出口易接口请求异常：【Code='.$result['Code'].'】'.$result['Message'];
            //获取轨迹请求参数
            $data = [
                'errorMsg'      => $this->errorMsg,
                'httpMethod'    => $httpMethod,
                'requestAction' => $requestAction,
                'requestParams' => $params,
            ];
            Helper::triggerAlarm('出口易接口请求响应异常代码-'.$result['Code'], $data, true, 60);
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
            "Authorization: Bearer {$this->accessToken}",
            "Content-Type: application/json; charset=utf-8"
        ];
    }
}