<?php
/**
 * 页面中文字爬虫
 * User: Dubx
 * Date: 2015/4/2
 * Time: 12:51
 */
require('config.php');

$start=microtime();
$GLOBALS['hashsame']=true;
class crawler{

    public $filter='';
    public $list=array();
    public $offset=0;
    public $singlewirte=false;
    public function __construct(){
        set_time_limit(0);
        error_reporting(0);
        if(!file_exists('template')){
            exit('
            The template file is not exists!
            ');
        }
    }
    public function run($url,$filter){
        $this->filter=$filter;
        $this->newscan($url);
    }
    /*
     * 爬虫引擎，内存写入数据，优化执行效率，修复站点检索不完整BUG
     * @author dubx
     * */
    public function newscan($url){

        $urls=$this->crawler($url);
        if(!empty($urls)){
            foreach($urls as $u){
                if(!in_array($u,$this->list,true)){
                    $this->list[]=$u;
                }
            }
        }

        $this->offset++;

        if(isset($this->list[$this->offset-1])){
            $this->singlewirte && file_put_contents('url.txt',$this->list[$this->offset-1]."\r\n",FILE_APPEND);
            self::newscan($this->list[$this->offset-1]);
        }
        else{
            $record=implode("\r\n",$this->list);
            file_put_contents('url.txt',$record);
            self::getall();
        }

    }

    public function crawler($url) {
        $content = $this->_getUrlContent($url);
        if ($content) {
            $filterurl=$this->_filterUrl($content);
            $url_list = $this->_reviseUrl($url, $filterurl);
            if ($url_list) {
                return $url_list;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function _reviseUrl($base_url, $url_list) {
        $url_info = parse_url($base_url);
        $base_url = $url_info["scheme"] . '://';
        if (isset($url_info["user"]) && isset($url_info["pass"])) {
            $base_url .= $url_info["user"] . ":" . $url_info["pass"] . "@";
        }
        $base_url .= $url_info["host"];
        if (isset($url_info["port"])) {
            $base_url .= ":" . $url_info["port"];
        }
        $base_url .= isset($url_info["path"])?$url_info["path"]:'';

        if (is_array($url_list)) {
            foreach ($url_list as $url_item) {
                //URL筛选
                if(preg_match($this->filter, $url_item)){
                    if (preg_match('/^http/', $url_item)) {
                        $result[] = $url_item;
                    }
                    elseif(preg_match('/^javascript/', $url_item)){
                    }
                    elseif(preg_match('/^\/\//', $url_item)){
                        $result[] = 'http:'.$url_item;
                    }
                    else {
                        $real_url = $base_url . '/' . $url_item;
                        $result[] = $real_url;
                    }
                }
            }
            return !empty($result)?$result:[];
        } else {
            return false;
        }
    }

    public function _filterUrl($web_content) {
        $reg_tag_a = '/<[a|A].*?href=[\'\"]{0,1}([^>\'\"\ ]*).*?>/';
        $result = preg_match_all($reg_tag_a, $web_content, $match_result);
        if ($result) {
            return $match_result[1];
        }
    }
    public function _getUrlContent($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $content = curl_exec($ch);
        curl_close($ch);
        return $content;
    }
    //手动填入链接获取
    public function getman($url){
        $input=is_array($url)?$url:array($url);
        $res=$this->getch($input);
        $anser=implode('',$res);
        header("Content-type: text/html; charset=utf-8");
        echo $anser;
    }
    public function getall(){
        $links=trim(file_get_contents('url.txt'));
        $arr=explode("\r\n",$links);
        $res=$this->getch($arr);
        $anser=implode('',$res);

        $newhash=md5($anser);
        $oldhash=file_exists('hash')?file_get_contents('hash'):'';
        if($oldhash!=$newhash){
            $GLOBALS['hashsame']=false;
            file_put_contents('hash',$newhash);
        }
        $template=file_get_contents('template');
        $newhtml=str_replace("___content___",$anser,$template);
        file_put_contents('font-spider.html',$newhtml);
    }

    public function getch($urls){
        $res=array();
        foreach($urls as $key){
            $content=$this->_getUrlContent($key);
            preg_match_all("/[\x{4e00}-\x{9fa5}]/u", $content, $match);
            if(!empty($match[0])){
                foreach($match[0] as $k){
                    if(!in_array($k,$res)){
                        $res[]=$k;
                    }
                }
            }
        }
        return $res;
    }
}


/**
 * @param1 起始链接，符合条件正则
 *
 * @param2 符合条件正则表达式
 *
 * @param3 检索到内容更新 执行返回shell
 *
 * @return 检索到的URL 保存到执行文件下url.txt
 *
 * @return 检索出的中文字 依据模板生成font-spider.html 文件
 *
 *
 */
if(!isset($argv[1])){
    exit('
    Please set the url that what you want to scan !

    ');
}

$preg=isset($argv[2])?$argv[2]:'';

$crawler= new crawler();

$crawler->run($argv[1],$preg);

$end=microtime();


echo '
    执行完成，所用时间：'.abs(($end-$start)*1000).' 毫秒
    所得链接已保存到 url.txt 文件
    所得HTML文件已保存到 font-spider.html 文件

';

if(isset($argv[3])){

    if(!$GLOBALS['hashsame']){
        system($argv[3]);
    }
    else{
        echo '
    消息:被扫描页面文字无更新
    ';
    }
}

