<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/8/29
 * Time: 11:05
 */

namespace Burning\YibaiLogistics\Tracks\Cargocompany;

use Burning\YibaiLogistics\core\Httphelper;

/**
 * Class OneWorldExpress
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class OneWorldExpress extends ATracks
{
    /**
     * 调用接口的安全验证凭据
     * @var string
     */
    protected $apiToken = '';
    protected $apiAccountCoding = '';

    /**
     * 初始化配置
     * OneWorldExpress constructor.
     * @param array $apiConfig  eg. [
                                        'apiUrl'=>'http://api.wanbexpress.com',
                                        'apiToken'=>'',
                                        'apiAccountCoding'=>'',
                                    ]
     */
    public function __construct(array $apiConfig)
    {
        $this->maxTracksQueryNumber = 1;
        $this->apiUrl = isset($apiConfig['apiUrl'])?rtrim($apiConfig['apiUrl'], '/'):'http://api.wanbexpress.com';
        $this->apiToken = $apiConfig['apiToken'];
        $this->apiAccountCoding = $apiConfig['apiAccountCoding'];
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
        $requestAction = "/api/trackPoints?trackingNumber={$numbersArr[0]}";
        $result = $this->getResult($requestUrl, $requestAction, '', 'GET');
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
        $numberTracksData = $this->numberTracksData($tracksContent);
        array_push($allNumbersData, $numberTracksData);

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
        if (isset($oneNumberTracksContent['TrackPoints'])){
            $trackInfo = $oneNumberTracksContent['TrackPoints'];
            $data['logisticsStatus'] = self::getLogisticsStatusByText($oneNumberTracksContent['TrackingStatus']);
            $data['logisticsState'] = self::getLogisticsStatusMeaning($oneNumberTracksContent['TrackingStatus']);
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
        /* "Time":"2019-01-03T15:35:25Z",
                "Status":"DataReceived",
                "Location":"",
                "Content":"Parcel Data Received"*/
        return $oneTracksData = [
            "eventTime"     => date('Y-m-d H:i:s', strtotime($val['Time'])),
            "eventDetail"   => null,
            "eventThing"    => $val['Content'],
            "place"         => $val['Location'],
            "eventCity"     => null,
            "eventCountry"  => null,
            "eventState"    => $val['Status'],
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
     */
    public function getResult($requestUrl, $requestAction, $params, $httpMethod = 'GET', $headerArr = [])
    {
        $this->errorCode = 1;
        $headerArr = $this->requestHeaderArr();
        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl.$requestAction, $params, $httpMethod, $headerArr);
        if($response === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }

        $resultArr = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->errorMsg = 'json_decode error: ' . json_last_error_msg() . "({$response})";
            return false;
        }

        if (isset($resultArr['Succeeded']) && $resultArr['Succeeded'] === true && !empty($resultArr['Data'])){
            $this->errorCode = 0;
            return $resultArr['Data'];
        }else{
            //暂时异常错误直接返回全部
            $this->errorMsg = json_encode($resultArr, JSON_UNESCAPED_UNICODE);
            return false;
        }
    }

    /**
     * 物流状态
     * @param $key 包裹投递状态，输入值未匹配到任何包裹时，此值为空
                    DataReceived: 收到数据
                    InTransit: 运输途中
                    DeliveryReady: 到达待取
                    DeliveryTried: 尝试投递失败。部分渠道会尝试投递数次
                    Delivered: 已妥投
                    DeliveryFailed: 投递失败。 地址问题、尝试投递数次均失败、收件人搬家或拒收等
                    Returned: 已退回
                    Lost: 包裹遗失
     * @return int|mixed
     */
    public static function getLogisticsStatusByText($key)
    {
        //0暂无数据;1已签收;2运输途中;-2异常;3到达待取;4投递失败;5运输过久;6退件
        $arr = [
            "DataReceived"=>0,
            "PickUp"=>2,
            "InTransit"=>2,
            "OriginalWarehouseReceive"=>2,
            "OriginalWarehouseProcess"=>2,
            "OriginalWarehouseDeparture"=>2,
            "LinehaulAgencyReceive"=>2,
            "LinehaulAgencyDeparture"=>2,
            "LinehaulDeparture"=>2,
            "LinehaulArrival"=>2,
            "CustomsArrival"=>2,
            "CustomsCleared"=>2,
            "DeliveryReady"=>3,
            "SupplierAndLastMileReceive"=>3,
            "LastMileDelivered"=>1,
            "Delivered"=>1,
            "DeliveryTried"=>4,
            "DeliveryFailed"=>4,
            "Returned"=>6,
            "Lost"=>7,//包裹遗失
        ];

        return isset($arr[$key])?$arr[$key]:0;
    }

    /**
     * 官方状态代表含义
     * @param $key 包裹投递状态，输入值未匹配到任何包裹时，此值为空
                DataReceived: 收到数据
                InTransit: 运输途中
                DeliveryReady: 到达待取
                DeliveryTried: 尝试投递失败。部分渠道会尝试投递数次
                Delivered: 已妥投
                DeliveryFailed: 投递失败。 地址问题、尝试投递数次均失败、收件人搬家或拒收等
                Returned: 已退回
                Lost: 包裹遗失
     * @return int|mixed
     */
    public static function getLogisticsStatusMeaning($key)
    {
        $arr = [
            "DataReceived"=>"收到数据",
            "PickUp"=>"",
            "InTransit"=>"运输途中",
            "OriginalWarehouseReceive"=>"",
            "OriginalWarehouseProcess"=>"",
            "OriginalWarehouseDeparture"=>"",
            "LinehaulAgencyReceive"=>"",
            "LinehaulAgencyDeparture"=>"",
            "LinehaulDeparture"=>"",
            "LinehaulArrival"=>"",
            "CustomsArrival"=>"",
            "CustomsCleared"=>"",
            "SupplierAndLastMileReceive"=>"",
            "LastMileDelivered"=>"",
            "DeliveryReady"=>"到达待取",
            "DeliveryTried"=>"尝试投递失败。部分渠道会尝试投递数次",
            "DeliveryFailed"=>"投递失败。 地址问题、尝试投递数次均失败、收件人搬家或拒收等",
            "Delivered"=>"已妥投",
            "Returned"=>"已退回",
            "Lost"=>"包裹遗失",
        ];

        $description = isset($arr[$key])?$arr[$key]:'未知状态，请注意';
        return '['.$key.']'.$description;
    }

    /**
     * 设置请求头信息
     * @return array
     */
    public function requestHeaderArr()
    {
        //设置签名头部信息
        return array(
            'Accept: application/json',
            'Content-Type: application/json',
            "Authorization: Hc-OweDeveloper {$this->apiAccountCoding};{$this->apiToken};D8E671B7323A49FF9223AEB457257HS8",
        );
    }
}