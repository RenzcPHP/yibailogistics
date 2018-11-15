<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/17
 * Time: 10:25
 */

namespace Burning\YibaiLogistics\Tracks;

use Burning\YibaiLogistics\Tracks\Cargocompany\ATracks;

/**
 * 物流轨迹处理类
 * Class TracksStore
 *
 * 本类用于抓取各物流商订单物流轨迹，目前已对接四季正扬
 * 调用示例：
 *      //1、实例化抓取轨迹容器
        * $tracksStoreObj = new TracksStore();
 *
* $trackingNumber = 'LF408251938CN';
        * $apiUrl = 'http://api.fourseasonsfly.net';
        * $ticket = 'NDMzMkI5NEEzMDNFQzk4MjZFNTU2MzYxNEM0REE1OUUpKComXiZeNWJu';
 *
* //2、初始化四季正扬接口配置
        * $fourseasonsflyObj = new Fourseasonsfly($apiUrl, $ticket);
 *
* //3、抓取四季正扬$trackingNumber跟踪号轨迹，将结果存储到轨迹容器
        * $tracksStoreObj->addTracks($fourseasonsflyObj, $trackingNumber);
 *
* //4、获取抓取到的轨迹
        * $data = $tracksStoreObj->getTracksData();
        * var_dump($data);
 *
 * Class TracksStore
 * @package Burning\YibaiLogistics\Tracks
 */
class TracksStore
{
    /**
     * 物流轨迹结果存储容器
     * @var array
     */
    public $tracksData = [];

    /**
     * 将跟踪号$trackingNumber轨迹信息添加到轨迹存储容器中
     * @param ATracks $logisticsApi
     * @param $trackingNumber
     */
    public function addTracks(ATracks $logisticsApi, $trackingNumber)
    {
        //先获取轨迹
        $tracksContent = $logisticsApi->getTrackingInfo($trackingNumber);
        $this->tracksData = [
            'errorCode'         => $logisticsApi->getErrorCode(),
            'errorMsg'          => $logisticsApi->getErrorMsg(),
            'tracks_content'    => $tracksContent,
        ];
    }

    public function getTracksData()
    {
        return $this->tracksData;
    }

}