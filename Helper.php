<?php

namespace App\Common;

class Helper
{
    /**
     * http请求
     * @param $url string 请求地址
     * @param $params array 请求参数
     * @param $method string 请求类型
     * @param $multi boolean 是否传输文件
     * @return string json格式
     */
    public static function http($url, $params = [], $method = 'GET', $multi = false)
    {
        $header = array(
            "content-type: application/x-www-form-urlencoded;charset=UTF-8"
        );
        $opts = array(
            CURLOPT_TIMEOUT => 6000,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0',
            CURLOPT_FOLLOWLOCATION => 1,
            CURLOPT_AUTOREFERER => 1,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        );
        /* 根据请求类型设置特定参数 */
        switch (strtoupper($method)) {
            case 'GET':
                $opts[CURLOPT_URL] = $url . '?' . http_build_query($params);
                break;
            case 'POST':
                //判断是否传输文件
                $params = $multi ? $params : http_build_query($params);
                $opts[CURLOPT_URL] = $url;
                $opts[CURLOPT_POST] = 1;
                $opts[CURLOPT_POSTFIELDS] = $params;
                break;
            default:
                throw new Exception('不支持的请求方式！');
        }
        /* 初始化并执行curl请求 */
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) throw new Exception('请求发生错误：' . $error);
        return json_decode($data, true);
    }

    /**
     * 过滤掉emoji表情
     * @param $str
     * @return string|string[]|null
     */
    public static function filterEmoji($str)
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);
        return $str;
    }

    /**
     * PHP获取字符串中英文混合长度
     * @param $str string 字符串
     * @param $charset string 编码
     * @return integer *返回长度，1中文=1位，2英文=1位
     */
    public static function getStrLength($str, $charset = 'utf-8')
    {
        $str = self::filterEmoji($str);
        if ($charset == 'UTF-8') $str = iconv('UTF-8', 'GBK', $str);
        $num = strlen($str);
        $cnNum = 0;
        for ($i = 0; $i < $num; $i++) {
            if (ord(substr($str, $i + 1, 1)) > 127) {
                $cnNum++;
                $i++;
            }
        }
        $enNum = $num - ($cnNum * 2);
        $number = ($enNum / 2) + $cnNum;
        return ceil($number);
    }

    /**
     *  根据身份证号码获取性别
     * @param string $id_card 身份证号码
     * @return int $sex 性别 1男 2女 0未知
     */
    public static function get_sex($id_card)
    {
        if (empty($id_card)) return null;
        $sex = (int)substr($id_card, 16, 1);
        return $sex;
    }

    /**
     *  根据身份证号码获取生日
     * @param string $id_card 身份证号码
     * @return string $birthday
     */
    function get_birthday($id_card)
    {
        if (empty($id_card)) return null;
        $birthday = substr($id_card, 6, 8);
        $year = (int)substr($birthday, 0, 4);
        $month = (int)substr($birthday, 4, 2);
        $day = (int)substr($birthday, 6, 2);
        return $year . "-" . $month . "-" . $day;
    }

    /**
     *  根据身份证号码计算年龄
     * @param string $id_card 身份证号码
     * @return int $age
     */
    function get_age($id_card)
    {
        if (empty($id_card)) return null;
        //获得出生年月日的时间戳
        $date = strtotime(substr($id_card, 6, 8));
        //获得今日的时间戳
        $today = strtotime('today');
        //得到两个日期相差的大体年数
        $diff = floor(($today - $date) / 86400 / 365);
        //加上这个年数后得到那日的时间戳后与今日的时间戳相比
        $age = strtotime(substr($id_card, 6, 8) . ' +' . $diff . 'years') > $today ? ($diff + 1) : $diff;
        return $age;
    }


}