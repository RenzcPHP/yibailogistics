<?php


namespace Burning\YibaiLogistics\Tracks\Cargocompany;


use Burning\YibaiLogistics\core\Httphelper;

/**
 * Shopee轨迹查询接口对接
 * 官方接口文档：https://open.shopee.com/documents?module=3&type=1&id=392
 *
 * Class Shopee
 * @package Burning\YibaiLogistics\Tracks\Cargocompany
 */
class Shopee extends ATracks
{
    protected $apiUrl;
    public $apiVersion = '/api/v1/'; //版本
    public $shopId;        //店铺id
    public $partnerId;    //合作者id
    public $secretKey;    //秘钥
    /**
     * 抓取轨迹所用平台订单号
     * @var null
     */
    protected $ordersn = null;
    /**
     * 实例化
     * Shopee constructor.
     * @param array $apiConfig
     */
    public function __construct(array $apiConfig)
    {
        $this->shopId = (int) $apiConfig['shopId'];
        $this->partnerId = (int) $apiConfig['partnerId'];
        $this->secretKey = $apiConfig['secretKey'];
        $this->apiUrl = rtrim($apiConfig['apiUrl'], '/');
        $this->ordersn = $this->aquireOrderSn($apiConfig['ordersn']);
    }

    /**
     * 获取平台订单号
     * @param $platformOrderId
     * @return mixed
     */
    private function aquireOrderSn($platformOrderId)
    {
        if(strpos($platformOrderId,'_')){
            //对拆分订单处理
            $platformOrderIdArr = explode('_', $platformOrderId);
            $platformOrderId = $platformOrderIdArr[0];
        }elseif(strpos($platformOrderId,'-')){
            //补寄
            $platformOrderIdArr = explode('-', $platformOrderId);
            $platformOrderId = $platformOrderIdArr[0];
        }

        return $platformOrderId;
    }

    /**
     * 获取订单物流轨迹（获取追踪信息）
     * @param string $numbers 追踪号
     * @return array|mixed
     */
    public function getTrackingInfo($numbers = '')
    {
        $this->initAttributeParams();

        $params = [
            'ordersn'=>$this->ordersn,
            'tracking_number'=>$numbers
        ];
        $result = $this->getResult($this->apiUrl, 'logistics/tracking', $params, $httpMethod = 'POST', $headerArr = []);
        return $this->parseTrackingInfo($numbers, $result);
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
        /*{
"tracking_info":Array[9],
"tracking_number":"ID194777176061E",
"logistics_status":"LOGISTICS_DELIVERY_DONE",
"request_id":"d9386538b6ec22dd50ea135f571613cd",
"ordersn":"19110622092JDCB",
"forder_id":""
}*/
        $data = [
            'error'=>0,
            'msg'=>'',
            'trackingNumber'=>$oneNumberTracksContent['tracking_number'],
            'trackingInfo'=>'',
            'logisticsStatus'=>0,
            'logisticsState'=>''
        ];

        $trackingInfo = [];
        //轨迹
        $trackInfo = $oneNumberTracksContent['tracking_info'];
        $endOne = $trackInfo[0];//end($trackInfo);
        //The 3PL logistics status for the order. Applicable values: See Data Definition - TrackingLogisticsStatus.
        $data['logisticsStatus'] = self::getLogisticsStatusByText($endOne['status']);
        $data['logisticsState'] = $oneNumberTracksContent['logistics_status'];
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
        /*
            "status":"DELIVERED",
            "ctime":1574052600,
            "description":"[BOGOR]DELIVERED TO [LIUS | 18-11-2019 11:50 ]"*/
        return $oneTracksData = [
            "eventTime"     => date('Y-m-d H:i:s', $val['ctime']),
            "eventDetail"   => null,
            "eventThing"    => $val['description'],
            "place"         => '',
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
     * 物流状态 TrackingLogisticsStatus
     * TrackingLogisticsStatus 字段定义
     * https://open.shopee.com/documents?module=63&type=2&id=50
     * @param $key
     * @return int|mixed
     */
    public static function getLogisticsStatusByText($key)
    {
        //0暂无数据;1已签收;2运输途中;-2异常;3到达待取;4投递失败;5运输过久;6退件
        $arr = [
            "INITIAL"=>0,
            "ORDER_INIT"=>0,
            "ORDER_SUBMITTED"=>0,
            "ORDER_FINALIZED"=>0,
            "ORDER_CREATED"=>0,
            "PICKUP_REQUESTED"=>0,
            "PICKUP_PENDING"=>0,
            "PICKED_UP"=>2,
            "DELIVERY_PENDING"=>2,
            "DELIVERED"=>1,
            "PICKUP_RETRY"=>0,
            "TIMEOUT"=>0,
            "LOST"=>0,
            "UPDATE"=>0,
            "UPDATE_SUBMITTED"=>0,
            "UPDATE_CREATED"=>0,
            "RETURN_STARTED"=>0,
            "RETURNED"=>0,
            "RETURN_PENDING"=>0,
            "RETURN_INITIATED"=>0,
            "EXPIRED"=>0,
            "CANCEL"=>0,
            "CANCEL_CREATED"=>0,
            "CANCELED"=>0,
            "FAILED_ORDER_INIT"=>0,
            "FAILED_ORDER_SUBMITTED"=>0,
            "FAILED_ORDER_CREATED"=>0,
            "FAILED_PICKUP_REQUESTED"=>0,
            "FAILED_PICKED_UP"=>0,
            "FAILED_DELIVERED"=>0,
            "FAILED_UPDATE_SUBMITTED"=>0,
            "FAILED_UPDATE_CREATED"=>0,
            "FAILED_RETURN_STARTED"=>0,
            "FAILED_RETURNED"=>0,
            "FAILED_CANCEL_CREATED"=>0,
            "FAILED_CANCELED"=>0,
        ];

        return isset($arr[$key])?$arr[$key]:0;
    }

    /**
     * LogisticsStatus 字段定义
     * https://open.shopee.com/documents?module=63&type=2&id=50
     * @return array
     */
    public static function enumLogisticsStatus()
    {
        return [
            "LOGISTICS_NOT_START",
            "LOGISTICS_REQUEST_CREATED",
            "LOGISTICS_PICKUP_DONE",
            "LOGISTICS_PICKUP_RETRY",
            "LOGISTICS_PICKUP_FAILED",
            "LOGISTICS_DELIVERY_DONE",
            "LOGISTICS_DELIVERY_FAILED",
            "LOGISTICS_REQUEST_CANCELED",
            "LOGISTICS_COD_REJECTED",
            "LOGISTICS_READY",
            "LOGISTICS_INVALID",
            "LOGISTICS_LOST",
            "LOGISTICS_UNKNOWN"
        ];
    }

    /**
     * 描述:发送请求
     * @param $requestUrl
     * @param string $requestAction
     * @param $params
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed
     */
    public function getResult($requestUrl, $requestAction, $params, $httpMethod = 'POST', $headerArr = [])
    {
        $this->errorMsg = '';
        $requestUrl = $requestUrl.$this->apiVersion.$requestAction;
        $requestUrlTmp = "https://partner.shopeemobile.com".$this->apiVersion.$requestAction;
        $params['shopid'] = $this->shopId;
        $params['partner_id'] = $this->partnerId;
        $params['timestamp'] = time();
        $reqData = json_encode($params);
        $headers = [
            'Content-type: application/json',
            'Authorization: '.hash_hmac('sha256', $requestUrlTmp.'|'.$reqData, trim($this->secretKey))
        ];

        $httpClient = new Httphelper();
        $responseData = $httpClient->sendRequest($requestUrl, $reqData, $httpMethod, $headers);
        if($responseData === false){
            $this->errorMsg = $httpClient->getErrorMessage();
            return false;
        }

        $result = json_decode($responseData,true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->errorMsg = 'json_decode error: ' . json_last_error_msg()."({$responseData})";
            return false;
        }
        if (isset($result['error'])){
            $this->errorMsg = $this->getErrorDescription($result['error']);
            return false;
        }

        return $result;
    }

    /**
     * 描述:shopEE官方接口错误描述
     * @param $errorKey
     * @return string
     */
    protected function getErrorDescription($errorKey)
    {
        $errorArr = [
            'error_params' => 'There are errors in the input parameters',
            'error_auth' => 'The request is not authenticated. Ex: signature is wrong',
            'error_server' => 'An error has occurred',
            'error_not_support' => 'Not support action',
            'error_inner_error' => 'An inner error has occurred',
        ];

        $description = isset($errorArr[$errorKey])?$errorArr[$errorKey]:'未知描述';
        return '['.$errorKey.']'.$description;
    }

    /**
     * 订单状态描述
     * @param $orderStatus
     * @return mixed|string
     */
    protected function getOrderStatusDescription($orderStatus)
    {
        $orderStatusArr = [
            'UNPAID'            => '未支付',
            'READY_TO_SHIP'     => '准备发货',
            'RETRY_SHIP'        => '', // 缺省，谁来填一下啥状态
            'SHIPPED'           => '已发货',
            'TO_CONFIRM_RECEIVE'=> '确认收货',
            'IN_CANCEL'         => '取消中',
            'CANCELLED'         => '已取消',
            'TO_RETURN'         => '退件',
            'COMPLETED'         => '已完成'
        ];

        $description = isset($orderStatusArr[$orderStatus])?$orderStatusArr[$orderStatus]:'未知订单状态';
        return "[{$orderStatus}]{$description}";
    }
}