<?php

namespace App\Common;
use Illuminate\Support\Facades\Log;

/**
 * 通用的树型类，可以生成任何树型结构
 */
class Tree
{
    /**
     * 生成树型结构所需要的2维数组
     * @var array
     */
    public $arr = [];//数组集合
    public $array = [];//数组集合

    /**
     * 生成树型结构所需修饰符号，可以换成图片
     * @var array
     */
    public $icon = ['│', '├', '└'];
    public $nbsp = "&nbsp;";
    
    /**
     * @access private
     */
    public $ret = '';

    /**
     * 元素是否在数组中
     * @param array
     * @param string
     * @return string
     */
    private function have($list, $item)
    {
        return (strpos(',,' . $list . ',', ',' . $item . ','));
    }

    /**
     * 构造函数，初始化类
     * @param array 2维数组，例如：
     * array(
     *      1 => array('id'=>'1','pid'=>0,'name'=>'一级栏目一'),
     *      2 => array('id'=>'2','pid'=>0,'name'=>'一级栏目二'),
     *      3 => array('id'=>'3','pid'=>1,'name'=>'二级栏目一'),
     *      4 => array('id'=>'4','pid'=>1,'name'=>'二级栏目二'),
     *      5 => array('id'=>'5','pid'=>2,'name'=>'二级栏目三'),
     *      6 => array('id'=>'6','pid'=>3,'name'=>'三级栏目一'),
     *      7 => array('id'=>'7','pid'=>3,'name'=>'三级栏目二')
     *      )
     * @return array
     */
    public function init($arr = [])
    {
//        foreach($arr as $key => $value){
//            $this->arr[$value['id']] = $value;
//        }
        $this->arr = $arr;
        $this->ret = '';
        return is_array($arr);
    }

    /**
     * 得到父级数组
     * @param int
     * @return array
     */
    public function getParent($myId)
    {
        $newArr = [];
        $myIdArr = $this->getCurrent($myId);
        $pid = $myIdArr['pid'];
        if ($pid == 0) {
            return false;
        }
        foreach ($this->arr as $key => $value) {
            if ($value['pid'] == $pid)
                $newArr[$key] = $value;
        }
        return $newArr;
    }

    /**
     * 得到子级数组
     * @param int
     * @return array
     */
    public function getChild($myId)
    {
        $newArr = [];
        if (is_array($this->arr)) {
            foreach ($this->arr as $key => $value) {
                if ($value['pid'] == $myId) {
                    $newArr[$key] = $value;
                }
            }
        }
        return empty($newArr) == false ? $newArr : false;
    }

    /**
     * 得到当前数组
     * @param int
     * @return array
     */
    public function getCurrent($myId)
    {
        $arr_id = array_column($this->arr, 'id');
        if (!in_array($myId, $arr_id)){
            return false;
        }
        foreach($this->arr as $value)
        {
            if ($myId == $value['id']) {
                return $value;
            }
        }
    }

    /**
     * 得到当前层级数组
     * @param int
     * @return array
     */
    public function getPosition($myId)
    {
        $newArr = [];
        $myIdArr = $this->getCurrent($myId);
        $this->array[] = $myIdArr;
        $pid = $myIdArr['pid'];
        if ($pid != 0) {
            $this->getPosition($pid);
        }
        if (is_array($this->array)) {
            krsort($this->array);
            foreach ($this->array as $key => $value) {
                $newArr[$key] = $value;
            }
        }
        return $newArr;
    }

    /**
     * 生成树型结构数组
     * @param int ,myID，表示获得这个ID下的所有子级
     * @param int $maxLevel 最大获取层级,默认不限制
     * @param int $level 当前层级,只在递归调用时使用,真实使用时不传入此参数
     * @return array
     */
    public function getTreeArray($myId = 0, $maxLevel = 0, $level = 1)
    {
        $returnArray = [];
        $children = $this->getChild($myId);
        if (is_array($children)) {
            foreach ($children as $key => $value) {
                $value['_level'] = $level;
                $returnArray[$key] = $value;
                if ($maxLevel === 0 || ($maxLevel !== 0 && $maxLevel > $level)) {
                    $mLevel = $level + 1;
                    $returnArray[$key]["children"] = $this->getTreeArray($value['id'], $maxLevel, $mLevel);
                }
            }
        }
        return $returnArray;
    }

    /**
     * 得到树型结构
     * @param int ，ID表示获得这个ID下的所有子级
     * @param string ，生成树型结构的基本代码，例如："<option value=\$id \$selected>\$spacer\$name</option>"
     * @param int ，被选中的ID，比如在做树型下拉框的时候需要用到
     * @param string ，图标前文字前缀
     * @param string ，select分组 例如："<optgroup label=\$name>"
     * @return string
     */
    public function getTree($myId, $str = "", $sid = 0, $adds = '', $str_group = "")
    {
        $number = 1;
        $child = $this->getChild($myId);
        if (is_array($child)) {
            $total = count($child);
            foreach ($child as $key => $value) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                } else {
                    $j .= $this->icon[1];
                    $k = $adds ? $this->icon[0] : '';
                }
                $spacer   = $adds ? $adds . $j : '';
                $selected = $value['id'] == $sid ? 'selected' : '';
                $id       = $value['id'];
                $nstr     = '';
                @extract($value);
                $parentId = $value['pid'];
                $parentId == 0 && $str_group ? eval("\$nstr = \"$str_group\";") : eval("\$nstr = \"$str\";");
                $this->ret .= $nstr;
                if ($number == $total && $parentId != 0) {
                    $this->ret .="</optgroup>";
                }
                $nbsp      = $this->nbsp;
                $this->getTree($id, $str, $sid, $adds . $k . $nbsp, $str_group);
                $number++;
            }
        }
        return $this->ret;
    }

    /**
     * @param int ，ID表示获得这个ID下的所有子级
     * @param string ，生成树型结构的基本代码，例如："<option value=\$id \$selected>\$spacer\$name</option>"
     * @param int ，被选中的ID，多个用逗号分隔
     * @param string ，图标前文字前缀
     * @return string
     */
    public function getTreeMulti($myId, $str, $sid = 0, $adds = '')
    {
        $number = 1;
        $child  = $this->getChild($myId);
        if (is_array($child)) {
            $total = count($child);
            foreach ($child as $key => $value) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                } else {
                    $j .= $this->icon[1];
                    $k = $adds ? $this->icon[0] : '';
                }
                $id = $value['id'];
                $spacer = $adds ? $adds . $j : '';
                $selected = $this->have($sid, $id) ? 'selected' : '';
                @extract($value);
                eval("\$nstr = \"$str\";");
                $this->ret .= $nstr;
                $this->getTreeMulti($id, $str, $sid, $adds . $k . '&nbsp;');
                $number++;
            }
        }
        return $this->ret;
    }

    /**
     * @param integer $myId 要查询的ID
     * @param string $str 第一种HTML代码方式
     * @param string $str2 第二种HTML代码方式
     * @param integer $sid 默认选中
     * @param string $adds 前缀
     * @return string
     */
    public function getTreeCategory($myId, $str, $str2, $sid = 0, $adds = '')
    {
        $number = 1;
        $child  = $this->getChild($myId);
        if (is_array($child)) {
            $total = count($child);
            foreach ($child as $id => $a) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                } else {
                    $j .= $this->icon[1];
                    $k = $adds ? $this->icon[0] : '';
                }
                $spacer = $adds ? $adds . $j : '';
                $selected = $this->have($sid, $id) ? 'selected' : '';
                @extract($a);
                if (empty($html_disabled)) {
                    eval("\$nstr = \"$str\";");
                } else {
                    eval("\$nstr = \"$str2\";");
                }
                $this->ret .= $nstr;
                $this->getTreeCategory($id, $str, $str2, $sid, $adds . $k . '&nbsp;');
                $number++;
            }
        }
        return $this->ret;
    }
}

