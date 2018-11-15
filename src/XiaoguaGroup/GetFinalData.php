<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/11/14
 * Time: 16:59
 */

namespace Burning\YibaiLogistics\XiaoguaGroup;

/**
 * 查询快递单号轨迹类
 * Class GetFinalData
 * @package Burning\YibaiLogistics\XiaoguaGroup
 */
class GetFinalData extends AXiaogua
{
    //查询快递单号轨迹 url
    public function getApiRequestUrl()
    {
        return $this->apiUrl.'/Service/GetFinalData';
    }

    /**
     * 组装post请求信息
     *
     *参数名	含义	类型	长度	必选	参数值说明
        appKey	接入的密钥	string	32	是
        finalNo	用户自定义单号	string	32	是	用户单号与快递单号二选一
        userOrderId	快递单号	string	32	是
     */
    public function assemblyPostData($params = [])
    {
        $postData = [
            'appKey' => $this->appKey,
            'finalNo' => $params['finalNo'],
            'userOrderId' => $params['userOrderId'],
        ];
        return json_encode($postData);
    }

    public function getResult($params)
    {
        $response = $this->sendRequest($params);
        if ($response === false){
//            Helper::triggerAlarm('小瓜获取轨迹接口异常', $this->getErrorMsg(), false, 1);
            return false;
        }

        $response = json_decode($response, true);
        if (!isset($response['code'])){
            //没按正常格式进行返回
            return false;
        }
        //0表示成功，其他为错误代码
        if ($response['code'] != 0){
            $this->errorMsg = '【'.date('Y-m-d H:i:s').'】获取轨迹失败原因：'.$response['msg'].' , 【tracking_number='.$params['finalNo'].'】';
//            //获取轨迹请求参数
//            $data = [
//                'errorMsg'=>$this->errorMsg,
//                'requestParams'=>$params,
//            ];
//            Helper::triggerAlarm('小瓜获取轨迹接口请求响应错误代码-'.$response['code'], $data, $sendMailFlag = true, 6);
            return false;
        }

        //返回轨迹数据
        return $response['data'];
    }


}