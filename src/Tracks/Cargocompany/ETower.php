<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/9/20
 * Time: 19:50
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;


use Burning\YibaiLogistics\core\Httphelper;

/**
 * 利通物流 -- 获取跟踪信息
 * Class ETower
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class ETower extends ATracks
{
    //API令牌
    protected $apiToken = '';
    //API密钥
    protected $apiKey = '';

    /**
     * 初始化配置
     * Weis constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->apiToken = $apiConfig['apiToken'];
        $this->apiKey = $apiConfig['apiKey'];
        $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');//https://cn.etowertech.com

        $this->maxTracksQueryNumber = 200;
    }


    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $numbers 追踪号，官方轨迹接口不支持批量查询
     * @return array|mixed
     */
    public function getTrackingInfo($numbers = '')
    {
        $this->initAttributeParams();

        $numbersArr = explode($this->glueFlag, trim($numbers, $this->glueFlag));
        if (count($numbersArr) > $this->maxTracksQueryNumber){
            $this->errorCode = 1;
            $this->errorMsg = '最多查询'.$this->maxTracksQueryNumber.'个物流单号';
            return false;
        }

        $requestUrl = rtrim($this->apiUrl, '/');
        $requestAction = "/services/shipper/trackingEvents";
        $result = $this->getResult($requestUrl, $requestAction, json_encode($numbersArr), 'POST');
        if ($result === false){
            return false;
        }
        return $this->parseTrackingInfo($numbersArr, $result);
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
        /*
         "orderId":"4006318139516654",
        "status":"Success",
        "errors":null,
        "events":Array[17]
         */
        $data = [
            'error'=>0,
            'msg'=>'',
            'trackingNumber'=>$oneNumberTracksContent['orderId'],
            'logisticsStatus'=>0,
            'logisticsState'=>'',
            'trackingInfo'=>[],
        ];

        if ($oneNumberTracksContent['status'] == 'Success'){
            $trackingInfo = [];
            //轨迹
            if (!empty($oneNumberTracksContent['events'])){
                $trackInfo = $oneNumberTracksContent['events'];
                $data['logisticsStatus'] = self::getLogisticsStatusByText($trackInfo[0]['eventCode']);
                $data['logisticsState'] = self::getLogisticsStatusMeaning($trackInfo[0]['eventCode']);
                foreach ($trackInfo as $val){
                    array_push($trackingInfo, $this->oneTracksData($val));
                }
            }
            $data['trackingInfo'] = $trackingInfo;
        }elseif ($oneNumberTracksContent['status'] == 'Failure'){
            $data['error'] = 1;
            $data['msg'] = $this->getResultErrorMessage($oneNumberTracksContent);
        }else{
            $data['error'] = 1;
            $data['msg'] = json_encode($oneNumberTracksContent, JSON_UNESCAPED_UNICODE);
        }

        return $data;
    }

    /**
     * 组装一条轨迹
     * @param $val
     * @return array
     */
    protected function oneTracksData($val)
    {
        /* "trackingNo":"33CUD657712501000931506",
            "eventTime":"2019-09-20T14:13:43",
            "eventCode":"INF",
            "activity":"SHIPPING INFORMATION RECEIVED",
            "location":"",
            "referenceTrackingNo":null,
            "destCountry":"AU",
            "country":"CN",
            "timeZone":"GMT+08:00"*/
        return $oneTracksData = [
            "eventTime"     => date('Y-m-d H:i:s', strtotime($val['eventTime'])),
            "eventDetail"   => null,
            "eventThing"    => $val['activity'],
            "place"         => $val['location'],
            "eventCity"     => null,
            "eventCountry"  => $val['country'],
            "eventState"    => null,
            "eventZIPCode"  => "",
            "flowType"      => "0",
            "sort"          => "0",
            "originTrackData"=>json_encode($val, JSON_UNESCAPED_UNICODE)
        ];
    }

    /**
     * 处理请求
     * @param string $requestUrl
     * @param string $requestAction
     * @param string $paramsData
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed
     */
    public function getResult($requestUrl='', $requestAction='', $paramsData='', $httpMethod='POST', $headerArr = [])
    {
        $requestUrl = $this->apiUrl . $requestAction;
        $httpMethod = strtoupper($httpMethod);
        //设置签名头部信息
        $headerArr = $this->buildHeaders($httpMethod, $requestUrl);

        $this->errorCode = 1;
        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl, $paramsData, $httpMethod, $headerArr);
//        echo json_encode($httpClient->getSendRequestResult());
//        exit;
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }
        if ($httpClient->getHttpStatusCode() != 200){
            //请求失败，服务器响应http_code不是200
            $this->errorMsg = $response;
            return false;
        }

        $resultArr = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error()){
            $this->errorMsg = 'json_decode error: ' . json_last_error_msg()."({$response})";
            return false;
        }

        if (!isset($resultArr['status'])){
            $this->errorMsg = json_encode($resultArr, JSON_UNESCAPED_UNICODE);
            return false;
        }elseif ($resultArr['status'] == 'Failure'){
            $this->errorMsg = $this->getResultErrorMessage($resultArr);
            return false;
        }elseif(!empty($resultArr['data'])){
            //请求所有跟踪号轨迹成功或部分跟踪号轨迹成功
            $this->errorCode = 0;
            return $resultArr['data'];
        }else{
            $this->errorMsg = json_encode($resultArr, JSON_UNESCAPED_UNICODE);
            return false;
        }
    }

    /**
     * 提取错误信息
     * @param array $resultArr
     * @return string
     */
    protected function getResultErrorMessage($resultArr = [])
    {
        $errorsArr = [];
        if (!empty($resultArr['errors']) && is_array($resultArr['errors'])){
            foreach ($resultArr['errors'] as $errorInfo){
                $errorMsg = "[{$errorInfo['code']}]{$errorInfo['message']}";
                array_push($errorsArr, $errorMsg);
            }
        }
        if (!empty($errorsArr)){
            return implode(';', $errorsArr);
        }
        return json_encode($resultArr, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 头部设置
     * @param $method
     * @param $path
     * @return array
     */
    protected function buildHeaders($method, $path)
    {
        // gmstrftime 该函数不能在 Windows 平台下实现！
        $walltech_date = gmstrftime("%a, %d %b %Y %T %Z", time()); //rfc1123格式时间
        $auth = $method . "\n" . $walltech_date . "\n" . $path;
        $hash = base64_encode(hash_hmac('sha1', $auth, $this->apiKey, true));
        return array(
            'Content-Type: application/json',
            "Accept: application/json",
            "X-WallTech-Date: {$walltech_date}",
            "Authorization: WallTech {$this->apiToken}:{$hash}"
        );
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
            "INF"=>0,//"Shipping information received",
            "RCV"=>0,//"Received shipment",
            "SCN"=>2,//"Processed at origin hub",
            "CCD"=>2,//"Customs Cleared",//海关清关
            "HLD"=>2,//"Customs held",
            "DLV"=>2,//"Attempt delivery",
            "CRD"=>3,//"Carded，and left at nearby carrier facility for pickup",
            "DLD"=>1,//"Delivered",
            "RTN"=>6,//"Returned",
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
            "INF"=>"Shipping information received",
            "RCV"=>"Received shipment",
            "SCN"=>"Processed at origin hub",
            "CCD"=>"Customs Cleared",
            "HLD"=>"Customs held",
            "DLV"=>"Attempt delivery",
            "CRD"=>"Carded，and left at nearby carrier facility for pickup",
            "DLD"=>"Delivered",
            "RTN"=>"Returned",
        ];

        $description = isset($arr[$key])?$arr[$key]:'未知状态，请注意';
        return '['.$key.']'.$description;
    }
}