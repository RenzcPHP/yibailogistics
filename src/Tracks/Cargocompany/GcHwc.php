<?php


namespace Burning\YibaiLogistics\Tracks\Cargocompany;


/**
 * 谷仓海外仓
 * Class GcHwc
 * @package common\component\logisticsapi\core
 */
class GcHwc extends ATracks
{
    /**
     * 调用接口的安全验证凭据
     * @var string
     */
    protected $account = '';

    /**
     * 各仓账户
     * @var array
     */
    public static $accountList = [
        'G332',// '594d9c2a24e95de72aa632695face710'; // token 默认易佰美仓
        'G679',// '65973c830a9497a4764461c71607bc35';//易佰英仓
        'G836',// '0f39097c77724d79acbe9f53041f4abd';//易佰捷克
        'G677',// '18ad9785c3902e62e8ea5f55470e412a'; //Kevin美
        '236',// '1cdf38aef51e64bc871e7db6136a58ff';//Kevin英仓
        'G1088',// '8b87a17240abcff380e8770e9e7b367f';//Kevin捷仓
        'G1005',// '63d4704af1e63bc1c579f2809bba7c66';//易佰澳仓
    ];

    /**
     * 初始化配置
     * GcHwc constructor.
     * @param string $apiUrl
     * @param string $gcAccount 谷仓账号前缀
     */
    public function __construct($apiUrl = '', $gcAccount = '')
    {
        $this->account = $gcAccount;
    }

    /**
     * 设置谷仓账号
     * @param string $gcAccount
     */
    public function setGcAccount($gcAccount = '')
    {
        $this->account = $gcAccount;
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

        $numbersArr = explode(',', trim($numbers, ','));
        if (count($numbersArr) > $this->maxTracksQueryNumber){
            $this->errorCode = 1;
            $this->errorMsg = '最多查询'.$this->maxTracksQueryNumber.'个物流单号';
            return false;
        }

        $requestUrl = $this->apiUrl;
        $headerArr = $this->requestHeaderArr();
        $result = $this->getResult($requestUrl, '', $numbers, 'POST', $headerArr);
        $tracksData = !empty($result['trajectory_information'][0]['item'])?$result['trajectory_information'][0]['item']:[];
//        var_dump($result['trajectory_information'][0]);die;
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
            $data['msg'] = !empty($this->errorMsg)?$this->errorMsg:'没查询到轨迹信息，请确认跟踪号是否存在';
        }
        $data['trackingInfo'] = $trackingInfo;
        return $data;
    }

    /**
     * 组装一条轨迹
     * @param $val
     * @return array
    occurDate       String 轨迹发生时间（ YYYY-MM-DD HH24:MI:SS ）
    occurAddress    String 轨迹发生地点以及发生事件
    trackCode       String 轨迹代码
    trackContent    String 轨迹代码补充
     */
    protected function oneTracksData($val)
    {
        /*
        "date_time":"2019-03-05 08:59:00",
        "code":"TMS_FD",
        "code_info":"签收",
        "utc_time":"2019-03-05 00:59:00",
        "info":"Delivered, Front Door/Porch",
        "location":"SPRINGFIELD VA 22150"
        */

        //utc时间转为北京时间
//        $eventTime = Country::timezoneConvert($val['utc_time'], 'UTC', $toTimeZone = 'Asia/Shanghai');
//        if ($eventTime === false){
        //北京时间
        $eventTime = $val['date_time'];
//        }

        $eventThing = "[{$val['code']}]{$val['code_info']} {$val['info']}";
        return $oneTracksData = [
            "eventTime"     => $eventTime,//轨迹发生地点以及发生事件
            "eventDetail"   => null,
            "eventThing"    => $eventThing,
            "place"         => $val['location'],
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
     * @param array|string $trackingNumber
     * @param string $httpMethod
     * @param array $headerArr
     * @return bool|mixed|string
     * @return array|bool|mixed
     */
    public function getResult($requestUrl, $requestAction, $trackingNumber, $httpMethod = 'GET', $headerArr = [])
    {
        $this->errorCode = 1;

        $gcHwcApi = new GcHwcApi();
        if (!in_array($this->account, self::$accountList)){
            $this->errorMsg = "谷仓账号{$this->account}没有映射对应的token(token缺失)，请技术维护当前账号token";
            return false;
        }
        $gcHwcApi->setConfig($this->account);//G836-190402-2531
        $result = $gcHwcApi->queryTrackingStatus($trackingNumber);
        if (!isset($result['ask'])){
            $this->errorMsg = json_encode($result, JSON_UNESCAPED_UNICODE);
            return false;
        }

        //成功
        if ($result['ask'] == 'Success'){
            $this->errorCode = 0;
            return $result['data'];
        }

        $this->errorMsg = !empty($result['message'])?$result['message']:json_encode($result, JSON_UNESCAPED_UNICODE);
        return false;
    }

    /**
     * 设置请求头信息
     * @return array
     */
    public function requestHeaderArr()
    {
        return [
            "Content-Type: application/json",
        ];
    }

}