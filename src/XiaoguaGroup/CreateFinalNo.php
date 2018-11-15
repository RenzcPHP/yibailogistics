<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/14
 * Time: 16:50
 */

namespace Burning\YibaiLogistics\XiaoguaGroup;

/**
 * 预报快递单号类
 * Class CreateFinalNo
 * @package Burning\YibaiLogistics\XiaoguaGroup
 */
class CreateFinalNo extends AXiaogua
{
    //预报快递单号api url
    public function getApiRequestUrl()
    {
        return $this->apiUrl.'/Service/createFinalNo';
    }

    /**
     * 组装post请求信息
     *
     * 参数名	含义	类型	长度	必选	参数值说明
    appKey	接入的密钥	string	32	是
    to	目地国家二字码	string	2	是
    finalNo	快递单号	string	32	是
    finalBakNo	快递备用单号	string	32	否
    userOrderId	用户自定义单号	string	32	是
    zipcode	收货国家邮编	string	32	是
    finalType	快递类型	Int	16	是	参看附录2
    flag	优先查询标识	Int	16	否	1优先抓取
    batchNo	批次号统计某一批快递单号的标识	string	32	否	-
     *
     * @param $params
     * @return array
     */
    public function assemblyPostData($params)
    {
        $to = $params['to'];
        if ($to == 'UK'){
            $to = 'GB';
        }
        $postData = [
            'appKey'        => $this->appKey,
            'to'            => $to,
            'finalNo'       => $params['finalNo'],
            'finalBakNo'    => $params['finalBakNo'],
            'userOrderId'   => $params['userOrderId'],
            'zipcode'       => $params['zipcode'],
            'finalType'     => $params['finalType'],
            'flag'          => $params['flag'],
            'batchNo'       => $params['batchNo'],
        ];

        return json_encode($postData);
    }

    public function getResult($params)
    {
        $this->errorCode = 0;
        $response = $this->sendRequest($params);
        if ($response === false){
            $this->errorCode = -1;
//            Helper::triggerAlarm('小瓜预报接口异常', $this->getErrorMsg(), false, 1);
            return false;
        }

//        $logData = [
//            'response'=>$response,
//            'requestParams'=>$params,
//        ];
//        Helper::triggerAlarm('小瓜订单预报接口请求响应数据', $logData, false, 300);

        $response = json_decode($response, true);
        if (!isset($response['code'])){
            //503 响应数据格式不一致，html格式
            $this->errorCode = -2;
            return false;
        }
        //0表示成功，其他为错误代码
        if ($response['code'] != 0){
            $this->errorCode = $response['code'];
            //预报请求参数
            $this->errorMsg = '【'.date('Y-m-d H:i:s').'】预报失败原因：'.$response['msg'].' , 【tracking_number='.$params['finalNo'].'】';
//            $data = [
//                'code'=>$this->errorCode,
//                'errorMsg'=>$this->errorMsg,
//                'requestParams'=>$params,
//            ];
//            Helper::triggerAlarm('小瓜订单预报接口请求响应错误代码-'.$response['code'], $data, $sendMailFlag = true, 5);
            return false;
        }

        //预报成功
        return true;
    }

}