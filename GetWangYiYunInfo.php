<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/30
 * Time: 10:50
 */
// 引入 QueryList 插件,获取html页面中想要的组件内容
require 'phpQuery.php';
require 'QueryList.php';

// 设置时域
ini_set('date.timezone','Asia/Shanghai');

class GetWangYiYunInfo
{
    // 云音乐热歌榜 url
    private $url = 'http://music.163.com/discover/toplist?id=3778678';
    // 获取评论的音乐个数
    private $music_num = '3';

    function __construct()
    {
        $this->getHtmlInfo($this->url);
    }

    /**
     * 获取云音乐热歌榜的榜单歌曲名以及链接
     * @param $url
     */
    private function getHtmlInfo($url)
    {
        // 设置请求头
        $headers = array(
            'Host:music.163.com',
            'Refere:http://music.163.com/',
            // 模拟浏览器设置 User-Agent ，否则取到的数据不完整
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36'
        );
        $htmlInfo = $this->httpGet($url,$headers);
        $rules = array(
            'a' => array('.f-hide>li>a','href'),
            'text' => array('.f-hide>li>a','text')
        );
        $data = \QL\QueryList::Query($htmlInfo,$rules);
        $this->makeData($data);
    }

    /**
     * 修改获得的object类型以及拼接歌曲完整链接
     * @param $data
     */
    private function makeData($data)
    {
        $tmp = '';
        foreach ($data->data as $k=>$v){
            $tmp[$k]['text'] = $v['text'];
            $songId = explode('/song?id=',$v['a']);
            $tmp[$k]['songId'] = $songId[1];
        }
        $this->getComment($tmp);
    }

    /**
     * 获取评论
     * @param $data
     */
    private function getComment($data)
    {
        // 设置请求头
        $headers = array(
            'Accept:*/*',
            'Accept-Language:zh-CN,zh;q=0.9',
            'Connection:keep-alive',
            'Content-Type:application/x-www-form-urlencoded',
            'Host:music.163.com',
            'Origin:https://music.163.com',
            // 模拟浏览器设置 User-Agent ，否则取到的数据不完整
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36'
        );
        for ($i=0; $i<$this->music_num; $i++){
            // 拼接歌曲的url
            $url = 'https://music.163.com/weapi/v1/resource/comments/R_SO_4_'.$data[$i]['songId'].'?csrf_token=';
            // 拼接加密 params 用到的第一个参数
            $first_param = '{"rid":"R_SO_4_'.$data[$i]['songId'].'","offset":"0","total":"true","limit":"20","csrf_token":""}';
            $params = array('params' => $this->aesGetParams($first_param), 'encSecKey' => $this->getEncSecKey());
            $htmlInfo = $this->httpPost($url,$headers,http_build_query($params));
            // 记录评论
            $this->saveComment(json_decode($htmlInfo,true),$data[$i]['text']);
            // 没有设置代理IP,间隔2秒执行
            sleep(2);
        }
    }

    /**
     *  加密获取params
     * @param $param            // 待加密的明文信息数据
     * @param string $method    // 加密算法
     * @param string $key       // key
     * @param string $options   // options 是以下标记的按位或： OPENSSL_RAW_DATA 、 OPENSSL_ZERO_PADDING
     * @param string $iv        // 非 NULL 的初始化向量
     * @return string
     *
     * $key 在加密 params 中第一次用的是固定的第四个参数 0CoJUm6Qyw8W8jud,在第二次加密中用的是 js 中随机生成的16位字符串
     */
    private function aesGetParams($param,$method = 'AES-128-CBC',$key = 'JK1M5sQAEcAZ46af',$options = '0',$iv = '0102030405060708')
    {
        $firstEncrypt = openssl_encrypt($param,$method,'0CoJUm6Qyw8W8jud',$options,$iv);
        $secondEncrypt = openssl_encrypt($firstEncrypt,$method,$key,$options,$iv);
        return $secondEncrypt;
    }

    /**
     *  encSecKey 在 js 中有 res 方法加密。
     *  其中三个参数分别为上面随机生成的16为字符串,第二个参数 $second_param,第三个参数 $third_param 都是固定写死的，这边使用抄下来的一个固定 encSecKey
     * @return bool
     */
    private function getEncSecKey()
    {
        $getEncSecKey = '2a98b8ea60e8e0dd0369632b14574cf8d4b7a606349669b2609509978e1b5f96ed8fbe53a90c0bb74497cd2eb965508bff5bfa065394a52ea362539444f18f423f46aded5ed9a1788d110875fb976386aa4f5d784321433549434bccea5f08d1888995bdd2eb015b2236f5af15099e3afbb05aa817c92bfe3214671e818ea16b';

        return $getEncSecKey;
    }

    /**
     * curl 发送 get 请求
     * @param $url
     * @param $header
     * @return mixed
     */
    private function httpGet($url,$header)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);                // true获取响应头的信息
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);        // 对认证证书来源的检查
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);        // 使用自动跳转
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);           // 自动设置Referer
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);        // 设置等待时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);              // 设置cURL允许执行的最长秒数
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    /**
     * curl 发送 post 请求
     * @param $url
     * @param $header
     * @param $data
     * @return mixed
     */
    private function httpPost($url,$header,$data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST,true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);                // 0不带头文件，1带头文件（返回值中带有头文件）
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS , $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);        // 对认证证书来源的检查
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);        // 使用自动跳转
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);           // 自动设置Referer
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);        //设置等待时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);              //设置cURL允许执行的最长秒数
        $content = curl_exec($ch);
        curl_close($ch);
        return $content;
    }

    /**
     * 写入文件
     * @param $songName
     * @param $data
     */
    private function saveComment($data,$songName)
    {
        // 读写方式打开文件，将文件指针指向文件末尾。如果文件不存在，则创建。
        $myFile = fopen(iconv('UTF-8', 'GB18030', "网易云音乐热门评论_".date("Y-m-d",time()).".txt"), "a+");

        $hotCommentsLength = count($data['hotComments']);
        for ($i=0;$i<$hotCommentsLength;$i++){
            $text  = "歌名：".$songName.PHP_EOL;
            $text .= "评论：".$data['hotComments'][$i]['content'].PHP_EOL;
            $text .= "时间：".date("Y-m-d H:i:s",$data['hotComments'][$i]['time'] / 1000).PHP_EOL;
            $text .= "用户名：".$data['hotComments'][$i]['user']['nickname'].PHP_EOL;
            $text .= "点赞数：".$data['hotComments'][$i]['likedCount'].PHP_EOL;
            $text .= "******************************************************".PHP_EOL;
            // 写入文件，第一个参数要写入的文件名，第二个参数是被写的字符串
            fwrite($myFile,$text);
        }

        // 关闭打开的文件
        fclose($myFile);
    }
}