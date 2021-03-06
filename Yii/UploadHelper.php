<?php
namespace common\helpers;

use Yii;

class UploadHelper
{
    private $fileField; //文件域名
    private $file; //文件上传对象
    private $config; //配置信息
    private $oriName; //原始文件名
    private $folder;//文件路径
    private $fileName; //新文件名
    private $fullName; //完整文件名,即从当前配置目录开始的URL
    private $filePath; //完整文件名,即从当前配置目录开始的URL
    private $savePath; //完整文件名,即从当前配置目录开始的URL
    private $fileSize; //文件大小
    private $fileType; //文件类型
    private $stateInfo; //上传状态信息,
    private $stateMap = array( //上传状态映射表，国际化用户需考虑此处数据的国际化
        "SUCCESS", //上传成功标记，在UEditor中内不可改变，否则flash判断会出错
        "文件大小超出 upload_max_filesize 限制",
        "文件大小超出 MAX_FILE_SIZE 限制",
        "文件未被完整上传",
        "没有文件被上传",
        "上传文件为空",
        "ERROR_TMP_FILE" => "临时文件错误",
        "ERROR_TMP_FILE_NOT_FOUND" => "找不到临时文件",
        "ERROR_SIZE_EXCEED" => "文件大小超出网站限制",
        "ERROR_TYPE_NOT_ALLOWED" => "文件类型不允许",
        "ERROR_CREATE_DIR" => "目录创建失败",
        "ERROR_DIR_NOT_WRITEABLE" => "目录没有写权限",
        "ERROR_FILE_MOVE" => "文件保存时出错",
        "ERROR_FILE_NOT_FOUND" => "找不到上传文件",
        "ERROR_WRITE_CONTENT" => "写入文件内容错误",
        "ERROR_UNKNOWN" => "未知错误",
        "ERROR_DEAD_LINK" => "链接不可用",
        "ERROR_HTTP_LINK" => "链接不是http链接",
        "ERROR_HTTP_CONTENTTYPE" => "链接contentType不正确",
        "INVALID_URL" => "非法 URL",
        "INVALID_IP" => "非法 IP"
    );

    /**
     * 构造函数
     * @param string $fileField 表单名称
     * @param array $config 配置项
     * @param string $type 是否解析base64编码，可省略。若开启，则$fileField代表的是base64编码的字符串表单名
     */
    public function __construct($fileField, $config, $type = "upload")
    {
        $this->fileField = $fileField;
        $this->config = $config;
        if ($type == "remote") {
            $this->saveRemote();
        } else if($type == "base64") {
            $this->upBase64();
        } else {
            $this->upFile();
        }
    }

    /**
     * 上传文件的主处理方法
     * @return mixed
     */
    private function upFile()
    {
        $file = $this->file = $_FILES[$this->fileField];
        if (!$file) {
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_NOT_FOUND");
            return;
        }
        if ($this->file['error']) {
            $this->stateInfo = $this->getStateInfo($file['error']);
            return;
        } else if (!file_exists($file['tmp_name'])) {
            $this->stateInfo = $this->getStateInfo("ERROR_TMP_FILE_NOT_FOUND");
            return;
        } else if (!is_uploaded_file($file['tmp_name'])) {
            $this->stateInfo = $this->getStateInfo("ERROR_TMPFILE");
            return;
        }

        $this->oriName = $this->getOriName($file);
        $this->fileSize = $file['size'];
        $this->fileType = $this->getFileExt();
        $this->fileName = $this->getFileName();
        $this->folder   = $this->getFolder();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->savePath = $this->getSavePath();
        $dirname = dirname($this->savePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //检查是否不允许的文件格式
        if (!$this->checkType()) {
            $this->stateInfo = $this->getStateInfo("ERROR_TYPE_NOT_ALLOWED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirname)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        if (!(move_uploaded_file($file["tmp_name"], $this->savePath) && file_exists($this->savePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_MOVE");
        } else {
            //移动文件
            if (!OssHelper::file($this->filePath,$this->savePath)) { //移动失败
                $this->stateInfo = $this->getStateInfo("ERROR_FILE_MOVE");
            } else {
                //@unlink($this->savePath);
                $this->stateInfo = $this->stateMap[0];
            }
        }
        return ;
    }

    /**
     * 处理base64编码的图片上传
     * @return mixed
     */
    private function upBase64()
    {
        $base64Data = $_POST[$this->fileField];
        $img = base64_decode($base64Data);

        $this->oriName = $this->config['oriName'];
        $this->fileSize = strlen($img);
        $this->fileType = $this->getFileExt();
        $this->fileName = $this->getFileName();
        $this->folder   = $this->getFolder();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->savePath = $this->getSavePath();
        $dirname = dirname($this->savePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirname)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件
        if (!(file_put_contents($this->savePath, $img) && file_exists($this->savePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_WRITE_CONTENT");
        } else {
            //移动文件
            if (!OssHelper::file($this->filePath,$this->savePath)) { //移动失败
                $this->stateInfo = $this->getStateInfo("ERROR_FILE_MOVE");
            } else {
                //@unlink($this->savePath);
                $this->stateInfo = $this->stateMap[0];
            }
        }
        return ;
    }

    /**
     * 拉取远程图片
     * @return mixed
     */
    private function saveRemote()
    {
        $imgUrl = htmlspecialchars($this->fileField);
        $imgUrl = str_replace("&amp;", "&", $imgUrl);

        //http开头验证
        if (strpos($imgUrl, "http") !== 0) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_LINK");
            return;
        }

        preg_match('/(^https*:\/\/[^:\/]+)/', $imgUrl, $matches);
        $host_with_protocol = count($matches) > 1 ? $matches[1] : '';

        // 判断是否是合法 url
        if (!filter_var($host_with_protocol, FILTER_VALIDATE_URL)) {
            $this->stateInfo = $this->getStateInfo("INVALID_URL");
            return;
        }

        preg_match('/^https*:\/\/(.+)/', $host_with_protocol, $matches);
        $host_without_protocol = count($matches) > 1 ? $matches[1] : '';

        // 此时提取出来的可能是 ip 也有可能是域名，先获取 ip
        $ip = gethostbyname($host_without_protocol);
        // 判断是否是私有 ip
        if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            $this->stateInfo = $this->getStateInfo("INVALID_IP");
            return;
        }

        //获取请求头并检测死链
        $heads = get_headers($imgUrl, 1);
        if (!(stristr($heads[0], "200") && stristr($heads[0], "OK"))) {
            $this->stateInfo = $this->getStateInfo("ERROR_DEAD_LINK");
            return;
        }
        //格式验证(扩展名验证和Content-Type验证)
        $fileType =  substr(strtolower(strrchr($imgUrl, '.')), 1);
        if (!in_array($fileType, $this->config['allowFiles']) || !isset($heads['Content-Type']) || !stristr($heads['Content-Type'], "image")) {
            $this->stateInfo = $this->getStateInfo("ERROR_HTTP_CONTENTTYPE");
            return;
        }

        //打开输出缓冲区并获取远程图片
        ob_start();
        $context = stream_context_create(
            array('http' => array(
                'follow_location' => false // don't follow redirects
            ))
        );
        readfile($imgUrl, false, $context);
        $img = ob_get_contents();
        ob_end_clean();
        preg_match("/[\/]([^\/]*)[\.]?[^\.\/]*$/", $imgUrl, $m);

        $this->oriName = $m ? $m[1]:"";
        $this->fileSize = strlen($img);
        $this->fileType = $this->getFileExt();
        $this->fileName = $this->getFileName();
        $this->folder   = $this->getFolder();
        $this->fullName = $this->getFullName();
        $this->filePath = $this->getFilePath();
        $this->savePath = $this->getSavePath();
        $dirname = dirname($this->savePath);

        //检查文件大小是否超出限制
        if (!$this->checkSize()) {
            $this->stateInfo = $this->getStateInfo("ERROR_SIZE_EXCEED");
            return;
        }

        //创建目录失败
        if (!file_exists($dirname) && !mkdir($dirname, 0777, true)) {
            $this->stateInfo = $this->getStateInfo("ERROR_CREATE_DIR");
            return;
        } else if (!is_writeable($dirname)) {
            $this->stateInfo = $this->getStateInfo("ERROR_DIR_NOT_WRITEABLE");
            return;
        }

        //移动文件
        if (!(file_put_contents($this->savePath, $img) && file_exists($this->savePath))) { //移动失败
            $this->stateInfo = $this->getStateInfo("ERROR_FILE_MOVE");
        } else {
            //移动文件
            if (!OssHelper::file($this->filePath,$this->savePath)) { //移动失败
                $this->stateInfo = $this->getStateInfo("ERROR_FILE_MOVE");
            } else {
                //@unlink($this->savePath);
                $this->stateInfo = $this->stateMap[0];
            }
        }
        return ;
    }

    /**
     * 上传错误检查
     * @param $errCode
     * @return string
     */
    private function getStateInfo($errCode)
    {
        return !$this->stateMap[$errCode] ? $this->stateMap["ERROR_UNKNOWN"] : $this->stateMap[$errCode];
    }

    private function getOriName($file){
        if (isset($_REQUEST["name"])) {
            return $_REQUEST["name"];
        }else{
            return $file['name'];
        }
    }

    /**
     * 获取文件扩展名
     * @return string
     */
    private function getFileExt()
    {
        $ext = substr(strtolower(strrchr($this->oriName, '.')),1);
        return $ext;
    }

    /**
     * 重命名文件
     * @return string
     */
    private function getFullName()
    {
        $folder = $this->getFolder($this->config[ "isDateMkdir" ]);
        return $folder.$this->fileName;
    }
    /**
     * 自动创建存储文件夹
     * @param bool $is_mkdir_date true 按日期创建 ，false 不按日期创建
     * @return string
     */
    private function getFolder($is_mkdir_date = true)
    {
        $pathStr = '';
        $backStr = '';

        if($is_mkdir_date){
            if ( strrchr( $pathStr , "/" ) != "/" ) {
                $backStr .= "/";
            }
            $backStr .= date( "Y" )."/";
            $backStr .= date( "m" )."/";
            $backStr .= date( "d" )."/";
        }

        $pathStr = $pathStr.$backStr;
        return $pathStr;
    }

    /**
     * 获取文件名
     * @return string
     */
    private function getFileName()
    {
        $this->fileName = time() . rand( 1 , 10000 ) ;
        return $this->fileName. '.' .$this->getFileExt();
    }

    /**
     * 获取文件完整路径
     * @return string
     */
    private function getFilePath()
    {
        $fullname = $this->fullName;
        $rootPath = $this->config['uploadFilePath'];
        if (substr($fullname, 0, 1) != '/') {
            $fullname = '/' . $fullname;
        }
        return $rootPath . $fullname;
    }

    /**
     * 绝对路径
     * @return string
     */
    private function getSavePath()
    {
        return Yii::getAlias('@storage') . '/' . $this->filePath;
    }

    /**
     * 文件类型检测
     * @return bool
     */
    private function checkType()
    {
        return in_array($this->getFileExt(), $this->config["allowFiles"]);
    }
    /**
     * 文件大小检测
     * @return bool
     */
    private function checkSize()
    {
        return $this->fileSize <= $this->config["maxSize"];
    }
    /**
     * 获取当前上传成功文件的各项信息
     * @return array
     */
    public function getFileInfo()
    {
        return array(
            "state"     => $this->stateInfo,
            "url"       => $this->fullName,
            "path_url"  => $this->filePath,
            "title"     => $this->fileName,
            "original"  => $this->oriName,
            "type"      => $this->fileType,
            "size"      => $this->fileSize,
            "local"     => $this->savePath
        );
    }
}
