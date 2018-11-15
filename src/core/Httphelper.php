<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/22
 * Time: 10:49
 */

namespace Burning\YibaiLogistics\core;

/**
 * http请求帮助类
 *
 * 调用示例：
 *          1.post请求：
 *                  $responseData = $this->http_helper->sendRequest($requestUrl, $paramsData, 'POST', $curlOption = []);
 *          2.get请求：
 *                  $responseData = $this->http_helper->sendRequest($requestUrl);
 *          3.网络原因或远程服务器响应异常时获取失败详情：
                   if($responseData === false){
                       $errorMsg = $this->http_helper->getErrorMessage();
                   }
 *
 * Class Httphelper
 * @package Burning\YibaiLogistics\core
 */
class Httphelper
{
    private $requestUrl;
    /**
     * 请求方式
     * @var string
     */
    private $httpMethod = 'GET';
    /**
     * 请求最后一次的错误代码  0 表示成功
     *  the error code if one exists.
     *  Note that code 0 means its not an error, it means success.
     * @var int
     */
    protected $errorCode = 0;
    /**
     * 会话最后一次错误的字符串
     * @var string
     */
    protected $errorMessage = '';
    /**
     * 请求响应数据
     * @var string
     */
    protected $response = '';
    /**
     * 请求响应header头信息
     * @var array
     */
    protected $responseHeaders;
    /**
     * http请求状态码
     * @var
     */
    protected $httpStatusCode;

    /**
     * curl默认参数配置
     */
    public static $CURL_OPTS = [
        CURLOPT_CONNECTTIMEOUT  => 25,//连接阶段的超时(timeout for the connect phase)
        CURLOPT_RETURNTRANSFER  => true,//TRUE 将curl_exec()获取的信息以字符串返回，而不是直接输出。
        CURLOPT_TIMEOUT         => 60,//设置允许请求的最长时间(set maximum time the request is allowed to take)
        CURLOPT_FRESH_CONNECT   => 1,//Use a new connection. Force a new connection to be used
        CURLOPT_FOLLOWLOCATION  => true,//Follow HTTP redirects. 这样能够让cURL支持页面链接跳转(TRUE 时将会根据服务器返回 HTTP 头中的 "Location: " 重定向。（注意：这是递归的，"Location: " 发送几次就重定向几次，除非设置了 CURLOPT_MAXREDIRS，限制最大重定向次数。）)
        CURLOPT_HEADER          => 0,//Include the header in the body output. 启用时会将头文件的信息作为数据流输出。
        CURLOPT_AUTOREFERER     => true, //Automatically set Referer: header.
    ];

    /**
     * 请求数据
     * @param $url 请求链接
     * @param string $params 请求参数
     * @param string $httpMethod    http请求方式
     * @param array $headerArr header请求头数据
     * @param array $curlOption curl请求配置
     * @return bool|mixed
     */
    public function sendRequest($url, $params = '', $httpMethod = 'GET', $headerArr = [], $curlOption = [])
    {
        $this->setHttpMethod($httpMethod);
        if ($this->httpMethod === 'GET'){
            $this->handleGetCurl($url.$params, $headerArr, $curlOption);
        }elseif ($this->httpMethod === 'POST'){
            $this->handlePostCurl($url, $params, $headerArr, $curlOption);
        }else{
            //未扩展的http请求方式
            $this->notExpanded($url);
        }

        return $this->getResponse();
    }

    /**
     * 未扩展的http请求方式走这里
     * @param $url
     */
    private function notExpanded($url)
    {
        $this->setRequestUrl($url);
        $this->setResponse(false);
        $this->setResponseHeaders();

        // fetch errors
        $this->setErrorCode();
        $this->setErrorMessage("{$this->httpMethod}请求方式未扩展，请技术开发人员进行扩展");
    }

    /**
     * curl请求配置
     * @param array $curlOption curl请求配置
     * @return array
     */
    protected function mergeCurlOptions($curlOption = [])
    {
        if (empty($curlOption) || !is_array($curlOption)){
            return self::$CURL_OPTS;
        }

        foreach ($curlOption as $key=>$value){
            self::$CURL_OPTS[$key] = $value;
        }

        return self::$CURL_OPTS;
    }

    /**
     * 发送 get curl 请求
     * @param $url
     * @param array $headerArr
     * @param array $curlOption curl请求配置
     * @return mixed
     */
    private function handleGetCurl($url, $headerArr = [], $curlOption = [])
    {
        $this->setRequestUrl($url);

        $ch = curl_init();

        $opts = $this->mergeCurlOptions($curlOption);
        $opts[CURLOPT_URL] = $this->getRequestUrl();

        // https
        if (strpos($opts[CURLOPT_URL], 'https://') === 0) {
            //FALSE 禁止 cURL 验证对等证书（peer's certificate）。要验证的交换证书可以在 CURLOPT_CAINFO 选项中设置，或在 CURLOPT_CAPATH中设置证书目录。
            $opts[CURLOPT_SSL_VERIFYPEER] = false;//这个是重点。
        }

        //request headers
        if (!empty($headerArr)){
            //设置 HTTP 头字段的数组。格式： array('Content-type: text/plain', 'Content-length: 100')
            $opts[CURLOPT_HTTPHEADER] = $headerArr;
        }

        // set options
        curl_setopt_array($ch, $opts);

        //execute
        $this->setResponse(curl_exec($ch));
        $this->setResponseHeaders(curl_getinfo($ch));

        // fetch errors
        $this->setErrorCode(curl_errno($ch));
        $this->setErrorMessage(curl_error($ch));

        curl_close($ch);
        return $this->getResponse();
    }

    /**
     * 发送 post curl 请求
     * @param $url
     * @param string $postData
     * @param array $headerArr
     * @param array $curlOption curl请求配置
     * @return mixed
     */
    private function handlePostCurl($url, $postData = '', $headerArr = [], $curlOption = [])
    {
        $this->setRequestUrl($url);

        $ch = curl_init();

        $opts = $this->mergeCurlOptions($curlOption);
        $opts[CURLOPT_URL] = $this->getRequestUrl();

        // https
        if (strpos($opts[CURLOPT_URL], 'https://') === 0) {
            //FALSE 禁止 cURL 验证对等证书（peer's certificate）。要验证的交换证书可以在 CURLOPT_CAINFO 选项中设置，或在 CURLOPT_CAPATH中设置证书目录。
            $opts[CURLOPT_SSL_VERIFYPEER] = false;//这个是重点。
        }

        //post提交方式
        $opts[CURLOPT_POST] = 1;
        if (is_string($postData)){
            $opts[CURLOPT_POSTFIELDS] = $postData;
        }elseif (is_array($postData)){
            $opts[CURLOPT_POSTFIELDS] = http_build_query($postData, null, '&');
        }

        //request headers
        if (!empty($headerArr)){
            //设置 HTTP 头字段的数组。格式： array('Content-type: text/plain', 'Content-length: 100')
            $opts[CURLOPT_HTTPHEADER] = $headerArr;
        }

        // set options
        curl_setopt_array($ch, $opts);

        //execute
        $this->setResponse(curl_exec($ch));
        $this->setResponseHeaders(curl_getinfo($ch));

        // fetch errors
        $this->setErrorCode(curl_errno($ch));
        $this->setErrorMessage(curl_error($ch));

        curl_close($ch);
        return $this->getResponse();
    }

    /**
     * 设置curl请求链接
     * @param $url
     * @return $this
     */
    public function setRequestUrl($url)
    {
        $this->requestUrl = $url;

        return $this;
    }

    /**
     * 获取curl请求链接
     * @return mixed
     */
    public function getRequestUrl()
    {
        return $this->requestUrl;
    }

    /**
     * 设置curl请求方式
     * @param $httpMethod
     * @return $this
     */
    public function setHttpMethod($httpMethod)
    {
        $this->httpMethod = strtoupper($httpMethod);

        return $this;
    }

    /**
     * 获取curl请求方式
     * @return string
     */
    public function getHttpMethod()
    {
        return $this->httpMethod;
    }

    /**
     * 设置curl请求响应数据
     * @param string $response
     * @return $this
     */
    public function setResponse($response = '')
    {
        $this->response = $response;

        return $this;
    }

    /**
     * 获取curl请求响应数据
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * 设置curl请求响应header头信息
     * @param array $headers
     * @return $this
     */
    public function setResponseHeaders($headers = [])
    {
        $this->responseHeaders = $headers;
        $httpCode = 0;
        if (isset($headers['http_code'])){
            $httpCode = $headers['http_code'];
        }
        $this->setHttpStatusCode($httpCode);

        return $this;
    }

    /**
     * 获取curl请求响应header头信息
     * 请求响应头信息 包含http请求状态码，一般的，请求状态码不是200，说明请求失败，比如遇到了400错误、500服务器错误
     * 参考资料：https://developer.mozilla.org/zh-CN/docs/Web/HTTP/Status#成功响应
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * 设置curl请求最后一次的错误代码  0 表示成功
     * @param int $code
     * @return $this
     */
    public function setErrorCode($code = 0)
    {
        $this->errorCode = $code;

        return $this;
    }

    /**
     * 获取curl请求最后一次的错误代码
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * 设置当前会话最后一次错误的字符串
     * @param string $message
     * @return $this
     */
    public function setErrorMessage($message = '')
    {
        $this->errorMessage = $message;

        return $this;
    }

    /**
     * 获取当前会话最后一次错误的字符串
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * 设置http请求状态码
     * @param $statusCode
     * @return $this
     */
    public function setHttpStatusCode($statusCode)
    {
        $this->httpStatusCode = $statusCode;
        return $this;
    }

    /**
     * 获取http请求状态码
     * @return mixed
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }
}