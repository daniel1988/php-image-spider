<?php
error_reporting(E_ALL);
class DivIdSpider {

    var $linked_list = [];

    var $opts = [];

    var $page_deep = 0;

    var $max_page_deep = 100;

    var $host = '';

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
用法：$ php div-id-spider.php -u http://www.meizitu.com/
参数说明：
    -u 网站链接
\n
EOF;
            exit;
        }

        if ( empty( $this->opts['u'] ) ) {
            die('抓取网址不能为空!');
        }
        $this->host = parse_url($this->opts['u'])['host'];
    }

    function getHtml($url)
    {
        if (strpos($url, 'rss') > 0 OR strpos($url, 'safe') OR strpos($url, 'css')) {
            return '';
        }
        return file_get_contents($url);
    }

    function getDiv( $html )
    {
        $pattern = '/<div[\s|\S]*?id=[\'|"]picture[\'|"]>([\s|\S]*?)<\/div>/';
        preg_match_all($pattern, $html, $matches);
        return $matches[1];
    }


    function getTitle( $html )
    {
        $pattern = '/<title[\s|\S]*?>([\s|\S]*?)<\/title>/';
        preg_match($pattern, $html, $matches);
        return explode('|',$matches[1])[0];
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
            if (strpos($url, 'rss') > 0 OR strpos($url, 'safe') OR strpos($url, 'css')) {
                continue;
            }
            if ( strpos($url, $this->host) > 0 ) {
                $href_list[] = $url;
            }
        }
        return $href_list;
    }

    var $conn = null ;
    function initDB( $table )
    {

        if ( empty( $this->conn ) ) {
            $host = 'localhost';
            $user = 'root';
            $pwd  = 'root';
            $this->conn = mysql_connect($host,$user,$pwd);
        }

        if ( !$this->conn ) {
            die('connect failed');
        }
        $sql = "set names utf8";
        mysql_query( $sql, $this->conn );

        $sql = "SELECT MAX(id) AS id FROM $table ";
        $result = mysql_fetch_object(mysql_query( $sql, $this->conn ));
        $this->id = $result->id;
        return $this->conn;
    }

    function saveToDB($post_title, $post_content)
    {
        $table = 'wordpress.wp_posts';
        $conn = $this->initDB( $table );
        $now = date('Y-m-d H:i:s');

        $this->id += 1;
        $guid = "http://local.wordpress.com/?p={$this->id}";

        $sql = "INSERT INTO $table
                SET ID=$this->id,post_author='1', post_date='{$now}', post_date_gmt='{$now}',
                post_content='{$post_content}', post_title='{$post_title}', post_excerpt='',post_status='publish',
                comment_status='open',ping_status='open', post_password='', post_name='{$post_title}',
                to_ping='', pinged='', post_modified='{$now}',
                post_modified_gmt='{$now}', post_content_filtered='', post_parent='0',
                guid='{$guid}', menu_order='0', post_type='post',
                post_mime_type='', comment_count='0'
        ";
        $res = mysql_query($sql, $conn);
        echo sprintf("%s, id:%d, res:%s\n", $post_title, $this->id, $res);
    }


    function run()
    {
        $url = $this->opts['u'];
        $this->start( $url );
    }

    function start( $url )
    {
        echo 'URL:' . $url, "\n";

        $html           = $this->getHtml( $url );
        $html           = iconv("gb2312", "utf-8//IGNORE",$html);
        $div_content_list    = $this->getDiv( $html );
        $title          = $this->getTitle( $html );

        $div_content = '';
        foreach( $div_content_list as $content ) {
            $div_content .= $content;
        }

        $this->saveToDB($title, $div_content);

        $href_list      = $this->getHrefs( $html );
        foreach( $href_list as $url ) {
            if ( in_array( $url, $this->linked_list ) ) {
                continue;
            }
            $this->linked_list[] = $url;
            $this->start($url);
        }
        $this->page_deep += 1;
        if ( $this->page_deep > $this->max_page_deep ) {
            echo sprintf("page count:%d, link count:%d", $this->page_deep, count($this->linked_list));
            exit;
        }
    }
}

//http://www.meizitu.com/

$imgspider = new DivIdSpider();
$imgspider->run();
