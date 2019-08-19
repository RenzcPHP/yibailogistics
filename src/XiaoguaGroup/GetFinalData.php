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
            //$this->getErrorMsg()
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
            return false;
        }

        //返回轨迹数据
        return $response['data'];
    }


}