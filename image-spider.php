<?php
error_reporting(E_ALL);
class ImageSpider {

    var $saved_img_list = [];

    var $linked_list = [];

    var $opts = [];

    var $page_deep = 0;

    var $max_page_deep = 100;

    function __construct()
    {
        $this->_init();
    }

    function _init()
    {
        //可执行命令
        $shortopts = "u:d:";
        $longopts = [
            'help',
        ];
        $this->opts = getopt($shortopts, $longopts);
        if ( empty( $this->opts ) || isset( $this->opts['help'] )) {
            echo <<<EOF
用法：$ php image-spider.php -u http://www.meizitu.com/ -d /data/imgs
参数说明：
    -u 网站链接
    -d 保存图片路径
\n
EOF;
            exit;
        }
        if ( empty( $this->opts['d'] ) ) {
            die('保存图片路径不能为空!');
        }

        if ( empty( $this->opts['u'] ) ) {
            die('抓取网址不能为空!');
        }
    }

    function getHtml($url)
    {
        if (strpos($url, 'rss') > 0 OR strpos($url, 'safe') OR strpos($url, 'css')) {
            return '';
        }
        return file_get_contents($url);
    }

    function getImages( $html )
    {
        $pattern = '/src="(.+?[\.jpg|\.png|\.gif])"/';
        preg_match_all($pattern, $html, $matches);
        return $matches[1];
    }

    function getHrefs( $html )
    {
        $pattern = '/href="(http:\/\/.*?)"/';
        preg_match_all($pattern, $html, $matches);

        $href_list = [];
        foreach( $matches[1] as $url ) {
            if ( strpos('css', $url) > 0 ) {
                continue;
            }
            if ( strpos($url,'www.meizitu.com') > 0 ) {
                $href_list[] = $url;
            }
        }
        return $href_list;
    }

    function saveImg($imgurl, $imgdir)
    {
        if ( !is_dir( $imgdir ) ) {
            mkdir($imgdir, 0777, true) ;
        }
        echo $imgurl, "\n";
        $pathinfo = pathinfo($imgurl);

        $imgfile = $imgdir . '/' . date('YmdHis') . $pathinfo['extension'];
        return file_put_contents($imgfile, file_get_contents($imgurl));
    }

    function run()
    {
        $url = $this->opts['u'];
        $this->start( $url );
    }

    function start( $url )
    {
        echo 'URL:' . $url, "\n";
        $html = $this->getHtml( $url );
        $img_list = $this->getImages( $html );

        $imgdir = $this->opts['d'];
        foreach( $img_list as $imgurl ) {
            if (in_array($imgurl, $this->saved_img_list)){
                continue;
            }
            $this->saved_img_list[] = $imgurl;
            $this->saveImg($imgurl, $imgdir);
        }
        $href_list = $this->getHrefs( $html );
        foreach( $href_list as $url ) {
            if ( strpos($url, 'css') > 0 ) {
                continue ;
            }
            if ( in_array( $url, $this->linked_list ) ) {
                continue;
            }
            $this->linked_list[] = $url;
            $this->start($url);
        }
        $this->page_deep += 1;
        if ( $this->page_deep > $this->max_page_deep ) {
            echo sprintf("img count:%d, link count:%d",count($this->saved_img_list), count($this->linked_list));
            exit;
        }
    }
}

//http://www.meizitu.com/

$imgspider = new ImageSpider();
$imgspider->run();
