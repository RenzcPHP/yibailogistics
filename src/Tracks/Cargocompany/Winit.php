<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/18
 * Time: 21:53
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;


use Burning\YibaiLogistics\core\Httphelper;

/**
 * 万邑通轨迹查询接口
 * 卖家或第三方通过该接口可查询ISP订单轨迹。
 * https://developer.winit.com.cn/document/detail/id/71.html
 *
        * 验证方法	Token,md5
        * 格式	json
        * 字符编码	UTF-8
        * http请求方式	http
        * 请求数限制	默认每分钟1000，有需要可申请加大。
 *
 * Class Winit
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Winit extends ATracks
{
    //***************************启用新账号 不影响之前账号获取面单和物流跟踪号***********************
    protected $newAccountAddCode    = 'DG';
    protected $newAccountAppKey     = '';
    protected $newAccountToken       = '';
    protected $orderPaytime           = '';//临时切换账号
    protected $version = '1.0';

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
     * Winit constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->accessToken = $apiConfig['accessToken'];
        $this->newAccountAddCode = $apiConfig['newAccountAddCode'];
        $this->newAccountAppKey = $apiConfig['newAccountAppKey'];
        $this->newAccountToken = $apiConfig['newAccountToken'];
        $this->orderPaytime = $apiConfig['orderPaytime'];
        $this->version = isset($apiConfig['version'])?$apiConfig['version']:'1.0';

        if (!empty($apiConfig['apiUrl'])){
            $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        }else{
            $this->apiUrl = 'http://openapi.winit.com.cn/openapi/service';
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
            '等待收件'    => 0,
            '待处理'      => 2,
            '货物未收到'   => 2,
            '货物已收到'   => 2,
            '入库完成'    => 2,
            '已打包'      => 2,
            '已出库'      => 2,
        ];

        return isset($arr[$key])?$arr[$key]:0;
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $numbers 追踪号，多跟踪号用逗号分隔
     * @return array|mixed
     */
    public function getTrackingInfo($numbers = '')
    {
        $this->initAttributeParams();

        $trackingNOs = [
            'trackingNOs'=>$numbers
        ];

        $numbersArr = explode(',', trim($numbers, ','));

        $requestUrl = rtrim($this->apiUrl, '/');
        $requestAction = 'tracking.getOrderTracking';

        $headerArr = $this->requestHeaderArr();
        $postData = $this->assembleRequestParams($requestAction, $trackingNOs);

        $result = $this->getResult($requestUrl, '', $postData, 'POST', $headerArr);
        return $this->parseTrackingInfo($numbersArr, $result['data']);
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
            'trackingNumber'=>$oneNumberTracksContent['trackingNo'],
            'trackingInfo'=>'',
            'logisticsStatus'=>0,
            'logisticsState'=>''
        ];

        $trackingInfo = [];
        //轨迹
        $trackInfo = $oneNumberTracksContent['trace'];
        $endOne = end($trackInfo);
        $data['logisticsStatus'] = self::getLogisticsStatusByText($endOne['eventStatus']);
        $data['logisticsState'] = $endOne['eventDescription'];
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
            "eventThing"    => $val['eventDescription'],
            "place"         => $val['location'],
            "eventCity"     => null,
            "eventCountry"  => null,
            "eventState"    => $val['eventStatus'],
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
            $this->errorMsg = $response;
            return false;
        }
        if ($result['code'] != 0){
            $this->errorCode = 1;
            //请求异常
            $this->errorMsg = '万邑通接口请求异常：【code='.$result['code'].'】'.$result['msg'];
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
            "Content-Type: application/json; charset=utf-8"
        ];
    }

    /**
     * 组装请求参数
     * @param $method
     * @param $data
     * @return string
     */
    protected function assembleRequestParams($method, $data)
    {
        ksort($data);
        $timestamp = date('Y-m-d H:i:s');
        $signData = urldecode(json_encode($this->urlEncode($data)));
        $signString = $this->newAccountToken.'action'.$method.'app_key'.$this->newAccountAppKey.'data'.$signData.'formatjsonplatformSELLERERPsign_methodmd5timestamp'.$timestamp.'version'.$this->version.$this->newAccountToken;

        $postParams = [
            'action'        => $method,
            'app_key'       => $this->newAccountAppKey,
            'timestamp'     => $timestamp,
            'version'       => $this->version,
            'sign_method'   => 'md5',
            'format'        => 'json',
            'platform'      => 'SELLERERP',
            'language'      => 'zh_CN',
            'data'          => $data,
            'sign'          => strtoupper(md5($signString))
        ];

        return urldecode(json_encode($this->urlEncode($postParams)));
    }

    protected function urlEncode($str)
    {
        if (is_array($str)) {
            foreach ($str as $key => $value) {
                $str[$key] = $this->urlEncode($value);
            }
        } else {
            $str = urlencode($str);
        }
        return $str;
    }
}