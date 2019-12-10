<?php


namespace Burning\YibaiLogistics\Python;


use Burning\YibaiLogistics\core\Httphelper;

/**
 * 与python轨迹接口对接类
 * Class PythonCrawlingTracks
 * @package common\component\wms
 */
class PythonCrawlingTracks
{
    protected static $mainUrl = 'http://112.74.190.222:5005';
    public static $errorMsg;

    /**
     * 将未推送到python那边的订单推送过去（python那边抓轨迹用）
     * @param array $orderInfo
     * @param string $mappingCode
     * @return bool|mixed
     */
    public static function yubao($orderInfo = [], $mappingCode='')
    {
        $postData = [
            'order_id'          => $orderInfo['order_id'],
            'tracking_number'   => $orderInfo['tracking_number'],
            'add_time'          => date('Y-m-d H:i:s'),
            '100_code'          => $mappingCode,//'chukou1'
        ];
        return self::httpRequest('/bowen/api/wuliu_id/push', $postData, 'POST');
    }

    /**
     * 批量获取轨迹数据
     *
     * @param string $addTime 最后一次获取轨迹时间
     * @param int $page
     * @param int $limit
     * @return bool|mixed
     */
    public static function batchAquireTracks($addTime = '', $page = 1, $limit = 100)
    {
        $addTime = urlencode($addTime);
        $requestAction = "/bowen/api/wuliu_query/page/{$page}/limit/{$limit}/add_time/{$addTime}";

        return self::httpRequest($requestAction);
    }

    /**
     * 推送已签收订单到Python
     * @param $order
     * @return bool|mixed
     */
    public static function pushReceivedOrder($order)
    {
        $postData = [
            'tracking_number'   => $order['tracking_number'],
            'add_time'          => date('Y-m-d H:i:s'),
        ];
        $requestAction = '/bowen/api/wuliu_id_change/push';
        return self::httpRequest($requestAction, $postData, 'POST');
    }

    /**
     * 单个及批量物流订单查询
     * @param string $trackingNumber
     * @return bool|mixed
     */
    public static function aquirePythonTracks($trackingNumber='')
    {
        $requestAction = '/bowen/api/wuliu_query/tracking_number/'.$trackingNumber;
        return self::httpRequest($requestAction);
    }

    /**
     * 公共请求类
     * @param $requestAction
     * @param $postData
     * @param string $httpMethod
     * @return bool|mixed
     */
    public static function httpRequest($requestAction, $postData='', $httpMethod='GET')
    {
        $requestUrl = self::$mainUrl.$requestAction;

        $httpClient = new Httphelper();
        $response = $httpClient->sendRequest($requestUrl, $postData, $httpMethod);
        if($response === false){
            self::$errorMsg = $httpClient->getErrorMessage();
            return false;
        }

        $responseData = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error()){
            self::$errorMsg = "json_decode failed:".json_last_error_msg()."({$response})";
            return false;
        }
        if (!isset($responseData['status'])){
            self::$errorMsg = $response;
            return false;
        }

        if ($responseData['status'] != 'Successful'){
            self::$errorMsg = $responseData['response'];
            return false;
        }

        return $responseData;
    }
}