<?php


class CmstopClient
{
    protected static $defaultOptions = array(
        'userAgent' => 'Open Client 1.0',     // 请求时的 user agent
        'connectionTimeout' => 10,            // 发起连接前等待超时时间
        'timeout' => 30,                      // 请求执行超时时间
        'sslVerifypeer' => false              // 是否从服务端进行验证
    );

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $api;

    /**
     * @var string
     */
    protected $appid;

    /**
     * @var string
     */
    protected $secret;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array HTTP head
     */
    protected $httpHead;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * @var string
     */
    protected $error;

    /**
     * @var array 响应信息
     */
    protected $httpInfo = array();

    /**
     * 初始化
     *
     * @param $api
     * @param $appid
     * @param $secret
     * @param array $options
     */
    public function __construct($api, $appid, $secret, array $options = array())
    {
        unset($this->data);
        $this->api = $api;
        $this->appid = $appid;
        $this->secret = $secret;
        $this->options = array_merge(self::$defaultOptions, $options);
    }

    /**
     * 发起GET请求
     * @access public
     *
     * @param $api
     * @param array $params
     * @return mixed
     */
    public function get($api, array $params = array())
    {
        $url = $this->url($api, $params);
//		print_r($url);
        $response = $this->request($url, $params, 'GET', array());
        $this->parseResponse($response);
        return $this->data;
    }

    /**
     * 外部调用POST请求
     * @access public
     *
     * @param $api
     * @param array $params
     * @param array $multipart
     * @return mixed
     */
    public function post($api, array $params = array(), array $multipart = array())
    {
//        $url = $this->url($api, $params);
//        $response = $this->request($url, $params, 'POST', $multipart);
//        $this->parseResponse($response);
//        return $this->data;
        
        $url = rtrim($this->api, '/') . '/' . ltrim($api, '/');
        $time = time();
        $params['time'] = $time;
        $params['appid'] = $this->appid;
        ksort($params);
        $sign = http_build_query($params);
        $params['sign'] = md5(md5($sign) . $this->appid . $this->secret . $time);
        $api = $url.'?sign='.$params['sign'];
        
        $response = $this->request($api, $params, 'POST', $multipart);
        $this->parseResponse($response);
        return $this->data;
    }

    /**
     * 外部调用生成签名后的URL
     * @access public
     *
     * @param string $api
     * @param array $params
     * @return string
     */
    public function url ($api = '', Array $params = array())
    {
        $url = rtrim($this->api, '/') . '/' . ltrim($api, '/');
        return $this->sign($url, $params);
    }

    /**
     * 错误提示
     * @access public
     *
     * @return string
     */
    public function error()
    {
        return $this->error;
    }

    /**
     * 生成签名URL
     * @access public
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    protected function sign($url = '', array $params)
    {
        $time = time();
        $params['time'] = $time;
        $params['appid'] = $this->appid;
        ksort($params);
        $sign = http_build_query($params);
        $params['sign'] = md5(md5($sign) . $this->appid . $this->secret . $time);
//        var_dump($params['sign']);
        return $url . '?' . http_build_query($params);
    }

    /**
     * 处理返回结果
     * @access protected
     *
     * @param $response
     */
    protected function parseResponse($response)
    {
//        print_r($response);
        try {
            $result = json_decode($response, true);
        } catch (\Exception $e) {}

        if (json_last_error() != JSON_ERROR_NONE) {
            $this->error = $response;
            $this->data = false;
            return;
        }

        if (empty($result) || !is_array($result)) {
            $this->error = $response;
            $this->data = false;
            return;
        }

        if (!$result['state']) {
            $this->error = !empty($result['error']) ? $result['error'] : 'Unknown error';
            $this->data = false;
            return;
        }

        $this->data = empty($result['data']) ? array() : $result['data'];
    }

    /**
     * 发起请求
     * @access protected
     *
     * @param $url
     * @param array $params
     * @param string $method
     * @param array $multipart
     * @param array $extra_headers
     * @return mixed
     */
    protected function request($url, $params = array(), $method = 'GET', array $multipart = array(), array $extra_headers = array())
    {
        $url = preg_replace('/\\0/', "", $url);

        $this->error = null;
        $this->data = null;

        $method = strtoupper($method);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, $this->options['userAgent']);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->options['connectionTimeout']);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->options['sslVerifypeer']);

        $headers = (array)$extra_headers;

        /**
         * 解决响应结果中包含额外的 100 continue 的问题
         *
         * @see http://www.iandennismiller.com/posts/curl-http1-1-100-continue-and-multipartform-data-post.html
         * @see http://stackoverflow.com/questions/11359276/php-curl-exec-returns-both-http-1-1-100-continue-and-http-1-1-200-ok-separated-b
         */
        $headers[] = 'Expect: ';

        switch ($method) {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                if (!empty($params)) {
                    if ($multipart) {

                        foreach (array('file', 'filename', 'type') as $key) {
                            if (!array_key_exists($key, $multipart)) {
                                return "Missing parameter {$key}";
                            }
                        }

                        $file = '@' . $multipart['file'];
                        $file .= ';filename=' . $multipart['filename'];
                        $file .= ';type=' . $multipart['type'];

                        $params['file'] = $file;

                        if (PHP_VERSION >= '5.6.0') {
                            curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
                        }

                        @curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                    } else {
                        curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($params) ? http_build_query($params) : $params);
                    }
                }
                break;
            case 'DELETE':
            case 'GET':
                if ($method == 'DELETE') {
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                }
//				print_r($params);
                if (!empty($params)) {
                    $url = $url . (strpos($url, '?') !== false ? '&' : '?')
                        . (is_array($params) ? http_build_query($params) : $params);
                }
                break;
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url);

        $response = curl_exec($curl);
        $this->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->httpInfo = curl_getinfo($curl) ?: array();

        curl_close($curl);

        return $response;
    }
}