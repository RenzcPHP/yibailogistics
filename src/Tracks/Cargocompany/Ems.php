<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/18
 * Time: 20:54
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;
use Burning\YibaiLogistics\core\XML2Array;
use Exception;

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
     * 初始化配置
     * Ems constructor.
     * @param $apiUrl
     * @param $accessToken
     */
    public function __construct($apiUrl, $accessToken)
    {
        $this->maxTracksQueryNumber = 1;
        $this->apiUrl = 'http://shipping.ems.com.cn/partner/api/public/p/track/query/cn/';
        $this->accessToken = $accessToken;//'01309bda419d409d995dd7edf63a61a3';
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
//            1=>0,// Wishpost订单已生成
//            30=>3,// 到达目的国机场
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
//            1=>'Wishpost订单已生成',
//            30=>'到达目的国机场'
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
        $numbers = trim($numbers);

        $numbersArr = explode($this->glueFlag, trim($numbers, $this->glueFlag));
        if (count($numbersArr) > $this->maxTracksQueryNumber){
            $this->errorCode = 1;
            $this->errorMsg = '最多查询'.$this->maxTracksQueryNumber.'个物流单号';
            return false;
        }

        $requestUrl = $this->apiUrl;
        $headerArr = $this->requestHeaderArr();

        $result = $this->getResult($requestUrl, '', $numbers, 'GET', $headerArr);
        $tracksData = !empty($result['traces']['trace'])?$result['traces']['trace']:[];
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
     */
    protected function oneTracksData($val)
    {
        /*
      'acceptTime' => string '2018-11-21 12:55:00' (length=19)
      'acceptAddress' => string '' (length=0)
      'remark' => string '妥投，' (length=9)*/
        return $oneTracksData = [
            "eventTime"     => $val['acceptTime'],
            "eventDetail"   => null,
            "eventThing"    => $val['remark'],
            "place"         => $val['acceptAddress'],
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
        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl, $params, $httpMethod, $headerArr);
        try{
            if($response === false){
                throw new Exception($httpClient->getErrorMessage());
            }
            if (empty($response)){
                throw new Exception('E邮宝接口请求数据响应为空');
            }
            if ($response == 'null'){
                throw new Exception("请求E邮宝接口响应数据为：{$response}，排查系还没出货");
            }
            $resultArr = XML2Array::createArray($response);
            if (empty($resultArr)){
                throw new Exception($response);
            }

            $this->errorCode = 0;
            return $resultArr;
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
            "version: international_eub_us_1.1",
            "authenticate: EB104919_059528b86cdb3c2293c1632c91f39d2a"
        ];
    }
}