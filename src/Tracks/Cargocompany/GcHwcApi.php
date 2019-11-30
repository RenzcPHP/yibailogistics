<?php


namespace Burning\YibaiLogistics\Tracks\Cargocompany;

/**
 * 谷仓api接口类
 * Class GcHwcApi
 */
class GcHwcApi
{
    // bof 正式
    public $_appToken = '';
    public $_appKey = '';
    private $YB_US_appToken = '594d9c2a24e95de72aa632695face710'; // token 默认易佰美仓
    private $YB_US_appKey = 'fd19a638c984e06795a9a957144e3caf'; // key 默认易佰美仓
    private $YB_UK_appToken = '65973c830a9497a4764461c71607bc35';//易佰英仓
    private $YB_UK_appKey = '1ca5693d6626953c0917fc01be137fc1';//易佰英仓
    private $YB_CZ_appToken = '0f39097c77724d79acbe9f53041f4abd';//易佰捷克仓
    private $YB_CZ_appKey = '2c23ac9a809dfeff67bc4dbe01c8e2f9';//易佰捷克仓
    private $KV_US_appToken = '18ad9785c3902e62e8ea5f55470e412a'; //Kevin美仓
    private $KV_US_appKey = 'f1c3f6be5e7dc3256880a44d5655bed5'; //Kevin美仓
    private $KV_UK_appToken = '1cdf38aef51e64bc871e7db6136a58ff';//Kevin英仓
    private $KV_UK_appKey = 'eb5532c9b54de426b58e5efd0c4fad0a';//Kevin英仓
    private $KV_CZ_appToken = '8b87a17240abcff380e8770e9e7b367f';//Kevin捷克仓
    private $KV_CZ_appKey = '6437f1d13acced9629540815ce4b6983'; //Kevin捷克仓
    private $YB_AU_appToken = '63d4704af1e63bc1c579f2809bba7c66';//易佰澳仓
    private $YB_AU_appKey = 'e1ddcf9c31a3de9f87d35c44187b1ed2';//易佰澳仓

    private $appToken_G332 = '594d9c2a24e95de72aa632695face710'; // token 默认易佰美仓
    private $appKey_G332 = 'fd19a638c984e06795a9a957144e3caf'; // key 默认易佰美仓
    private $appToken_G679 = '65973c830a9497a4764461c71607bc35';//易佰英仓
    private $appKey_G679 = '1ca5693d6626953c0917fc01be137fc1';//易佰英仓
    private $appToken_G836 = '0f39097c77724d79acbe9f53041f4abd';//易佰捷克仓
    private $appKey_G836 = '2c23ac9a809dfeff67bc4dbe01c8e2f9';//易佰捷克仓
    private $appToken_G677 = '18ad9785c3902e62e8ea5f55470e412a'; //Kevin美仓
    private $appKey_G677 = 'f1c3f6be5e7dc3256880a44d5655bed5'; //Kevin美仓
    private $appToken_236 = '1cdf38aef51e64bc871e7db6136a58ff';//Kevin英仓
    private $appKey_236 = 'eb5532c9b54de426b58e5efd0c4fad0a';//Kevin英仓
    private $appToken_G1088 = '8b87a17240abcff380e8770e9e7b367f';//Kevin捷克仓
    private $appKey_G1088 = '6437f1d13acced9629540815ce4b6983'; //Kevin捷克仓
    private $appToken_G1005 = '63d4704af1e63bc1c579f2809bba7c66';//易佰澳仓
    private $appKey_G1005 = 'e1ddcf9c31a3de9f87d35c44187b1ed2';//易佰澳仓

//    protected $wsdl = 'http://oms.goodcang.com/default/svc/wsdl';
    //走代理
    /*
     * modified at 2019-09-10 17:00 by Caiyu
正式WSDL地址：https://oms.goodcang.net/default/svc/wsdl
正式线service地址(curl数据)：https://oms.goodcang.net/default/svc/web-service
    */
    protected $wsdl = 'https://oms.goodcang.net/default/svc/wsdl';//'http://ag.yibainetwork.com:97/default/svc/wsdl';//'https://oms.goodcang.com/default/svc/wsdl';//新地址
    protected $wsdl_file = 'https://oms.goodcang.net/default/svc/web-service';//'http://oms.goodcang.com/default/svc/web-servic';

    /**
     * soap链接成功保存起来，避免下次重复链接
     * @var array
     */
    public static $clientObj = null;

    // eof 正式

    //美国的州对应的简称  如果订单里面是全称  转换为简称  并记录日志
    public $_country_abbreviation = array(
        'Alabama' => 'AL', 'Alaska' => 'AK', 'Arizona' => 'AZ', 'Arkansas' => 'AR', 'California' => 'CA',
        'Colorado' => 'CO', 'Connecticut' => 'CT', 'Delaware' => 'DE', 'Florida' => 'FL', 'Georgia' => 'GA',
        'Hawaii' => 'HI', 'Idaho' => 'ID', 'Illinois' => 'IL', 'Indiana' => 'IN', 'Iowa' => 'IA', 'Kansas' => 'KS',
        'Kentucky' => 'KY', 'Louisiana' => 'LA', 'Maine' => 'ME', 'Maryland' => 'MD', 'Massachusetts' => 'MA',
        'Michigan' => 'MI', 'Minnesota' => 'MN', 'Mississippi' => 'MS', 'Missouri' => 'MO', 'Montana' => 'MT',
        'Nebraska' => 'NE', 'Nevada' => 'NV', 'New Hampshire' => 'NH', 'New Jersey' => 'NJ', 'New Mexico' => 'NM',
        'New York' => 'NY', 'North Carolina' => 'NC', 'North Dakota' => 'ND', 'Ohio' => 'OH', 'Oklahoma' => 'OK',
        'Oregon' => 'OR', 'Pennsylvania' => 'PA', 'Rhode Island' => 'RI', 'South Carolina' => 'SC',
        'South Dakota' => 'SD', 'Tennessee' => 'TN', 'Texas' => 'TX', 'Utah' => 'UT', 'Vermont' => 'VT',
        'Virginia' => 'VA', 'Washington' => 'WA', 'West Virginia' => 'WV', 'Wisconsin' => 'WI', 'Wyoming' => 'WY',
        'American Samoa' => 'AS', 'District Of Columbia' => 'DC', 'Federated States Of Micronesia' => 'FM',
        'Guam' => 'GU', 'Marshall Islands' => 'MH', 'Northern Mariana Islands' => 'MP', 'Palau' => 'PW',
        'Puerto Rico' => 'PR', 'Virgin Islands' => 'VI', 'Armed Forces Africa' => 'AE', 'Armed Forces Americas' => 'AA',
        'Armed Forces Canada' => 'AE', 'Armed Forces Europe' => 'AE', 'Armed Forces Middle East' => 'AE',
        'Armed Forces Pacific' => 'AP',
    );

    public $_active = true; // 是否启用发送到oms
    protected $_client = null; // SoapClient
    public $_error = '';

    //当前账号
    public $account;

    public function __construct()
    {
//        if (DC_ENV == 'product') {
//            $this->changeConfig();
//        } else {
//            $this->YB_US_appToken = $this->YB_UK_appToken = $this->YB_CZ_appToken = $this->KV_US_appToken
//                = $this->KV_UK_appToken = $this->KV_CZ_appToken = $this->YB_AU_appToken
//                = '843c57d0df52e54eb455d6f61b21ed6e';
//            $this->YB_US_appKey = $this->YB_UK_appKey = $this->YB_CZ_appKey = $this->KV_US_appKey
//                = $this->KV_UK_appKey = $this->KV_UK_appKey = $this->YB_AU_appKey = '87850c809e0e4499e50821b28fefa332';
//            $this->wsdl = 'http://202.104.134.94:61639/default/svc/wsdl';
//            $this->wsdl_file = 'http://202.104.134.94:61639/default/svc/web-service';
//        }
    }

    public function changeConfig($country = 'YB_US')
    {
        switch ($country) {
            case 'YB_US':
                $this->_appToken = $this->YB_US_appToken;
                $this->_appKey = $this->YB_US_appKey;
                break;
            case 'YB_UK':
                $this->_appToken = $this->YB_UK_appToken;
                $this->_appKey = $this->YB_UK_appKey;
                break;
            case 'YB_CZ':
                $this->_appToken = $this->YB_CZ_appToken;
                $this->_appKey = $this->YB_CZ_appKey;
                break;
            case 'KV_US':
                $this->_appToken = $this->KV_US_appToken;
                $this->_appKey = $this->KV_US_appKey;
                break;
            case 'KV_UK':
                $this->_appToken = $this->KV_UK_appToken;
                $this->_appKey = $this->KV_UK_appKey;
                break;
            case 'KV_CZ':
                $this->_appToken = $this->KV_CZ_appToken;
                $this->_appKey = $this->KV_CZ_appKey;
                break;
            case 'YB_AU':
                $this->_appToken = $this->YB_AU_appToken;
                $this->_appKey = $this->YB_AU_appKey;
                break;
        }

    }

    public function setConfig($account)
    {
        $this->account = $account;
        $appToken = 'appToken_' . $account;
        $appKey = 'appKey_' . $account;
        $this->_appToken = $this->$appToken;
        $this->_appKey = $this->$appKey;
//        if (DC_ENV != 'product') {
//            $this->_appToken = '843c57d0df52e54eb455d6f61b21ed6e';
//            $this->_appKey = '87850c809e0e4499e50821b28fefa332';
//            $this->wsdl = 'http://202.104.134.94:61639/default/svc/wsdl';
//            $this->wsdl_file = 'http://202.104.134.94:61639/default/svc/web-service';
//        }
    }

    protected function getClient()
    {
        if (isset(static::$clientObj) && !empty(static::$clientObj)){
            return static::$clientObj;
        }

        static::$clientObj = $this->setClient();

        return static::$clientObj;
    }

    protected function setClient()
    {
        $omsConfig = array(
            'active' => '1',
            'appToken' => $this->_appToken,
            'appKey' => $this->_appKey,
            'timeout' => '60',
            'wsdl' => $this->wsdl,
            'wsdl-file' => $this->wsdl_file
        );
//        var_dump($omsConfig);
        $wsdl = $omsConfig['wsdl'];
        $this->_appToken = $omsConfig['appToken'];
        $this->_appKey = $omsConfig['appKey'];
        // 超时
        $timeout = $omsConfig['timeout'];

        $streamContext = stream_context_create(array(
            'ssl' => array(
                'verify_peer' => false,
                'allow_self_signed' => true
            ),
            'socket' => array()
        ));

        $options = array(
            "trace" => true,
            "connection_timeout" => $timeout,
            "encoding" => "utf-8",

            'ssl'   => array(
                'verify_peer'          => false
            ),
            'https' => array(
                'curl_verify_ssl_peer'  => false,
                'curl_verify_ssl_host'  => false
            )
        );

        return new \SoapClient($wsdl, $options);
    }

    /**
     * 调用webservice
     * ====================================================================================
     *
     * @param unknown_type $req
     * @return Ambigous <mixed, NULL, multitype:, multitype:Ambigous <mixed,
     *         NULL> , StdClass, multitype:Ambigous <mixed, multitype:,
     *         multitype:Ambigous <mixed, NULL> , NULL> , boolean, number,
     *         string, unknown>
     */
    protected function callService($req)
    {
        $client = $this->getClient();
        $req['appToken'] = $this->_appToken;
        $req['appKey'] = $this->_appKey;
        $response = $client->callService($req);
//{"response":"{"ask":"Failure","message":"Failure","Error":{"errMessage":"no data","errCode":10103086}}"}
        $result = json_decode(json_encode($response), true);
        if (empty($result) || !isset($result['response'])){
            return [];
        }

        return json_decode($result['response'], true);
    }

    /**
     * 轨迹查询接口
     * @param $trackingNumber
     * @return array
     */
    public function queryTrackingStatus($trackingNumber)
    {
        $result = array(
            'ask' => 'Failure',
            'message' => '没请求到数据'
        );
        try {
            $paramsData = ['refrence_no'=>$trackingNumber];
            $req = array(
                'service' => 'queryTrackingStatus',
                'paramsJson' => json_encode($paramsData)
            );

            $return = $this->callService($req);
            //{"ask":"Failure","message":"Failure","Error":{"errMessage":"no data","errCode":10103086}}
            if (!isset($return['ask'])){
                $result['message'] = "请求失败";
                if (!empty($return)){
                    $result['message'] .= "(".json_encode($return, JSON_UNESCAPED_UNICODE).")";
                }
            }elseif($return['ask'] == 'Failure'){
                if (!empty($return['Error']['errMessage'])){
                    $result['message'] = "[{$return['Error']['errCode']}]{$return['Error']['errMessage']}";
                }else{
                    $result['message'] = json_encode($return, JSON_UNESCAPED_UNICODE);
                }
            }elseif($return['ask'] == 'Success'){
                $result['ask'] = $return['ask'];
                $result['message'] = $return['message'];
                $result['data'] = $return['data'];
            }
        } catch (\Exception $e) {
            $result['message'] = "谷仓接口调用失败：".$e->getMessage();
        }

        return $result;
    }

    /**
     * 由对象为数组
     * @param obj $obj
     * @return array
     */
    public function objectToArray($obj)
    {
        $arr = '';
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        if(is_array($_arr)){
            foreach($_arr as $key => $val){
                $val = (is_array($val) || is_object($val)) ? $this->objectToArray($val) : $val;
                $arr[$key] = $val;
            }
        }
        return $arr;
    }
}