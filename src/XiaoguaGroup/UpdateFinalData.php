<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/14
 * Time: 17:19
 */

namespace Burning\YibaiLogistics\XiaoguaGroup;

/**
 * 修改快递单号信息
 * Class UpdateFinalData
 * @package Burning\YibaiLogistics\XiaoguaGroup
 */
class UpdateFinalData extends AXiaogua
{
    //预报快递单号api url
    public function getApiRequestUrl()
    {
        return $this->apiUrl.'/Service/updateFinalData';
    }

    /**
     * 组装post请求信息
     *
     * 参数名	含义	类型	长度	必选	参数值说明
     * appKey	接入的密钥	string	32	是	-
     * finalNo	快递单号	string	32	是	必填
     * userOrderId	快递单号	string	32	是	根据自定义单号当中条件来修改数据
     * finalType	快递类型	Int	16	是	参看附录2
     *
     * @param $params
     * @return array
     */
    public function assemblyPostData($params)
    {
        $postData = [
            'appKey'        => $this->appKey,
            'finalNo'       => $params['finalNo'],
            'userOrderId'   => $params['userOrderId'],
            'finalType'     => $params['finalType'],
        ];

        return json_encode($postData);
    }

    public function getResult($params)
    {
        $response = $this->sendRequest($params);
        if ($response === false){
//            Helper::triggerAlarm('小瓜修改快递单号接口异常', $this->getErrorMsg(), false, 1);
            return false;
        }

        $response = json_decode($response, true);
        if (!isset($response['code'])){
            return false;
        }
        //0表示成功，其他为错误代码
        if ($response['code'] != 0){
            //预报请求参数
            $this->errorMsg = '【'.date('Y-m-d H:i:s').'】修改快递单号信息失败原因：'.$response['msg'].' , 【tracking_number='.$params['finalNo'].'】';
//            $data = [
//                'errorMsg'=>$this->errorMsg,
//                'requestParams'=>$params,
//            ];
//            Helper::triggerAlarm('小瓜修改快递单号信息接口请求响应错误代码-'.$response['code'], $data, $sendMailFlag = true, 24);
            return false;
        }

        //修改成功
        return true;
    }

}