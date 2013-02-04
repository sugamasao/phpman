<?php

$web = '/web/index.php';

if (in_array('phar', stream_get_wrappers()) && class_exists('Phar', 0)) {
Phar::interceptFileFuncs();
set_include_path('phar://' . __FILE__ . PATH_SEPARATOR . get_include_path());
Phar::webPhar(null, $web);
include 'phar://' . __FILE__ . '/' . Extract_Phar::START;
return;
}

if (@(isset($_SERVER['REQUEST_URI']) && isset($_SERVER['REQUEST_METHOD']) && ($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'POST'))) {
Extract_Phar::go(true);
$mimes = array(
'phps' => 2,
'c' => 'text/plain',
'cc' => 'text/plain',
'cpp' => 'text/plain',
'c++' => 'text/plain',
'dtd' => 'text/plain',
'h' => 'text/plain',
'log' => 'text/plain',
'rng' => 'text/plain',
'txt' => 'text/plain',
'xsd' => 'text/plain',
'php' => 1,
'inc' => 1,
'avi' => 'video/avi',
'bmp' => 'image/bmp',
'css' => 'text/css',
'gif' => 'image/gif',
'htm' => 'text/html',
'html' => 'text/html',
'htmls' => 'text/html',
'ico' => 'image/x-ico',
'jpe' => 'image/jpeg',
'jpg' => 'image/jpeg',
'jpeg' => 'image/jpeg',
'js' => 'application/x-javascript',
'midi' => 'audio/midi',
'mid' => 'audio/midi',
'mod' => 'audio/mod',
'mov' => 'movie/quicktime',
'mp3' => 'audio/mp3',
'mpg' => 'video/mpeg',
'mpeg' => 'video/mpeg',
'pdf' => 'application/pdf',
'png' => 'image/png',
'swf' => 'application/shockwave-flash',
'tif' => 'image/tiff',
'tiff' => 'image/tiff',
'wav' => 'audio/wav',
'xbm' => 'image/xbm',
'xml' => 'text/xml',
);

header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$basename = basename(__FILE__);
if (!strpos($_SERVER['REQUEST_URI'], $basename)) {
chdir(Extract_Phar::$temp);
include $web;
return;
}
$pt = substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], $basename) + strlen($basename));
if (!$pt || $pt == '/') {
$pt = $web;
header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . $_SERVER['REQUEST_URI'] . '/' . $pt);
exit;
}
$a = realpath(Extract_Phar::$temp . DIRECTORY_SEPARATOR . $pt);
if (!$a || strlen(dirname($a)) < strlen(Extract_Phar::$temp)) {
header('HTTP/1.0 404 Not Found');
echo "<html>\n <head>\n  <title>File Not Found<title>\n </head>\n <body>\n  <h1>404 - File ", $pt, " Not Found</h1>\n </body>\n</html>";
exit;
}
$b = pathinfo($a);
if (!isset($b['extension'])) {
header('Content-Type: text/plain');
header('Content-Length: ' . filesize($a));
readfile($a);
exit;
}
if (isset($mimes[$b['extension']])) {
if ($mimes[$b['extension']] === 1) {
include $a;
exit;
}
if ($mimes[$b['extension']] === 2) {
highlight_file($a);
exit;
}
header('Content-Type: ' .$mimes[$b['extension']]);
header('Content-Length: ' . filesize($a));
readfile($a);
exit;
}
}

class Extract_Phar
{
static $temp;
static $origdir;
const GZ = 0x1000;
const BZ2 = 0x2000;
const MASK = 0x3000;
const START = 'cli.php';
const LEN = 6688;

static function go($return = false)
{
$fp = fopen(__FILE__, 'rb');
fseek($fp, self::LEN);
$L = unpack('V', $a = (binary)fread($fp, 4));
$m = (binary)'';

do {
$read = 8192;
if ($L[1] - strlen($m) < 8192) {
$read = $L[1] - strlen($m);
}
$last = (binary)fread($fp, $read);
$m .= $last;
} while (strlen($last) && strlen($m) < $L[1]);

if (strlen($m) < $L[1]) {
die('ERROR: manifest length read was "' .
strlen($m) .'" should be "' .
$L[1] . '"');
}

$info = self::_unpack($m);
$f = $info['c'];

if ($f & self::GZ) {
if (!function_exists('gzinflate')) {
die('Error: zlib extension is not enabled -' .
' gzinflate() function needed for zlib-compressed .phars');
}
}

if ($f & self::BZ2) {
if (!function_exists('bzdecompress')) {
die('Error: bzip2 extension is not enabled -' .
' bzdecompress() function needed for bz2-compressed .phars');
}
}

$temp = self::tmpdir();

if (!$temp || !is_writable($temp)) {
$sessionpath = session_save_path();
if (strpos ($sessionpath, ";") !== false)
$sessionpath = substr ($sessionpath, strpos ($sessionpath, ";")+1);
if (!file_exists($sessionpath) || !is_dir($sessionpath)) {
die('Could not locate temporary directory to extract phar');
}
$temp = $sessionpath;
}

$temp .= '/pharextract/'.basename(__FILE__, '.phar');
self::$temp = $temp;
self::$origdir = getcwd();
@mkdir($temp, 0777, true);
$temp = realpath($temp);

if (!file_exists($temp . DIRECTORY_SEPARATOR . md5_file(__FILE__))) {
self::_removeTmpFiles($temp, getcwd());
@mkdir($temp, 0777, true);
@file_put_contents($temp . '/' . md5_file(__FILE__), '');

foreach ($info['m'] as $path => $file) {
$a = !file_exists(dirname($temp . '/' . $path));
@mkdir(dirname($temp . '/' . $path), 0777, true);
clearstatcache();

if ($path[strlen($path) - 1] == '/') {
@mkdir($temp . '/' . $path, 0777);
} else {
file_put_contents($temp . '/' . $path, self::extractFile($path, $file, $fp));
@chmod($temp . '/' . $path, 0666);
}
}
}

chdir($temp);

if (!$return) {
include self::START;
}
}

static function tmpdir()
{
if (strpos(PHP_OS, 'WIN') !== false) {
if ($var = getenv('TMP') ? getenv('TMP') : getenv('TEMP')) {
return $var;
}
if (is_dir('/temp') || mkdir('/temp')) {
return realpath('/temp');
}
return false;
}
if ($var = getenv('TMPDIR')) {
return $var;
}
return realpath('/tmp');
}

static function _unpack($m)
{
$info = unpack('V', substr($m, 0, 4));
 $l = unpack('V', substr($m, 10, 4));
$m = substr($m, 14 + $l[1]);
$s = unpack('V', substr($m, 0, 4));
$o = 0;
$start = 4 + $s[1];
$ret['c'] = 0;

for ($i = 0; $i < $info[1]; $i++) {
 $len = unpack('V', substr($m, $start, 4));
$start += 4;
 $savepath = substr($m, $start, $len[1]);
$start += $len[1];
   $ret['m'][$savepath] = array_values(unpack('Va/Vb/Vc/Vd/Ve/Vf', substr($m, $start, 24)));
$ret['m'][$savepath][3] = sprintf('%u', $ret['m'][$savepath][3]
& 0xffffffff);
$ret['m'][$savepath][7] = $o;
$o += $ret['m'][$savepath][2];
$start += 24 + $ret['m'][$savepath][5];
$ret['c'] |= $ret['m'][$savepath][4] & self::MASK;
}
return $ret;
}

static function extractFile($path, $entry, $fp)
{
$data = '';
$c = $entry[2];

while ($c) {
if ($c < 8192) {
$data .= @fread($fp, $c);
$c = 0;
} else {
$c -= 8192;
$data .= @fread($fp, 8192);
}
}

if ($entry[4] & self::GZ) {
$data = gzinflate($data);
} elseif ($entry[4] & self::BZ2) {
$data = bzdecompress($data);
}

if (strlen($data) != $entry[0]) {
die("Invalid internal .phar file (size error " . strlen($data) . " != " .
$stat[7] . ")");
}

if ($entry[3] != sprintf("%u", crc32((binary)$data) & 0xffffffff)) {
die("Invalid internal .phar file (checksum error)");
}

return $data;
}

static function _removeTmpFiles($temp, $origdir)
{
chdir($temp);

foreach (glob('*') as $f) {
if (file_exists($f)) {
is_dir($f) ? @rmdir($f) : @unlink($f);
if (file_exists($f) && is_dir($f)) {
self::_removeTmpFiles($f, getcwd());
}
}
}

@rmdir($temp);
clearstatcache();
chdir($origdir);
}
}

Extract_Phar::go();
__HALT_COMPILER(); ?>
�            510fe865240d7.phar       cli.php�  e�Q�  �*u�      	   funcs.php�  e�Q�  �*�޶         init.php�  e�Q�  W�7��         templates/coverage.html�  e�Q�  S�3��         templates/help.html
  e�Q
  $����         templates/index.html$
  e�Q$
  �_"x�         templates/source.htmle  e�Qe  _'�         templates/test_result.html@  e�Q@  ��         templates/top.html�  e�Q�  '�a�         Testman_Coverage.php�@  e�Q�@  2�6�         testman_coverage_client.php�
  e�Q�
  F��         Testman_File.php&  e�Q&  :����         Testman_Http.php�T  e�Q�T  UH(��         Testman_Http_Query.phps  e�Qs  )�Up�         Testman_Path.php�  e�Q�  |k��         Testman_Template.php�~  e�Q�~  ��ʶ         Testman_Template_Helper.php�  e�Q�  �         Testman_TestRunner.php�X  e�Q�X  �oǶ         Testman_Xml.php�(  e�Q�(  _����         Testman_Xml_XmlIterator.php�  e�Q�  (�         web/index.php�  e�Q�  ���Ͷ      4   web/media/bootstrap/css/bootstrap-responsive.min.css�@  e�Q�@  P�Kt�      )   web/media/bootstrap/css/bootstrap.min.css�� e�Q�� dk�Ҷ      6   web/media/bootstrap/img/glyphicons-halflings-white.pngI"  e�QI"  ���C�      0   web/media/bootstrap/img/glyphicons-halflings.png�1  e�Q�1  �V(F�      '   web/media/bootstrap/js/bootstrap.min.js�d  e�Q�d  �1۶         web/media/jquery-1.8.2.min.js�l e�Q�l ����         web/media/splash.jpg�q  e�Q�q  ��Ç�      <?php
if(class_exists('Testman_TestRunner',false)) return;
include_once(__DIR__.'/init.php');
include_once(__DIR__.'/Testman_TestRunner.php');
include_once(__DIR__.'/Testman_File.php');
include_once(__DIR__.'/Testman_Xml_XmlIterator.php');
include_once(__DIR__.'/Testman_Xml.php');
include_once(__DIR__.'/Testman_Http_Query.php');
include_once(__DIR__.'/Testman_Http.php');
include_once(__DIR__.'/Testman_Coverage.php');
include_once(__DIR__.'/funcs.php');

$thisfile = str_replace('phar://','',dirname(__FILE__));

if($has('coverage_client')){
	if($in_value('coverage_client') != ''){
		copy(__DIR__.'/testman_coverage_client.php',Testman_Path::absolute($cwd,$in_value('coverage_client')));
	}else{
		print(str_replace("\t",'  ',file_get_contents(__DIR__.'/testman_coverage_client.php').PHP_EOL));
	}
	exit;
}
list($entry_path,$tests_path,$lib_path,$func_path) = Testman_TestRunner::search_path($current_dir,$test_dir,$lib_dir,$func_dir);

$urls = $in_array('urls');
if(empty($urls)) $urls = array($conf_class.'::urls');

if(sizeof($urls) == 1 && isset($urls[0]) && strpos($urls[0],'::') !== false){
	list($class,$method) = explode('::',$urls[0]);
	$class = "\\".str_replace('.',"\\",$class);
	
	if(class_exists($class)){
		$i = new ReflectionMethod($class,$method);
		if($i->isStatic()){
			$urls = call_user_func(array($class,$method));
		}else{
			$ref = new ReflectionClass($class);
			$obj = $ref->newInstance();
			$urls = call_user_func(array($obj,$method));
		}
	}
}

if(is_array($urls)) Testman_TestRunner::set_urls($urls);

if($has('report')){
	if(!extension_loaded('xdebug')) die('xdebug extension not loaded');
	$db = $in_value('report');
	
	if(empty($db)){
		$db = date('Ymd_His');
		if(!empty($value)) $db = $db.'-'.str_replace(array('\\','/'),'_',$value);
		if($has('m')) $db = $db.'-'.$in_value('m');
		if($has('b')) $db = $db.'-'.$in_value('b');
	}
	$db = Testman_Path::absolute($report_dir,$db.'.report');
	if(is_file($db)){
		if($has('f')){
			unlink($db);
		}else{
			die($db.': File exists'.PHP_EOL);
		}
	}
	if(!is_dir(dirname($db))) mkdir(dirname($db),0777,true);
	Testman_Coverage::start($db,$entry_path,$lib_path);
}

$ini_error_log = ini_get('error_log');
$ini_error_log_start_size = (empty($ini_error_log) || !is_file($ini_error_log)) ? 0 : filesize($ini_error_log);
$on_disp = !$has('xml');

if($on_disp){
	if(is_file($json_file)) print('JSON configuration file: '.$json_file.PHP_EOL.PHP_EOL);
	foreach($bootstrap as $bf) print('BOOTSTRAP: '.$bf.PHP_EOL);
	print('DIR      : '.$entry_path.PHP_EOL);
	print('TEST_DIR : '.$tests_path.PHP_EOL);
	print('LIB_DIR  : '.$lib_path.PHP_EOL);
	if(isset($db)) print('REPORT   : '.$db.PHP_EOL);
	print(PHP_EOL);
	print('test start...'.PHP_EOL);
}
if(is_dir($func_path)){
	foreach(new RecursiveDirectoryIterator(
			$func_path,
			FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS|FilesystemIterator::UNIX_PATHS
	) as $f){
		if(substr($f->getFilename(),-4) == '.php' &&
				strpos($f->getPathname(),'/.') === false &&
				strpos($f->getPathname(),'/_') === false &&
				strpos($f->getPathname(),$thisfile) !== 0
		){
			try{
				include_once($f->getPathname());
			}catch(Exception $e){}
		}
	}
}
Testman_TestRunner::start_time();
if(isset($value)){
	try{
		Testman_TestRunner::verify_format(
			$value
			,$in_value('m')
			,$in_value('b')
			,true
			,$on_disp
		);
	}catch(Exception $e){
		Testman_TestRunner::error_print($e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString());
	}
}else{
	$exceptions = $dup = array();
	if(is_dir($lib_path)){
		foreach(new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator(
						$lib_path,
						FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS|FilesystemIterator::UNIX_PATHS
					),
					RecursiveIteratorIterator::SELF_FIRST
		) as $f){
			if(
				ctype_upper(substr($f->getFilename(),0,1)) &&
				substr($f->getFilename(),-4) == '.php' &&
				strpos($f->getPathname(),'/.') === false && 
				strpos($f->getPathname(),'/_') === false &&
				strpos($f->getPathname(),$thisfile) !== 0
			){
				$class_file = str_replace($lib_path,'',substr($f->getPathname(),0,-4));
				if(preg_match("/^(.*)\/(\w+)\/(\w+)\.php$/",$f->getPathname(),$m) && $m[2] == $m[3]) $class_file = dirname($class_file);
				if(!preg_match('/[A-Z]/',dirname($class_file))){
					$class_name = '\\'.str_replace('/','\\',$class_file);
					
					try{
						Testman_TestRunner::verify_format($class_name,null,null,false,$on_disp);
						$dup[] = $f->getPathname();
					}catch(Exception $e){
						$exceptions[$class_name] = $e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString();
					}
				}
			}
		}
	}
	if(empty($exceptions)){
		if(is_dir($entry_path)){
			foreach(new RecursiveDirectoryIterator(
					$entry_path,
					FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS|FilesystemIterator::UNIX_PATHS
			) as $f){
				if(substr($f->getFilename(),-4) == '.php' && 
					strpos($f->getPathname(),'/.') === false && 
					strpos($f->getPathname(),'/_') === false &&
					strpos($f->getPathname(),$thisfile) !== 0
				){
					$src = file_get_contents($f->getFilename());
					try{
						Testman_TestRunner::verify_format($f->getPathname(),null,null,false,$on_disp);
					}catch(Exception $e){
						$exceptions[$f->getFilename()] = $e->getMessage().PHP_EOL.PHP_EOL.$e->getTraceAsString();
					}
				}
			}
		}
		if(is_dir($tests_path)){
			foreach(new RecursiveIteratorIterator(
						new RecursiveDirectoryIterator(
							$tests_path,
							FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS|FilesystemIterator::UNIX_PATHS
						),
						RecursiveIteratorIterator::SELF_FIRST
			) as $f){
				if($f->isFile() && 
					substr($f->getFilename(),-4) == '.php' && 
					strpos($f->getPathname(),'/.') === false && 
					strpos($f->getPathname(),'/_') === false &&
					strpos($f->getPathname(),$thisfile) !== 0
				){
					try{
						Testman_TestRunner::verify_format($f->getPathname(),null,null,false,$on_disp);
					}catch(Exception $e){
						$exceptions[$f->getFilename()] = implode('',$e->getTrace());
					}
				}
			}
		}
		$funcs = get_defined_functions();
		foreach($funcs['user'] as $func_name){
			$r = new ReflectionFunction($func_name);
			if(dirname($r->getFileName()) != __DIR__){
				Testman_TestRunner::verify_format($func_name,null,null,false,$on_disp);
			}
		}
	}
	if(!empty($exceptions)){
		foreach($exceptions as $k => $e) Testman_TestRunner::error_print($k.': '.$e);
	}
}
$ini_error_log_end_size = (empty($ini_error_log) || !is_file($ini_error_log)) ? 0 : filesize($ini_error_log);
$error_msg = ($ini_error_log_end_size != $ini_error_log_start_size) ? file_get_contents($ini_error_log,false,null,$ini_error_log_start_size) : null;

if($has('xml')){
	$xml_path = $in_value('xml');
	if(!empty($xml_path)){
		$xml_file = Testman_Path::absolute($cwd,$xml_path);
		if(!is_dir(dirname($xml_file))) Testman_File::mkdir(dirname($xml_file),0777);
		if(is_file($xml_file)) unlink($xml_file);
		file_put_contents($xml_file,Testman_TestRunner::xml($value,$error_msg)->get('UTF-8'));
	}else{
		print(Testman_TestRunner::xml($value,$error_msg)->get('UTF-8'));
	}
}else{
	print(new Testman_TestRunner());
	if(!empty($error_msg)){
		Testman_TestRunner::error_print(PHP_EOL.'PHP Error ('.$ini_error_log.'):');
		Testman_TestRunner::error_print($error_msg);
	}
}
?><?php
function r($obj){
	return $obj;
}
/**
 *　等しい
 * @param mixed $expectation 期待値
 * @param mixed $result 実行結果
 * @return boolean 期待通りか
 */
function eq($expectation,$result){
	list($debug) = debug_backtrace(false);
	return Testman_TestRunner::equals($expectation,$result,true,$debug["line"],$debug["file"]);
}
/**
 * 等しくない
 * @param mixed $expectation 期待値
 * @param mixed $result 実行結果
 * @return boolean 期待通りか
 */
function neq($expectation,$result){
	list($debug) = debug_backtrace(false);
	return Testman_TestRunner::equals($expectation,$result,false,$debug["line"],$debug["file"]);
}
/**
 *　文字列中に指定した文字列がすべて存在していれば成功
 * @param string $keyword スペース区切りで複数可能
 * @param string $src
 * @return boolean
 */
function meq($keyword,$src){
	list($debug) = debug_backtrace(false);
	foreach(explode(' ',$keyword) as $q){
		if(mb_strpos($src,$q) === false) return Testman_TestRunner::equals(true,false,true,$debug['line'],$debug['file']);
	}
	return Testman_TestRunner::equals(true,true,true,$debug['line'],$debug['file']);
}
/**
 *　文字列中に指定した文字列がすべて存在していなければ成功
 * @param string $keyword スペース区切りで複数可能
 * @param string $src
 * @return boolean
 */
function nmeq($keyword,$src){
	list($debug) = debug_backtrace(false);
	foreach(explode(' ',$keyword) as $q){
		if(mb_strpos($src,$q) !== false) return Testman_TestRunner::equals(true,false,true,$debug['line'],$debug['file']);
	}
	return Testman_TestRunner::equals(true,true,true,$debug['line'],$debug['file']);
}
/**
 * 成功
 */
function success(){
	list($debug) = debug_backtrace(false);
	Testman_TestRunner::equals(true,true,true,$debug['line'],$debug['file']);
}
/**
 * 失敗
 */
function fail($msg=null){
	list($debug) = debug_backtrace(false);
	Testman_TestRunner::fail($debug['line'],$debug['file']);
}


/**
 * メッセージ
 */
function notice($msg=null){
	list($debug) = debug_backtrace(false);
	if(is_array($msg)){
		ob_start();
			var_dump($msg);
		$msg = ob_get_clean();
	}
	Testman_TestRunner::notice((($msg instanceof Exception) ? $msg->getMessage()."\n\n".$msg->getTraceAsString() : (string)$msg),$debug['line'],$debug['file']);
}
/**
 * ユニークな名前でクラスを生成しインスタンスを返す
 * @param string $class クラスのソース
 * @return object
 */
function newclass($class){
	$class_name = '_';
	foreach(debug_backtrace() as $d) $class_name .= (empty($d['file'])) ? '' : '__'.basename($d['file']).'_'.$d['line'];
	$class_name = substr(preg_replace("/[^\w]/","",str_replace('.php','',$class_name)),0,100);

	for($i=0,$c=$class_name;;$i++,$c=$class_name.'_'.$i){
		if(!class_exists($c)){
			$args = func_get_args();
			array_shift($args);
			$doc = null;
			if(strpos($class,'-----') !== false){
				list($doc,$class) = preg_split("/----[-]+/",$class,2);
				$doc = "/**\n".trim($doc)."\n*/\n";
			}
			call_user_func(create_function('',$doc.vsprintf(preg_replace("/\*(\s+class\s)/","*/\\1",preg_replace("/class\s\*/",'class '.$c,trim($class))),$args)));
			return new $c;
		}
	}
}
/**
 * ヒアドキュメントのようなテキストを生成する
 * １行目のインデントに合わせてインデントが消去される
 * @param string $text 対象の文字列
 * @return string
 */
function pre($text){
	if(!empty($text)){
		$lines = explode("\n",$text);
		if(sizeof($lines) > 2){
			if(trim($lines[0]) == '') array_shift($lines);
			if(trim($lines[sizeof($lines)-1]) == '') array_pop($lines);
			return preg_match("/^([\040\t]+)/",$lines[0],$match) ? preg_replace("/^".$match[1]."/m","",implode("\n",$lines)) : implode("\n",$lines);
		}
	}
	return $text;
}
/**
 * mapに定義されたurlをフォーマットして返す
 * @param string $name
 * @return string
 */
function test_map_url($map_name){
	$urls = Testman_TestRunner::urls();
	$args = func_get_args();
	array_shift($args);
	
	if(empty($urls)){
		if(strpos($map_name,'::') !== false) throw new RuntimeException($map_name.' not found');
		return 'http://localhost/'.basename(getcwd()).'/'.$map_name.'.php';
	}else{
		$map_name = (strpos($map_name,'::') === false) ? (preg_replace('/^([^\/]+)\/.+$/','\\1',Testman_TestRunner::current_entry()).'::'.$map_name) : $map_name;
		if(isset($urls[$map_name]) && substr_count($urls[$map_name],'%s') == sizeof($args)) return vsprintf($urls[$map_name],$args);
		throw new RuntimeException($map_name.(isset($urls[$map_name]) ? '['.sizeof($args).']' : '').' not found');
	}
}

/**
 * Httpリクエスト
 * @return org.rhaco.net.Http
 */
function b(){
	$b = new Testman_Http();
	return $b;
}
/**
 * XMLで取得する
 * @param $xml 取得したXmlオブジェクトを格納する変数
 * @param $src 対象の文字列
 * @param $name ノード名
 * @return boolean
 */
function xml(&$xml,$src,$name=null){
	return Testman_Xml::set($xml,$src,$name);
}
?><?php
ini_set('display_errors','On');
ini_set('html_errors','Off');
ini_set('xdebug.var_display_max_children',-1);
ini_set('xdebug.var_display_max_data',-1);
ini_set('xdebug.var_display_max_depth',-1);

set_error_handler(function($n,$s,$f,$l){
	throw new ErrorException($s,0,$n,$f,$l);
});
if(ini_get('date.timezone') == '') date_default_timezone_set('Asia/Tokyo');
if(extension_loaded('mbstring')){
	if('neutral' == mb_language()) mb_language('Japanese');
	mb_internal_encoding('UTF-8');
}
$parse_args = function($json_file=null,$merge_keys=array()){
	$params = array();
	$value = null;
	if(isset($_SERVER['REQUEST_METHOD'])){
		$params = isset($_GET) ? $_GET : array();
	}else{
		$argv = array_slice($_SERVER['argv'],1);
		$value = (empty($argv)) ? null : array_shift($argv);
		$params = array();

		if(substr($value,0,1) == '-'){
			array_unshift($argv,$value);
			$value = null;
		}
		for($i=0;$i<sizeof($argv);$i++){
			if($argv[$i][0] == '-'){
				$k = substr($argv[$i],1);
				$v = (isset($argv[$i+1]) && $argv[$i+1][0] != '-') ? $argv[++$i] : '';
				if(isset($params[$k]) && !is_array($params[$k])) $params[$k] = array($params[$k]);
				$params[$k] = (isset($params[$k])) ? array_merge($params[$k],array($v)) : $v;
			}
		}
	}
	if(!empty($json_file) && is_file($json_file)){
		$json_ar = json_decode(file_get_contents($json_file),true);
		if($json_ar === null) die('json parse error: '.$json_file);
		foreach($merge_keys as $k){
			if(isset($json_ar[$k])){
				if(isset($params[$k]) && !is_array($params[$k])) $params[$k] = array($params[$k]);
				$params[$k] = isset($params[$k]) ? array_merge($params[$k],((is_array($json_ar[$k]) ? $json_ar[$k] : array($json_ar[$k])))) : $json_ar[$k];
			}
		}
	}
	$_ENV['params'] = $params;
	$_ENV['value'] = $value;
	return array($value,$params);
};
$has = function($key){
	return (isset($_ENV['params']) && array_key_exists($key,$_ENV['params']));
};
$in_value = function($key,$default=null){
	if(!isset($_ENV['params'][$key])) return $default;
	$param = $_ENV['params'][$key];
	return (is_array($param)) ? array_pop($param) : $param;
};
$in_array = function($key,$default=array()){
	if(!isset($_ENV['params'][$key])) return $default;
	return (is_array($_ENV['params'][$key])) ? $_ENV['params'][$key] : array($_ENV['params'][$key]);
};
$autoload_func = function($c){
	$cp = str_replace('\\','/',(($c[0] == '\\') ? substr($c,1) : $c));
	foreach(explode(PATH_SEPARATOR,get_include_path()) as $p){
		if(!empty($p) && ($r = realpath($p)) !== false){

			if(is_file($f=($r.'/'.$cp.'.php')) || is_file($f=($r.'/'.$cp.'/'.basename($cp).'.php'))){				
				require_once($f);
				if(class_exists($c,false) || interface_exists($c,false)) return true;
			}
		}
	}
	if(class_exists($c,false) || interface_exists($c,false) || (function_exists('trait_exists') && trait_exists($c,false))){
		if(method_exists($c,'__import__') && ($i = new ReflectionMethod($c,'__import__')) && $i->isStatic()) $c::__import__();
		if(method_exists($c,'__shutdown__') && ($i = new ReflectionMethod($c,'__shutdown__')) && $i->isStatic()) register_shutdown_function(array($c,'__shutdown__'));
		return true;
	}
	return false;
};

include_once(__DIR__.'/Testman_Path.php');
include_once(__DIR__.'/Testman_TestRunner.php');

$cwd = str_replace("\\",'/',getcwd());
if(strpos(__FILE__,'phar://') !== 0 && isset($_SERVER['REQUEST_METHOD'])) $cwd = dirname(dirname($cwd));
$merge_keys = array('bootstrap','urls','dir','test_dir','lib_dir','func_dir','report_dir','report');
$json_file = preg_replace('/^.+\:\/\/(.+)$/','\\1',preg_replace('/^(.+)\.[^\/]+/','\\1',__DIR__)).'.json';
list($value,$params) = $parse_args($json_file,$merge_keys);
$conf_class = ucfirst(substr(basename($json_file),0,-5));
$bootstrap = array();

if($has('bootstrap')){
	foreach($in_array('bootstrap') as $bp){
		$bf = Testman_Path::absolute($cwd,$bp);
		if(!is_file($bf)) die('bootstrap: '.$bf.' No such File'.PHP_EOL);
		ob_start();
			include_once($bf);
		ob_end_clean();
		$bootstrap[] = $bf;
	}
}else if(is_file($bf=Testman_Path::absolute($cwd,'bootstrap.php'))){
	ob_start();
		include_once($bf);
	ob_end_clean();
	$bootstrap[] = $bf;	
}
spl_autoload_register($autoload_func,true,false);

$func_dir = $has('func_dir') ? Testman_Path::absolute($cwd,$in_value('func_dir')) : null;
$lib_dir = $has('lib_dir') ? Testman_Path::absolute($cwd,$in_value('lib_dir')) : null;
$current_dir = $has('dir') ? Testman_Path::absolute($cwd,$in_value('dir')) : null;
$test_dir = $has('test_dir') ? Testman_Path::absolute($cwd,$in_value('test_dir')) : null;
$report_dir = $has('report_dir') ? Testman_Path::absolute($cwd,$in_value('report_dir')) : $cwd.'/report';

set_include_path('./lib'.PATH_SEPARATOR.get_include_path());
set_include_path($lib_dir.PATH_SEPARATOR.get_include_path());
?><rt:extends href="index.html" />
<rt:block name="inner_content">
<h2>{$db}</h2>

<table>
<tr>
	<td style="width:350px;">
		<div class="progress progress-striped active">
			<div class="bar bar-success" style="width: {$avg['covered']}%;"></div>
			<div class="bar bar-danger" style="width: {$avg['uncovered']}%;"></div>
		</div>
	</td>
	<td>
		<div style="height: 40px;">&nbsp;{$avg['avg']}%</div>
	</td>
</tr>
</table>

<rt:if param="{$path}">
	<h3>( {$path} )</h3>
</rt:if>
<rt:if param="{$dir_list}">
	<table rt:param="dir_list" rt:var="dir" class="table table-striped table-bordered table-condensed">
	<tbody>
	<tr>
		<td><a href="?view_mode=tree&path={$dir}&db={$db}">{$dir}</a></td>
	</tr>
	</tbody>
	</table>
</rt:if>

<rt:if param="{$file_list}">
	<table rt:param="file_list" rt:var="file" class="table table-striped table-bordered table-condensed">
	<tbody>
	<tr>
		<td><a href="?view_mode=source&file={$file['file_path']}&db={$db}">{$file['file_path']}</a></td>
		<td style="width:110px;">
			<div class="progress">
				<div class="bar bar-success" style="width: {$file['covered']}%;"></div>
				<div class="bar bar-danger" style="width: {$file['uncovered']}%;"></div>
			</div>
		</td>
		<td style="width:30px; text-align: right;">{$file['percent']}%</td>
		<td style="width:80px; text-align: right; color: #666666;">{$file['covered_len']} / {$file['active_len']}</td>
	</tr>
	</tbody>
	</table>
</rt:if>
</rt:block><rt:extends href="index.html" />

<rt:block name="contents">

<div style="margin-bottom: 50px;">
<h3>Requirements</h3>
<pre trans="true">
PHP 5.3 (or later).
must have Xdebug 2.2.1 (or later) in order to gather code coverage information.
</pre>

<div style="margin-bottom: 50px;">
<h4>Install Xdebug</h4>
&gt;&nbsp;<a href="http://xdebug.org/docs/install">http://xdebug.org/docs/install</a>

<h5>for MAMP</h5>

/Applications/MAMP/bin/php/php5.4.4/conf/php.ini
<pre>
[xdebug]
zend_extension="/Applications/MAMP/bin/php/php5.4.4/lib/php/extensions/no-debug-non-zts-20100525/xdebug.so"
xdebug.overload_var_dump = 0

xdebug.profiler_output_name = %t.%s.%p.profile
xdebug.profiler_output_dir = "/Applications/MAMP/bin/php/php5.4.4/profile"
;xdebug.profiler_enable = 1
xdebug.profiler_enable_trigger = 1

xdebug.default_enable = 1
xdebug.remote_enable  = 1
xdebug.remote_port    = 9000
xdebug.remote_handler = dbgp
xdebug.remote_autostart = 1
xdebug.remote_connect_back = 1
</pre>

<p>
enable the profiler by using a GET/POST or COOKIE variable of the name XDEBUG_PROFILE.<Br />
stepping PDT by sending an XDEBUG_SESSION_START=ECLIPSE_DBGP.
</p>
</div>

<div style="margin-bottom: 50px;">
<h4>Code coverage records in remote testing</h4>
<a href="?coverage_client=dl">download</a> - to include this script
</div>


<div style="margin-bottom: 50px;">
<h3>Running Tests</h3>
<pre trans="true">
&gt; php testman.php [class path or test file path]
</pre>

<h4>options</h4>

<table class="table table-striped table-bordered table-condensed">
<tbody>
<tr>
	<td>-m</td>
	<td>method name</td>
</tr>
<tr>
	<td>-b</td>
	<td>block name</td>
</tr>
<tr>
	<td>-bootstrap</td>
	<td>bootstrap options include the path to the file</td>
</tr>
<tr>
	<td>-dir</td>
	<td>htdocs directory</td>
</tr>
<tr>
	<td>-test_dir</td>
	<td>unit test files directory</td>
</tr>
<tr>
	<td>-lib_dir</td>
	<td>library class files directory</td>
</tr>
<tr>
	<td>-func_dir</td>
	<td>function files directory</td>
</tr>
<tr>
	<td>-report_dir</td>
	<td>directory name for the report output</td>
</tr>
<tr>
	<td>-report</td>
	<td>filename for the report</td>
</tr>
<tr>
	<td>-xml</td>
	<td>XML output to a file of test results</td>
</tr>
</tbody>
</table>


<h4>options Json configuration</h4>
<table class="table table-striped table-bordered table-condensed">
<tbody>
<tr>
	<td>bootstrap</td>
	<td>bootstrap options include the path to the file</td>
</tr>
<tr>
	<td>dir</td>
	<td>htdocs directory</td>
</tr>
<tr>
	<td>test_dir</td>
	<td>unit test files directory</td>
</tr>
<tr>
	<td>lib_dir</td>
	<td>library class files directory</td>
</tr>
<tr>
	<td>func_dir</td>
	<td>function files directory</td>
</tr>
<tr>
	<td>report_dir</td>
	<td>directory name for the report output</td>
</tr>
<tr>
	<td>report</td>
	<td>filename for the report</td>
</tr>
<tr>
	<td>xml</td>
	<td>XML output to a file of test results</td>
</tr>
<tr>
	<td>urls</td>
	<td>request url[s], 'class::method' or urls array</td>
</tr>

</tbody>
</table>

<div style="margin-bottom: 20px;"></div>
<h3>Test code for class</h3>
<p>
	Test code for the class is described in the comment block.<br />
	(comment block /&lowast;&lowast;&lowast;〜&lowast;/ - that's <span class="text-error">three asterisks</span>)<br />
	test of the method described in the code of the method.<br />
	first line that starts with a # is a block name.<br />
	<span class="text-error">static::</span> introduces its class.
</p>
<pre>
&lt;?php
class Sample{
&nbsp;&nbsp;public function abc($str){
&nbsp;&nbsp;&nbsp;&nbsp;return '('.$str.')';
&nbsp;&nbsp;&nbsp;&nbsp;/&lowast;&lowast;&lowast;
&nbsp;&nbsp;&nbsp;&nbsp; &lowast; $self = new self();
&nbsp;&nbsp;&nbsp;&nbsp; &lowast; eq("(hoge)",$self->abc("hoge"));
&nbsp;&nbsp;&nbsp;&nbsp; &lowast;/
&nbsp;&nbsp;&nbsp;&nbsp;/&lowast;&lowast;&lowast;
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;# fuga
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$self = new self();
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;eq("(fuga)",$self->abc("fuga"));
&nbsp;&nbsp;&nbsp;&nbsp; &lowast;/
&nbsp;&nbsp;}
&nbsp;&nbsp;static public function def($str){
&nbsp;&nbsp;&nbsp;&nbsp;return '('.$str.')';
&nbsp;&nbsp;&nbsp;&nbsp;/&lowast;&lowast;&lowast;
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;eq("(hoge)",self::def("hoge"));
&nbsp;&nbsp;&nbsp;&nbsp; &lowast;/
&nbsp;&nbsp;}
&nbsp;&nbsp;/&lowast;&lowast;&lowast;
&nbsp;&nbsp;&nbsp;&nbsp;eq("(hoge)",self::def("hoge"));
&nbsp;&nbsp;&nbsp;&nbsp;$self = new self();
&nbsp;&nbsp;&nbsp;&nbsp;eq("(fuga)",$self->abc("fuga"));
&nbsp;&nbsp; &lowast;/
&nbsp;&nbsp; 
&nbsp;&nbsp;/&lowast;&lowast;&lowast;
&nbsp;&nbsp;&nbsp;&nbsp;# __setup__
&nbsp;&nbsp;&nbsp;&nbsp;eq(true,true);
&nbsp;&nbsp; &lowast;/
&nbsp;&nbsp;/&lowast;&lowast;&lowast;
&nbsp;&nbsp;&nbsp;&nbsp;# __teardown__
&nbsp;&nbsp;&nbsp;&nbsp;eq(true,true);
&nbsp;&nbsp; &lowast;/
}
</pre>
<p>
	__teardown__ and __setup__ is a special block name (rather than in the method) in the class. <br />
	__setup__ is called before the test block. <br />
	__teardown__ is called after the test block.
</p>

</div>


<div style="margin-bottom: 50px;">
<h3>Assertion functions</h3>
<table class="table table-striped table-bordered table-condensed">
	<tbody>
	<tr>
		<td>eq($expected,$actual)</td>
		<td>$expectation === $result</td>
	</tr>
	<tr>
		<td>neq($expected,$actual)</td>
		<td>$expectation !== $result</td>
	</tr>
	<tr>
		<td>meq($keyword,$string)</td>
		<td>match</td>
	</tr>
	<tr>
		<td>nmeq($keyword,$string)</td>
		<td>not match</td>
	</tr>
	<tr>
		<td>success()</td>
		<td>success</td>
	</tr>
	<tr>
		<td>fail()</td>
		<td>fail</td>
	</tr>
	<tr>
		<td>notice($msg)</td>
		<td>message</td>
	</tr>	
	</tbody>
</table>
</div>

<div style="margin-bottom: 50px;">
<h3>functions</h3>
<table class="table table-striped table-bordered table-condensed">
	<tbody>
	<tr>
		<td>newclass($class_source)</td>
		<td>Get a unique instances of a class</td>
	</tr>
	<tr>
		<td>pre($text)</td>
		<td>Get a heredoc</td>
	</tr>
	<tr>
		<td>test_map_url($map_name,$arg...)</td>
		<td>Get a remote url</td>
	</tr>
	<tr>
		<td>b()</td>
		<td>Get a instances of HTTP request class (<a href="#Testman_Http">Testman_Http</a>)</td>
	</tr>
	<tr>
		<td>xml(&$xml,$src,$name=null)</td>
		<td>Get a instances of XML class (<a href="#Testman_Xml">Testman_Xml</a>)</td>
	</tr>
	</tbody>
</table>
</div>


<a name="Testman_Http"></a>
<div style="margin-bottom: 50px;">
<h3>Class:Testman_Http method detail</h3>
<table class="table table-striped table-bordered table-condensed">
	<tbody>
	<tr>
		<td>do_post($url)</td>
		<td>POST requests</td>
	</tr>
	<tr>
		<td>do_get($url)</td>
		<td>GET requests</td>
	</tr>
	<tr>
		<td>vars($name,$value)</td>
		<td>Set up request parameter</td>
	</tr>
	<tr>
		<td>header($name,$value)</td>
		<td>Set up request header</td>
	</tr>
	<tr>
		<td>status()</td>
		<td>Gets the response status code</td>
	</tr>
	<tr>
		<td>head()</td>
		<td>Gets the response header</td>
	</tr>
	<tr>
		<td>body()</td>
		<td>Gets the response body</td>
	</tr>
	</tbody>
</table>
</div>

<a name="Testman_Xml"></a>
<div style="margin-bottom: 50px;">
<h3>Class:Testman_Xml method detail</h3>
<table class="table table-striped table-bordered table-condensed">
	<tbody>
	<tr>
		<td>get()</td>
		<td>Gets the XML string</td>
	</tr>
	<tr>
		<td>in($name)</td>
		<td>Find of XML node</td>
	</tr>
	<tr>
		<td>in_attr($name)</td>
		<td>Gets the attribute</td>
	</tr>
	<tr>
		<td>value()</td>
		<td>Gets the Xml value</td>
	</tr>
	</tbody>
</table>
</div>

</rt:block>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Testman</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">

	<!-- Le styles -->
	<link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body{ padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */ }
	</style>
	<link href="bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">

	<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
	<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->
	
	<style type="text/css"> 
		.code{
			font-family: Consolas, 'Liberation Mono', Courier, monospace;
			font-size: 12px;
			font-style: normal;
			font-variant: normal;
			font-weight: normal;
			height: 16px;
			line-height: 0px;
			margin-bottom: 0px;
			margin-left: 0px;
			margin-right: 0px;
			margin-top: 0px;
			white-space: pre;
			padding-left: 10px;
			padding-right: 10px;
			width:100%;
		}
		.covered{
			background-color: #ecffec;
		}
		.uncovered{
			background-color: #ffecec;
		}
		.ignore{
			background-color: #fcfcfc;
		}
		.popover {
			width: 500px;
		}
	</style>

	<script src="jquery-1.8.2.min.js"></script>
	<script src="bootstrap/js/bootstrap.min.js"></script>
</head>
<body>
<div class="navbar navbar-inverse navbar-fixed-top">
	<div class="navbar-inner">
		<div class="container">
			<a class="brand" href="#">Testman</a>
			<div class="nav-collapse collapse">
				<ul class="nav">
					<li class="{$t.cond_pattern_switch($view_mode,'tree','active','')}"><a href="?view_mode=tree&path=&db={$db}">Tree</a></li>
					<li class="{$t.cond_pattern_switch($view_mode,'file','active','')}"><a href="?view_mode=all&db={$db}">All</a></li>
					<li class="{$t.cond_pattern_switch($view_mode,'result','active','')}"><a href="?view_mode=result&db={$db}">Result</a></li>
					<li class="{$t.cond_pattern_switch($view_mode,'help','active','')}"><a href="?view_mode=help">Help</a></li>
				</ul>
			</div>
		</div>
	</div>
</div>

<div class="container">	
<rt:block name="contents">
	<form rt:ref="true">
		<input type="hidden" name="view_mode" />
		<input type="hidden" name="path" />
		<input type="hidden" name="file" />
		<input type="hidden" name="db" />
		<select rt:param="dblist" name="db" onChange="this.form.submit()" style="width:300px;"></select>
	</form>

	<rt:block name="inner_content">
	</rt:block>		
</rt:block>
</div>

<rt:block name="footer_block">
</rt:block>

</body>
</html>
<rt:extends href="index.html" />

<rt:block name="inner_content">

<h2>{$info['file_path']}</h2>
<table rt:param="{$info['view']}" rt:var="f" rt:counter="cnt">
<tr class="{$f['class']} {$f['class']}_tooltip" data-content="{$t.htmlencode($t.nl2br($f['test_path']))}">
	<td align="right" style="width:50px;"><a name="{$cnt}"></a><a href="#{$cnt}">{$cnt}</a></td>
	<td class="code">{$t.htmlencode($f['value'])}</td>
</tr>
</table>
<div style="height:50px;"></div>

<script type="text/javascript">$('.covered_tooltip').popover({trigger: 'hover',html:true,placement:'top',title:'covered test'});</script>
</rt:block>

<rt:extends href="index.html" />

<rt:block name="inner_content">

<span class="label label-success">Success: {$success}</span>
<span class="label label-important">Failure: {$fail}</span>
<span class="label label-warning">None: {$none}</span>


<h3>Failure</h3>
<table rt:param="{$failure}" rt:var="result" class="table table-striped table-bordered table-condensed">
<thead>
<tr>
	<th>file</th>
	<th>line</th>
	<th>expected</th>
	<th>actual</th>
</tr>
</thead>
<tbody>
<tr>
	<td>{$result['location']['file']}</td>
	<td style="text-align: right;">{$result['location']['line']}</td>	
	<td style="padding: 0;"><pre style="border: 0; widht:100%; height:100%;">{$result['expected']}</pre></td>
	<td style="padding: 0;"><pre style="border: 0; widht:100%; height:100%;">{$result['actual']}</pre></td>
</tr>
</tbody>
</table>
</rt:block>


<rt:extends href="help.html" />

<rt:block name="footer_block">
	<div id="splash" class="modal hide fade" tabindex="-1" style="width:380px;">
		<div class="modal-body" style="padding: 0;">
			<img src="splash.jpg" />
		</div>
	</div>
	
	<script type="text/javascript">
	$('#splash').modal('show');

	setTimeout(function(){
		$('#splash').modal('hide');	
	},200);
	</script>
</rt:block>


<?php
/**
 * @see http://xdebug.org/docs/install
 * @author tokushima
 *
 */
class Testman_Coverage{
	static private $base_dir;
	static private $target_dir;
	static private $start = false;
	static private $savedb;

	static public function has_started(&$vars){
		if(self::$start){
			$vars = array();
			$vars['savedb'] = self::$savedb;
			$vars['base_dir'] = self::$base_dir;
			$vars['target_dir'] = self::$target_dir;
			$vars['current_name'] = Testman_TestRunner::current_name();
			
			return true;
		}
		return false;
	}
	static public function start($savedb,$base_dir,$target_dir){
		if(extension_loaded('xdebug') && self::$start === false){
			xdebug_start_code_coverage();
			self::$start = true;
			self::$savedb = $savedb;
			$exist = (is_file(self::$savedb));

			if(!empty($target_dir) && !is_array($target_dir)) $target_dir = array($target_dir);
			self::$target_dir = $target_dir;
			self::$base_dir = str_replace('\\','/',$base_dir);
			if(substr(self::$base_dir,-1) != '/') self::$base_dir = self::$base_dir.'/';
			
			if($db = new PDO('sqlite:'.self::$savedb)){
				if(!$exist){
					$sql = 'create table coverage('.
							'id integer not null primary key,'.
							'parent_path text,'.
							'src text,'.
							'file_path text not null,'.
							'covered_line text not null,'.
							'ignore_line text,'.
							'covered_len integer,'.
							'active_len integer,'.
							'file_len integer,'.
							'percent integer'.
							')';
					if(false === $db->query($sql)) throw new RuntimeException('failure create coverage table');
					
					$sql = 'create table coverage_info('.
							'id integer not null primary key,'.
							'create_date text,'.
							'test_path text,'.
							'result text'.
							')';
					if(false === $db->query($sql)) throw new RuntimeException('failure create coverage_info table');

					$sql = 'create table coverage_covered('.
							'id integer not null primary key,'.
							'test_path text,'.
							'covered_line text,'.
							'file_path text'.
							')';
					if(false === $db->query($sql)) throw new RuntimeException('failure create coverage_covered table');					
					
					$sql = 'create table coverage_tree('.
							'id integer not null primary key,'.
							'parent_path text not null,'.
							'path text not null'.
							')';
					if(false === $db->query($sql)) throw new RuntimeException('failure create coverage_tree table');
					
					$sql = 'create table coverage_tree_root('.
							'path text not null'.
							')';
					if(false === $db->query($sql)) throw new RuntimeException('failure create coverage_tree_root table');
					
					
					$sql = 'insert into coverage_info(create_date) values(?)';
					$ps = $db->prepare($sql);
					$ps->execute(array(time()));
					
					$sql = 'insert into coverage_tree_root(path) values(?)';
					$ps = $db->prepare($sql);
					foreach(self::$target_dir as $path){
						$path = str_replace('\\','/',$path);
						if(substr($path,-1) == '/') $path = substr($path,0,-1);
						$ps->execute(array(basename($path)));
					}
				}
				register_shutdown_function(array(__CLASS__,'stop'));
			}
		}
	}
	static public function save($restart=true){
		if(self::$start){
			if($db = new PDO('sqlite:'.self::$savedb)){
				$db->beginTransaction();
				
				$get_prepare = function($db,$sql){
					$ps = $db->prepare($sql);
					if($ps === false) throw new LogicException($sql);
					return $ps;
				};
				
				$insert_ps = $get_prepare($db,'insert into coverage(file_path,covered_line,file_len,covered_len,src) values(?,?,?,?,?)');
				$getid_ps = $get_prepare($db,'select id,covered_line from coverage where file_path=?');
				$update_ps = $get_prepare($db,'update coverage set covered_line=?,covered_len=? where id=?');				
				$insert_exe_ps = $get_prepare($db,'insert into coverage_covered(file_path,covered_line,test_path) values(?,?,?)');				

				foreach(xdebug_get_code_coverage() as $file_path => $lines){
					if(
						strpos($file_path,'phar://') !== 0 &&
						strpos($file_path,'/_') === false &&
						is_file($file_path)
					){
						$bool = false;
						
						if(empty(self::$target_dir)){
							$bool = true;
						}else{
							foreach(self::$target_dir as $dir){
								if(strpos($file_path,$dir) === 0){
									$bool = true;
									break;
								}
							}
						}
						if($bool){
							$p = str_replace(self::$base_dir,'',$file_path);

							$pre_id = $pre_line = null;
							$getid_ps->execute(array($p));
							while($resultset = $getid_ps->fetch(PDO::FETCH_ASSOC)){
								$pre_id = $resultset['id'];
								$pre_line = $resultset['covered_line'];
							}
							if(!isset($pre_id)){
								$insert_ps->execute(array(
									$p,
									json_encode(array_keys($lines)),
									sizeof(file($file_path)),
									sizeof($lines),
									file_get_contents($file_path)
								));
							}else{
								$line_array = array_flip(json_decode($pre_line,true));
								foreach($lines as $k => $v) $line_array[$k] = $k;
								$covered_line = array_keys($line_array);

								$update_ps->execute(array(
									json_encode($covered_line),
									sizeof($covered_line),
									$pre_id
								));
							}
							$insert_exe_ps->execute(array(
									$p,
									implode(',',array_keys($lines)),
									Testman_Testrunner::current_name()
							));
						}
					}
				}
				$db->commit();

				xdebug_stop_code_coverage();
				self::$start = false;
				
				if($restart){
					xdebug_start_code_coverage();
					self::$start = true;
				}
			}
		}
	}
	/**
	 * @param string $src
	 * @return array($active_count,$ignore_line,$src,count)
	 */
	static public function parse_line($src){
		if(empty($src)) return array(0,array(),0);
		$ignore_line = array();

		$ignore_line_func = function($c0,$c1,$src){
			$s = substr_count(substr($src,0,$c1),PHP_EOL);
			$e = substr_count($c0,PHP_EOL);
			return range($s+1,$s+1+$e);
		};
		$parse = function($src,&$ignore_line,$preg_pattern) use($ignore_line_func){
			if(preg_match_all($preg_pattern,$src,$m,PREG_OFFSET_CAPTURE)){
				foreach($m[1] as $c){
					$ignore_line = array_merge($ignore_line,$ignore_line_func($c[0],$c[1],$src));
				}
			}
		};
		$parse($src,$ignore_line,"/(\/\*.*?\*\/)/ms");
		$parse($src,$ignore_line,"/^((namespace|use|class)[\040\t].+)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*(final|static|protected|private|public|const)[\040\t].+)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*\/\/.+)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*#.+)$/m");
		$parse($src,$ignore_line,"/^([\s]*<\?php[\040\t]*)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*\?>[\040\t]*)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*try[\040\t]*\{[\040\t]*)$/m");
		$parse($src,$ignore_line,"/^([\040\t\}]*catch[\040\t]*\(.+\).+)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*switch[\040\t]*\(.+\).+)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*\}[\040\t]*else[\040\t]*\{[\040\t]*)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*\{[\040\t]*)$/m");
		$parse($src,$ignore_line,"/^([\040\t]*\}[\040\t]*)$/m");
		$parse($src,$ignore_line,"/^([\040\t\(\)]+)$/m");
		$parse($src,$ignore_line,"/^([\s]*)$/ms");
		$parse($src,$ignore_line,"/(\n)$/s");
		
		$ignore_line = array_unique($ignore_line);
		sort($ignore_line);
		$src_count = substr_count($src,PHP_EOL) + 1;
		return array(($src_count-sizeof($ignore_line)),$ignore_line,$src_count);
	}
	static public function stop(){
		self::save(false);
		$dirlist = array();
		
		if(is_file(self::$savedb) && ($db = new PDO('sqlite:'.self::$savedb))){
			$sql = 'select file_path,id,src,active_len,covered_line,covered_len from coverage order by file_path';
			$ps = $db->query($sql);
			
			$update_sql = 'update coverage set parent_path=?,active_len=?,ignore_line=?,covered_line=?,covered_len=?,percent=? where id=?';
			$update_ps = $db->prepare($update_sql);
			if($update_ps === false) throw new LogicException($update_sql);			
			
			while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
				$percent = 0;
				$dir = dirname($resultset['file_path']);
				list($active_len,$ignore_line,$src_count) = self::parse_line($resultset['src']);
				$covered_lines = array_unique(json_decode($resultset['covered_line'],true));
				foreach($covered_lines as $k => $v){
					if($v === 0 || $v > $src_count) unset($covered_lines[$k]);
				}
				sort($covered_lines);
				
				$covered_dup = sizeof(array_intersect($covered_lines,$ignore_line));
				$covered_len = sizeof($covered_lines) - $covered_dup;
				$percent = ($active_len === 0) ? 100 : (($covered_len === 0) ? 0 : (floor($covered_len / $active_len * 100)));

				$update_ps->execute(array($dir,$active_len,json_encode($ignore_line),json_encode($covered_lines),$covered_len,(int)$percent,$resultset['id']));
				
				while(strpos($dir,'/') !== false){
					$dirlist[$dir] = dirname($dir);
					$dir = dirname($dir);
				}
			}
			$cnt_sql = 'select count(path) as cnt from coverage_tree where parent_path=? and path=?';
			$cnt_ps = $db->prepare($cnt_sql);
			if($cnt_ps === false) throw new LogicException($cnt_sql);

			$insert_sql = 'insert into coverage_tree(parent_path,path) values(?,?)';
			$insert_ps = $db->prepare($insert_sql);
			if($insert_ps === false) throw new LogicException($insert_sql);
				
			foreach($dirlist as $dir => $parent_dir){
				$cnt_ps->execute(array($parent_dir,$dir));
				$resultset = $cnt_ps->fetch(PDO::FETCH_ASSOC);
				if((int)$resultset['cnt'] === 0){
					$insert_ps->execute(array($parent_dir,$dir));
				}
			}			
			$sql = 'update coverage_info set result=?, test_path=?';
			$ps = $db->prepare($sql);
			$ps->execute(array(
					json_encode(Testman_TestRunner::get()),
					json_encode(Testman_TestRunner::search_path())
			));
		}
	}
	static public function test_result($savedb){
		if(is_file($savedb) && ($db = new PDO('sqlite:'.$savedb))){
			$sql = 'select result,test_path from coverage_info';
			$ps = $db->prepare($sql);
			$ps->execute();

			$success = $fail = $none = 0;
			$failure = array();
			
			while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
				$result = json_decode($resultset['result'],true);
				$test_path = json_decode($resultset['test_path'],true);
				if(!is_array($result)) $result = array();
				if(!is_array($test_path)) $test_path = array();
				rsort($test_path);
				
				foreach($result as $file => $f){
					foreach($f as $class => $c){
						foreach($c as $method => $m){
							foreach($m as $line => $r){
								foreach($r as $l){
									$info = array_shift($l);
									foreach($test_path as $p) $file = str_replace(dirname($p),'',$file);
									$name_var = array('class'=>$class,'file'=>$file,'method'=>$method,'line'=>$line);
									
									switch(sizeof($l)){
										case 0: // success
											$success++;
											break;
										case 1: // none
											$none++;
											break;
										case 2: // fail
											$fail++;
											$result_a = $result_b = null;
											
											ob_start();
												var_dump($l[0]);
												$result_a .= ob_get_contents();
											ob_end_clean();
		
											ob_start();
												var_dump($l[1]);
												$result_b .= ob_get_contents();
											ob_end_clean();
											
											$failure[] = array('location'=>$name_var,'expected'=>$result_a,'actual'=>$result_b);
											break;
										case 4: // exception
											$fail++;										
											$failure[] = array('location'=>$name_var,'expected'=>$l[0],'actual'=>$l[2].':'.$l[3]);
											break;
									}
								}
							}
						}
					}
				}
				return array($success,$fail,$none,$failure);
			}
		}
		return array(0,0,0,array());
	}
	static public function dir_list($savedb,$dir=null){
		$result_dir = $result_file = $avg = array();
		$avg = array('avg'=>0,'uncovered'=>100,'covered'=>0);
		$parent_path = null;

		if(is_file($savedb) && ($db = new PDO('sqlite:'.$savedb))){		
			if(empty($dir)){
				$sql = 'select path from coverage_tree_root order by path';
				$ps = $db->prepare($sql);
				$ps->execute();

				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){					
					$result_dir[] = $resultset['path'];
				}
				
				$avg_sql = 'select avg(percent) as percent_avg from coverage';
				$avg_ps = $db->prepare($avg_sql);
				$avg_ps->execute();
					
				if($resultset = $avg_ps->fetch(PDO::FETCH_ASSOC)){
					$avg['avg'] = floor($resultset['percent_avg']);
					$avg['uncovered'] = 100 - $resultset['percent_avg'];
					$avg['covered'] = 100 - $avg['uncovered'];
				}
			}else{
				$sql = 'select parent_path from coverage_tree where path=?';
				$ps = $db->prepare($sql);
				$ps->execute(array($dir));
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$parent_path = $resultset['parent_path'];
				}
				
				$sql = 'select path from coverage_tree where parent_path=? order by path';
				$ps = $db->prepare($sql);
				$ps->execute(array($dir));
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$result_dir[] = $resultset['path'];
				}
				
				$sql = 'select file_path,file_len,covered_len,active_len,percent from coverage where parent_path=? order by file_path';
				$ps = $db->prepare($sql);
				$ps->execute(array($dir));
				
				while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
					$resultset['uncovered'] = 100 - $resultset['percent'];
					$resultset['covered'] = 100 - $resultset['uncovered'];					
					$result_file[] = $resultset;
				}

				$avg_sql = 'select avg(percent) as percent_avg from coverage where file_path like(?)';
				$avg_ps = $db->prepare($avg_sql);
				$avg_ps->execute(array($dir.'/%'));
					
				if($resultset = $avg_ps->fetch(PDO::FETCH_ASSOC)){
					$avg['avg'] = floor($resultset['percent_avg']);
					$avg['uncovered'] = 100 - $resultset['percent_avg'];
					$avg['covered'] = 100 - $avg['uncovered'];
				}
			}
		}
		return array($result_dir,$result_file,$parent_path,$avg);
	}
	
	static public function all_file_list($savedb){
		$result_file = array();
		$avg = array('avg'=>0,'uncovered'=>100,'covered'=>0);
	
		if(is_file($savedb) && ($db = new PDO('sqlite:'.$savedb))){
			$sql = 'select file_path,file_len,covered_len,active_len,percent from coverage order by file_path';
			$ps = $db->prepare($sql);
			$ps->execute();

			while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
				$resultset['uncovered'] = 100 - $resultset['percent'];
				$resultset['covered'] = 100 - $resultset['uncovered'];
				$result_file[] = $resultset;
			}
			
			$sql = 'select avg(percent) as percent_avg from coverage';
			$ps = $db->prepare($sql);
			$ps->execute();
			
			if($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
				$avg['avg'] = floor($resultset['percent_avg']);
				$avg['uncovered'] = 100 - $resultset['percent_avg'];
				$avg['covered'] = 100 - $avg['uncovered'];
			}
		}
		return array($result_file,$avg);
	}	
	
	static public function file($savedb,$file_path){
		$result = array();

		if(is_file($savedb) && ($db = new PDO('sqlite:'.$savedb))){
			$covered_line = array();
			$sql = 'select test_path,covered_line from coverage_covered where file_path=?';
			$ps = $db->prepare($sql);
			if($ps === false) throw new LogicException($sql);
			$ps->execute(array($file_path));
			
			while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
				foreach(explode(',',$resultset['covered_line']) as $line){
					if(!isset($covered_line[$line])) $covered_line[$line] = array();
					$covered_line[$line][$resultset['test_path']] = $resultset['test_path'];
				}
			}

			$sql = 'select file_path,covered_line,file_len,covered_len,ignore_line,src from coverage where file_path=?';
			$ps = $db->prepare($sql);
			if($ps === false) throw new LogicException($sql);
			$ps->execute(array($file_path));
			
			while($resultset = $ps->fetch(PDO::FETCH_ASSOC)){
				$src_lines = explode(PHP_EOL,$resultset['src']);
				$covered_lines = array_flip(json_decode($resultset['covered_line'],true));
				$ignore_lines = array_flip(json_decode($resultset['ignore_line'],true));
				$view_src_lines = array();

				foreach($src_lines as $k => $v){
					$line_num = $k + 1;
					$class = isset($ignore_lines[$line_num]) ? 'ignore' : (isset($covered_lines[$line_num]) ? 'covered' : 'uncovered');
					$test_path = ($class == 'ignore') ? array() : (isset($covered_line[$line_num]) ? $covered_line[$line_num] : array());
					$view_src_lines[$k] = array('value'=>$v,'class'=>$class,'test_path'=>$test_path);
				}
				$resultset['view'] = $view_src_lines;
				return $resultset;
			}
		}
		throw new InvalidArgumentException($file_path.' not found');
	}
}
?><?php
if(extension_loaded('xdebug')){
	$coverage_vars = isset($_POST['_testman_coverage_vars_']) ? $_POST['_testman_coverage_vars_'] : 
						(isset($_GET['_testman_coverage_vars_']) ? $_GET['_testman_coverage_vars_'] : array());	
	if(isset($_POST['_testman_coverage_vars_'])) unset($_POST['_testman_coverage_vars_']);
	if(isset($_GET['_testman_coverage_vars_'])) unset($_GET['_testman_coverage_vars_']);

	if(!empty($coverage_vars) && isset($coverage_vars['savedb']) && is_file($coverage_vars['savedb'])){
		register_shutdown_function(function() use($coverage_vars){
			if($db = new PDO('sqlite:'.$coverage_vars['savedb'])){
				$get_prepare = function($db,$sql){
					$ps = $db->prepare($sql);
					if($ps === false) throw new LogicException($sql);
					return $ps;
				};
				$db->beginTransaction();
				$getid_ps = $get_prepare($db,'select id,covered_line from coverage where file_path=?');
				$insert_ps = $get_prepare($db,'insert into coverage(file_path,covered_line,file_len,covered_len,src) values(?,?,?,?,?)');
				$update_ps = $get_prepare($db,'update coverage set covered_line=?,covered_len=? where id=?');
				$insert_exe_ps = $get_prepare($db,'insert into coverage_covered(file_path,covered_line,test_path) values(?,?,?)');
				
				foreach(xdebug_get_code_coverage() as $filepath => $lines){
					if(strpos($filepath,'phar://') !== 0 && strpos($filepath,'/_') === false && is_file($filepath)){
						$bool = empty($coverage_vars['target_dir']);
						if(!$bool){
							foreach($coverage_vars['target_dir'] as $dir){
								if(strpos($filepath,$dir) === 0){
									$bool = true;
									break;
								}
							}
						}						
						if($bool){
							$p = str_replace($coverage_vars['base_dir'],'',$filepath);
							$pre_id = $pre_line = null;

							$getid_ps->execute(array($p));
							
							while($resultset = $getid_ps->fetch(PDO::FETCH_ASSOC)){
								$pre_id = $resultset['id'];
								$pre_line = $resultset['covered_line'];
							}
							if(!isset($pre_id)){
								$insert_ps->execute(array($p,json_encode(array_keys($lines)),sizeof(file($filepath)),sizeof($lines),file_get_contents($filepath)));
							}else{
								$line_array = array_flip(json_decode($pre_line,true));
								foreach($lines as $k => $v) $line_array[$k] = $k;
								$covered_line = array_keys($line_array);
								
								$update_ps->execute(array(json_encode($covered_line),sizeof($covered_line),$pre_id));
							}
							$insert_exe_ps->execute(array(
									$p,
									implode(',',array_keys($lines)),
									$coverage_vars['current_name']
							));
						}
					}
				}
				$db->commit();
				xdebug_stop_code_coverage();
			}
		});
		xdebug_start_code_coverage();
	}
}
?><?php
/**
 * ファイル
 * @author tokushima
 */
class Testman_File{
	private $path;
	private $value;
	private $mime;
	private $directory;
	private $name;
	private $oname;
	private $ext;

	public function __construct($path=null,$value=null){
		$this->path	= str_replace("\\",'/',$path);
		$this->value = $value;
		$this->parse_path();
	}
	public function __toString(){
		return $this->path;
	}
	/**
	 * ファイルパスを取得
	 * @return string
	 */
	public function path(){
		return $this->path;
	}
	/**
	 * ファイル名を取得
	 * @return string
	 */
	public function name(){
		return $this->name;
	}
	/**
	 * mimeを取得
	 * @return string
	 */
	public function mime(){
		return $this->mime;
	}
	/**
	 * 内容を取得
	 * @return string
	 */
	public function value(){
		if($this->value !== null) return $this->value;
		if(is_file($this->path)) return file_get_contents($this->path);
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$this->path));
	}
	/**
	 * ディレクトリを取得
	 * @return string
	 */
	public function directory(){
		return $this->directory;
	}


	/**
	 * 指定の拡張子が存在するか
	 * @param string $ext 拡張子(.なし)
	 * @return boolean
	 */
	public function is_ext($ext){
		return ('.'.strtolower($ext) === strtolower($this->ext));
	}
	/**
	 * ファイルが存在するか
	 * @return boolean
	 */
	public function exist(){
		return is_file($this->path);
	}
	/**
	 * エラーがあるか
	 * @return boolean
	 */
	public function is_error(){
		return (intval($this->error) > 0);
	}
	/**
	 * 標準出力に出力する
	 */
	public function output(){
		if(empty($this->value) && @is_file($this->path)){
			$fp = fopen($this->path,'rb');
			while(!feof($fp)){
				echo(fread($fp,8192));
				flush();
			}
			fclose($fp);
		}else{
			print($this->value);
		}
		exit;
	}
	/**
	 * 更新時間を取得
	 * @return integer
	 */
	public function update(){
		return (@is_file($this->path)) ? @filemtime($this->path) : time();
	}
	/**
	 * 内容のサイズを取得
	 * @return integer
	 */
	public function size(){
		return (@is_file($this->path)) ? @filesize($this->path) : strlen($this->value);
	}
	private function parse_path(){
		$path = str_replace("\\",'/',$this->path);
		if(preg_match("/^(.+[\/]){0,1}([^\/]+)$/",$path,$match)){
			$this->directory = empty($match[1]) ? "./" : $match[1];
			$this->name = $match[2];
		}
		if(false !== ($p = strrpos($this->name,'.'))){
			$this->ext = '.'.substr($this->name,$p+1);
			$filename = substr($this->name,0,$p);
		}
		$this->oname = @basename($this->name,$this->ext);

		if(empty($this->mime)){
			$ext = strtolower(substr($this->ext,1));
			switch($ext){
				case 'jpg':
				case 'jpeg': $ext = 'jpeg';
				case 'png':
				case 'gif':
				case 'bmp':
				case 'tiff': $this->mime = 'image/'.$ext; break;
				case 'css': $this->mime = 'text/css'; break;
				case 'txt': $this->mime = 'text/plain'; break;
				case 'html': $this->mime = 'text/html'; break;
				case 'csv': $this->mime = 'text/csv'; break;
				case 'xml': $this->mime = 'application/xml'; break;
				case 'js': $this->mime = 'text/javascript'; break;
				case 'flv':
				case 'swf': $this->mime = 'application/x-shockwave-flash'; break;
				case '3gp': $this->mime = 'video/3gpp'; break;
				case 'gz':
				case 'tgz':
				case 'tar':
				case 'gz':  $this->mime = 'application/x-compress'; break;
				default:
					if(empty($this->mime)) $this->mime = 'application/octet-stream';
			}
		}
	}
	/**
	 * フォルダを作成する
	 * @param string $source 作成するフォルダパス
	 * @param oct $permission
	 */
	public static function mkdir($source,$permission=0775){
		$bool = true;
		if(!is_dir($source)){
			try{
				$list = explode('/',str_replace('\\','/',$source));
				$dir = '';
				foreach($list as $d){
					$dir = $dir.$d.'/';
					if(!is_dir($dir)){
						$bool = mkdir($dir);
						if(!$bool) return $bool;
						chmod($dir,$permission);
					}
				}
			}catch(ErrorException $e){
				throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
			}
		}
		return $bool;
	}
	/**
	 * 移動
	 * @param string $source 移動もとのファイルパス
	 * @param string $dest 移動後のファイルパス
	 */
	static public function mv($source,$dest){
		if(is_file($source) || is_dir($source)){
			self::mkdir(dirname($dest));
			return rename($source,$dest);
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * 最終更新時間を取得
	 * @param string $filename ファイルパス
	 * @param boolean $clearstatcache ファイルのステータスのキャッシュをクリアするか
	 * @return integer
	 */
	static public function last_update($filename,$clearstatcache=false){
		if($clearstatcache) clearstatcache();
		if(is_dir($filename)){
			$last_update = null;
			foreach(self::ls($filename,true) as $file){
				if($last_update < $file->update()) $last_update = $file->update();
			}
			return $last_update;
		}
		return (is_readable($filename) && is_file($filename)) ? filemtime($filename) : null;
	}
	/**
	 * 削除
	 * $sourceがフォルダで$inc_selfがfalseの場合は$sourceフォルダ以下のみ削除
	 * @param string $source 削除するパス
	 * @param boolean $inc_self $sourceも削除するか
	 * @return boolean
	 */
	static public function rm($source,$inc_self=true){
		if(!is_dir($source) && !is_file($source)) return true;
		if(!$inc_self){
			foreach(self::dir($source) as $d) self::rm($d);
			foreach(self::ls($source) as $f) self::rm($f);
			return true;
		}
		if(is_writable($source)){
			if(is_dir($source)){
				if($handle = opendir($source)){
					$list = array();
					while($pointer = readdir($handle)){
						if($pointer != '.' && $pointer != '..') $list[] = sprintf('%s/%s',$source,$pointer);
					}
					closedir($handle);
					foreach($list as $path){
						if(!self::rm($path)) return false;
					}
				}
				if(rmdir($source)){
					clearstatcache();
					return true;
				}
			}else if(is_file($source) && unlink($source)){
				clearstatcache();
				return true;
			}
		}
		throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
	}
	/**
	 * コピー
	 * $sourceがフォルダの場合はそれ以下もコピーする
	 * @param string $source コピー元のファイルパス
	 * @param string $dest コピー先のファイルパス
	 */
	static public function copy($source,$dest){
		if(!is_dir($source) && !is_file($source)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$source));
		if(is_dir($source)){
			$bool = true;
			if($handle = opendir($source)){
				while($pointer = readdir($handle)){
					if($pointer != '.' && $pointer != '..'){
						$srcname = sprintf('%s/%s',$source,$pointer);
						$destname = sprintf('%s/%s',$dest,$pointer);
						if(false === ($bool = self::copy($srcname,$destname))) break;
					}
				}
				closedir($handle);
			}
			return $bool;
		}else{
			$dest = (is_dir($dest))	? $dest.basename($source) : $dest;
			if(is_writable(dirname($dest))){
				copy($source,$dest);
			}
			return is_file($dest);
		}
	}
	/**
	 * ファイルから取得する
	 * @param string $filename ファイルパス
	 * @return string
	 */
	static public function read($filename){
		if(!is_readable($filename) || !is_file($filename)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		return file_get_contents($filename);
	}
	/**
	 * ファイルに書き出す
	 * @param string $filename ファイルパス
	 * @param string $src 内容
	 */
	static public function write($filename,$src=null,$lock=true){
		if(empty($filename)) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		$b = is_file($filename);
		self::mkdir(dirname($filename));
		if(false === file_put_contents($filename,(string)$src,($lock ? LOCK_EX : 0))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
		if(!$b) chmod($filename,0777);
	}
	/**
	 * ファイルに追記する
	 * @param string $filename ファイルパス
	 * @param string $src 追加する内容
	 * @param integer $dir_permission モード　8進数(0644)
	 */
	static public function append($filename,$src=null,$lock=true){
		self::mkdir(dirname($filename));
		if(false === file_put_contents($filename,(string)$src,FILE_APPEND|(($lock) ? LOCK_EX : 0))) throw new InvalidArgumentException(sprintf('permission denied `%s`',$filename));
	}
	static private function parse_filename($filename){
		$filename = preg_replace("/[\/]+/",'/',str_replace("\\",'/',trim($filename)));
		return (substr($filename,-1) == '/') ? substr($filename,0,-1) : $filename;
	}
	/**
	 * ファイルパスからディレクトリ名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function dirname($path){
		$dir_name = dirname(str_replace("\\",'/',$path));
		$len = strlen($dir_name);
		return ($len === 1 || ($len === 2 && $dir_name[1] === ':')) ? null : $dir_name;
	}
	/**
	 * フルパスからファイル名部分を取得
	 * @param string $path ファイルパス
	 * @return string
	 */
	static public function basename($path){
		$basename = basename($path);
		$len = strlen($basename);
		return ($len === 1 || ($len === 2 && $basename[1] === ':')) ? null : $basename;
	}
	/**
	 * ディレクトリでユニークなファイル名を返す
	 * @param $dir
	 * @param $prefix
	 * @return string
	 */
	static public function temp_path($dir,$prefix=null){
		if(is_dir($dir)){
			if(substr(str_replace("\\",'/',$dir),-1) != '/') $dir .= '/';
			while(is_file($dir.($path = uniqid($prefix,true))));
			return $path;
		}
		return uniqid($prefix,true);
	}
}
?><?php
/**
 * HTTP関連処理
 * @author tokushima
 * @see http://jp2.php.net/manual/ja/context.ssl.php
 */
class Testman_Http{
	private $user;
	private $password;

	private $agent;
	private $timeout = 30;
	private $status_redirect = true;

	private $body;
	private $head;
	private $url;
	private $status;
	private $cmd;

	private $raw;
	protected $vars = array();
	protected $header = array();

	private $cookie = array();
	private $form = array();

	private $api_url;
	private $api_key;
	private $api_key_name = 'api_key';

	public function __construct($agent=null,$timeout=30,$status_redirect=true){
		$this->agent = $agent;
		$this->timeout = (int)$timeout;
		$this->status_redirect = (boolean)$status_redirect;
	}
	public function __toString(){
		return $this->body();
	}
	/**
	 * 自動でリダイレクトするか
	 * @param boolean $bool
	 */
	public function status_redirect($bool){
		$this->status_redirect = (boolean)$bool;
		return $this;
	}
	/**
	 * APIのベースURLを設定する
	 * @param string $url
	 * @return $this
	 */
	public function api_url($url){
		$this->api_url = $url;
		return $this;
	}
	/**
	 * APIへ送信するキーを設定する
	 * @param string $key
	 * @param string $keyname
	 * @return $this
	 */
	public function api_key($key,$keyname='api_key'){
		$this->api_key = $key;
		$this->api_key_name = $keyname;
		return $this;
	}
	/**
	 * 送信するRAWデータをセットする
	 * @param string $raw
	 * @return $this
	 */
	public function raw($raw){
		$this->raw = $raw;
		return $this;
	}
	/**
	 * 結果のHTTPステータスを取得する
	 * @return int
	 */
	public function status(){
		return $this->status;
	}
	/**
	 * 結果のBODYを取得する
	 * @return string
	 */
	public function body(){
		return (empty($this->body) ? null : $this->body);
	}
	/**
	 * 結果のHEADを取得する
	 * @return string
	 */
	public function head(){
		return $this->head;
	}
	/**
	 * 送信したURLを取得する
	 * @return string
	 */
	public function url(){
		return $this->url;
	}
	/**
	 * 送信した生データを取得する
	 * @return string
	 */
	public function cmd(){
		return $this->cmd;
	}
	/**
	 * 送信する値を取得する
	 * @return mixed[]
	 */
	public function get_vars(){
		return $this->vars;
	}
	/**
	 * 送信する値を設定する
	 * @param string $key
	 * @param mixed $value
	 */
	public function vars($key,$value){
		$this->vars[$key] = $value;
	}
	/**
	 * 送信する値(ファイル)を設定する
	 * @param string $key
	 * @param string $filename
	 * @param string $value
	 */
	public function file_vars($key,$filename,$value=null){
		$this->vars[$key] = new Testman_File($filename,$value);
	}
	/**
	 * 送信するHEADを設定する
	 * @param string $key
	 * @param string $value
	 */
	public function header($key,$value){
		$this->header[$key] = $value;
	}
	/**
	 * URLが有効かを調べる
	 * @param string $url 確認するURL
	 * @return boolean
	 */
	static public function is_url($url){
		try{
			$self = new self();
			$result = $self->request($url,'HEAD',array(),array(),null,false);
			return ($result->status === 200);
		}catch(Exception $e){}
		return false;
	}
	/**
	 * URLのステータスを確認する
	 * @param string $url 確認するURL
	 * @return integer
	 */
	static public function request_status($url){
		try{
			$self = new self();
			$result = $self->request($url,'HEAD',array(),array(),null,false);
			return $result->status;
		}catch(Exception $e){}
		return 404;
	}
	/**
	 * ヘッダ情報をハッシュで取得する
	 * @return string{}
	 */
	public function explode_head(){
		$result = array();
		foreach(explode("\n",$this->head) as $h){
			if(preg_match("/^(.+?):(.+)$/",$h,$match)) $result[trim($match[1])] = trim($match[2]);
		}
		return $result;
	}
	/**
	 * 配列、またはオブジェクトから値を設定する
	 * @param mixed $array
	 * @throws InvalidArgumentException
	 * @return $this
	 */
	public function cp($array){
		if(is_array($array)){
			foreach($array as $k => $v) $this->vars[$k] = $v;
		}else if(is_object($array)){
			if(in_array('Traversable',class_implements($array))){
				foreach($array as $k => $v) $this->vars[$k] = $v;
			}else{
				foreach(get_object_vars($array) as $k => $v) $this->vars[$k] = $v;
			}
		}else{
			throw new InvalidArgumentException('must be an of array');
		}
		return $this;
	}
	private function build_url($url){
		if($this->api_key !== null) $this->vars($this->api_key_name,$this->api_key);
		if($this->api_url !== null) return Testman_Path::absolute($this->api_url,(substr($url,0,1) == '/') ? substr($url,1) : $url);
		return $url;
	}
	/**
	 * getでアクセスする
	 * @param string $url アクセスするURL
	 * @param boolean $form formタグの解析を行うか
	 * @return $this
	 */
	public function do_get($url=null,$form=true){
		return $this->browse($this->build_url($url),'GET',$form);
	}
	/**
	 * postでアクセスする
	 * @param string $url アクセスするURL
	 * @param boolean $form formタグの解析を行うか
	 * @return $this
	 */
	public function do_post($url=null,$form=true){
		return $this->browse($this->build_url($url),'POST',$form);
	}
	/**
	 * putでアクセスする
	 * @param string $url アクセスするURL
	 * @return $this
	 */
	public function do_put($url=null){
		return $this->browse($this->build_url($url),'PUT',false);
	}
	/**
	 * deleteでアクセスする
	 * @param string $url アクセスするURL
	 * @return $this
	 */
	public function do_delete($url=null){
		return $this->browse($this->build_url($url),'DELETE',false);
	}
	/**
	 * ダウンロードする
	 *
	 * @param string $url アクセスするURL
	 * @param string $download_path ダウンロード先のファイルパス
	 * @return $this
	 */
	public function do_download($url=null,$download_path){
		return $this->browse($this->build_url($url),'GET',false,$download_path);
	}
	/**
	 * POSTでダウンロードする
	 *
	 * @param string $url アクセスするURL
	 * @param string $download_path ダウンロード先のファイルパス
	 * @return $this
	 */
	public function do_post_download($url=null,$download_path){
		return $this->browse($this->build_url($url),'POST',false,$download_path);
	}
	/**
	 * HEADでアクセスする formの取得はしない
	 * @param string $url アクセスするURL
	 * @return $this
	 */
	public function do_head($url=null){
		return $this->browse($this->build_url($url),'HEAD',false);
	}
	/**
	 * 指定の時間から更新されているか
	 * @param string $url アクセスするURL
	 * @param integer $time 基点となる時間
	 * @return string
	 */
	public function do_modified($url,$time){
		$this->header('If-Modified-Since',date('r',$time));
		return $this->browse($this->build_url($url),'GET',false)->body();
	}
	/**
	 * リダイレクトする
	 * @param string $url アクセスするURL
	 */
	public function do_redirect($url=null){
		$url = $this->build_url($url);
		$url = (strpos($url,'?') === false) ? $url.'?' : $url.'&';
		header('Location: '.$url.Testman_Http_Query::get($this->vars,null,true));
		exit;
	}
	/**
	 * Basic認証
	 * @param string $user ユーザ名
	 * @param string $password パスワード
	 */
	public function basic($user,$password){
		$this->user = $user;
		$this->password = $password;
		return $this;
	}
	private function browse($url,$method,$form=true,$download_path=null){
		$cookies = '';
		$variables = '';
		$headers = $this->header;
		$cookie_base_domain = preg_replace("/^[\w]+:\/\/(.+)$/","\\1",$url);

		foreach($this->cookie as $domain => $cookie_value){
			if(strpos($cookie_base_domain,$domain) === 0 || strpos($cookie_base_domain,(($domain[0] == '.') ? $domain : '.'.$domain)) !== false){
				foreach($cookie_value as $name => $value){
					if(!$value['secure'] || ($value['secure'] && substr($url,0,8) == 'https://')) $cookies .= sprintf("%s=%s; ",$name,$value['value']);
				}
			}
		}
		if(!empty($cookies)) $headers["Cookie"] = $cookies;
		if(!empty($this->user)){
			if(preg_match("/^([\w]+:\/\/)(.+)$/",$url,$match)){
				$url = $match[1].$this->user.':'.$this->password.'@'.$match[2];
			}else{
				$url = 'http://'.$this->user.':'.$this->password.'@'.$url;
			}
		}
		if($this->raw !== null) $headers['rawdata'] = $this->raw;
		$result = $this->request($url,$method,$headers,$this->vars,$download_path,false);
		$this->cmd = $result->cmd;
		$this->head = $result->head;
		$this->url = $result->url;
		$this->status = $result->status;
		$this->body = $result->body;
		$this->form = array();

		if(preg_match_all("/Set-Cookie:[\s]*(.+)/i",$this->head,$match)){
			$unsetcookie = $setcookie = array();
			foreach($match[1] as $cookies){
				$cookie_name = $cookie_value = $cookie_domain = $cookie_path = $cookie_expires = null;
				$cookie_domain = $cookie_base_domain;
				$cookie_path = '/';
				$secure = false;

				foreach(explode(';',$cookies) as $cookie){
					$cookie = trim($cookie);
					if(strpos($cookie,'=') !== false){
						list($name,$value) = explode('=',$cookie,2);
						$name = trim($name);
						$value = trim($value);
						switch(strtolower($name)){
							case 'expires': $cookie_expires = ctype_digit($value) ? (int)$value : strtotime($value); break;
							case 'domain': $cookie_domain = preg_replace("/^[\w]+:\/\/(.+)$/","\\1",$value); break;
							case 'path': $cookie_path = $value; break;
							default:
								$cookie_name = $name;
								$cookie_value = $value;
						}
					}else if(strtolower($cookie) == 'secure'){
						$secure = true;
					}
				}
				$cookie_domain = substr(Testman_Path::absolute('http://'.$cookie_domain,$cookie_path),7);
				if($cookie_expires !== null && $cookie_expires < time()){
					if(isset($this->cookie[$cookie_domain][$cookie_name])) unset($this->cookie[$cookie_domain][$cookie_name]);
				}else{
					$this->cookie[$cookie_domain][$cookie_name] = array('value'=>$cookie_value,'expires'=>$cookie_expires,'secure'=>$secure);
				}
			}
		}
		$this->vars = array();
		if($this->status_redirect){
			if(isset($result->redirect)) return $this->browse($result->redirect,'GET',$form,$download_path);
			if(Testman_Xml::set($tag,$result->body,'head')){
				foreach($tag->in('meta') as $meta){
					if(strtolower($meta->in_attr('http-equiv')) == 'refresh'){
						if(preg_match("/^[\d]+;url=(.+)$/i",$meta->in_attr('content'),$refresh)){
							$this->vars = array();
							return $this->browse(Testman_Path::absolute(dirname($url),$refresh[1]),'GET',$form,$download_path);
						}
					}
				}
			}
		}
		if($form) $this->parse_form();
		return $this;
	}
	private function parse_form(){
		$tag = new Testman_Xml('<:>'.$this->body.'</:>',':');
		foreach($tag->in('form') as $key => $formtag){
			$form = new \stdClass();
			$form->name = $formtag->in_attr('name',$formtag->in_attr('id',$key));
			$form->action = Testman_Path::absolute($this->url,$formtag->in_attr('action',$this->url));
			$form->method = strtolower($formtag->in_attr('method','get'));
			$form->multiple = false;
			$form->element = array();

			foreach($formtag->in('input') as $count => $input){
				$obj = new \stdClass();
				$obj->name = $input->in_attr('name',$input->in_attr('id','input_'.$count));
				$obj->type = strtolower($input->in_attr('type','text'));
				$obj->value = self::htmldecode($input->in_attr('value'));
				$obj->selected = ('selected' === strtolower($input->in_attr('checked',$input->in_attr('checked'))));
				$obj->multiple = false;
				$form->element[] = $obj;
			}
			foreach($formtag->in('textarea') as $count => $input){
				$obj = new \stdClass();
				$obj->name = $input->in_attr('name',$input->in_attr('id','textarea_'.$count));
				$obj->type = 'textarea';
				$obj->value = self::htmldecode($input->value());
				$obj->selected = true;
				$obj->multiple = false;
				$form->element[] = $obj;
			}
			foreach($formtag->in('select') as $count => $input){
				$obj = new \stdClass();
				$obj->name = $input->in_attr('name',$input->in_attr('id','select_'.$count));
				$obj->type = 'select';
				$obj->value = array();
				$obj->selected = true;
				$obj->multiple = ('multiple' == strtolower($input->param('multiple',$input->attr('multiple'))));

				foreach($input->in('option') as $count => $option){
					$op = new \stdClass();
					$op->value = self::htmldecode($option->in_attr('value',$option->value()));
					$op->selected = ('selected' == strtolower($option->in_attr('selected',$option->in_attr('selected'))));
					$obj->value[] = $op;
				}
				$form->element[] = $obj;
			}
			$this->form[] = $form;
		}
	}
	/**
	 * formをsubmitする
	 * @param string $form FORMタグの名前、または順番
	 * @param string $submit 実行するINPUTタグ(type=submit)の名前
	 * @return $this
	 */
	public function submit($form=0,$submit=null){
		foreach($this->form as $key => $f){
			if($f->name === $form || $key === $form){
				$form = $key;
				break;
			}
		}
		if(isset($this->form[$form])){
			$inputcount = 0;
			$onsubmit = ($submit === null);

			foreach($this->form[$form]->element as $element){
				switch($element->type){
					case 'hidden':
					case 'textarea':
						if(!array_key_exists($element->name,$this->vars)){
							$this->vars($element->name,$element->value);
						}
						break;
					case 'text':
					case 'password':
						$inputcount++;
						if(!array_key_exists($element->name,$this->vars)) $this->vars($element->name,$element->value); break;
						break;
					case 'checkbox':
					case 'radio':
						if($element->selected !== false){
							if(!array_key_exists($element->name,$this->vars)) $this->vars($element->name,$element->value);
						}
						break;
					case 'submit':
					case 'image':
						if(($submit === null && $onsubmit === false) || $submit == $element->name){
							$onsubmit = true;
							if(!array_key_exists($element->name,$this->vars)) $this->vars($element->name,$element->value);
							break;
						}
						break;
					case 'select':
						if(!array_key_exists($element->name,$this->vars)){
							if($element->multiple){
								$list = array();
								foreach($element->value as $option){
									if($option->selected) $list[] = $option->value;
								}
								$this->vars($element->name,$list);
							}else{
								foreach($element->value as $option){
									if($option->selected){
										$this->vars($element->name,$option->value);
									}
								}
							}
						}
						break;
					case "button":
						break;
				}
			}
			if($onsubmit || $inputcount == 1){
				return ($this->form[$form]->method == 'post') ?
							$this->browse($this->form[$form]->action,'POST') :
							$this->browse($this->form[$form]->action,'GET');
			}
		}
		return $this;
	}
	private function request($url,$method,array $header=array(),array $vars=array(),$download_path=null,$status_redirect=true){
		if(Testman_Coverage::has_started($coverage_vars)) $vars['_testman_coverage_vars_'] = $coverage_vars;
		
		$url = (string)$url;
		$result = (object)array('url'=>$url,'status'=>200,'head'=>null,'redirect'=>null,'body'=>null,'encode'=>null,'cmd'=>null);
		$raw = isset($header['rawdata']) ? $header['rawdata'] : null;
		if(isset($header['rawdata'])) unset($header['rawdata']);
		$header['Content-Type'] = 'application/x-www-form-urlencoded';

		if(!isset($raw) && !empty($vars)){
			if($method == 'GET'){
				$url = (strpos($url,'?') === false) ? $url.'?' : $url.'&';
				$url .= Testman_Http_Query::get($vars,null,true);
			}else{
				$query_vars = array(array(),array());
				foreach(Testman_Http_Query::expand_vars($tmp,$vars,null,false) as $v){
					$query_vars[is_string($v[1]) ? 0 : 1][] = $v;
				}
				if(empty($query_vars[1])){
					$raw = Testman_Http_Query::get($vars,null,true);
				}else{
					$boundary = '-----------------'.md5(microtime());
					$header['Content-Type'] = 'multipart/form-data;  boundary='.$boundary;
					$raws = array();

					foreach($query_vars[0] as $v){
						$raws[] = sprintf('Content-Disposition: form-data; name="%s"',$v[0])
									."\r\n\r\n"
									.$v[1]
									."\r\n";
					}
					foreach($query_vars[1] as $v){
						$raws[] = sprintf('Content-Disposition: form-data; name="%s"; filename="%s"',$v[0],$v[1]->name())
									."\r\n".sprintf('Content-Type: %s',$v[1]->mime())
									."\r\n".sprintf('Content-Transfer-Encoding: %s',"binary")
									."\r\n\r\n"
									.$v[1]->value()
									."\r\n";
					}
					$raw = "--".$boundary."\r\n".implode("--".$boundary."\r\n",$raws)."--".$boundary."--\r\n"."\r\n";
				}
			}
		}
		$ulist = parse_url(preg_match("/^([\w]+:\/\/)(.+?):(.+)(@.+)$/",$url,$m) ? ($m[1].urlencode($m[2]).":".urlencode($m[3]).$m[4]) : $url);
		$ssl = (isset($ulist['scheme']) && ($ulist['scheme'] == 'ssl' || $ulist['scheme'] == 'https'));
		$port = isset($ulist['port']) ? $ulist['port'] : null;
		$errorno = $errormsg = null;

		if(!isset($ulist['host']) || substr($ulist['host'],-1) === '.') throw new InvalidArgumentException('Connection fail `'.$url.'`');
		$fp	= fsockopen((($ssl) ? 'ssl://' : '').$ulist['host'],(isset($port) ? $port : ($ssl ? 443 : 80)),$errorno,$errormsg,$this->timeout);
		if($fp == false || false == stream_set_blocking($fp,true) || false == stream_set_timeout($fp,$this->timeout)) throw new InvalidArgumentException('Connection fail `'.$url.'` '.$errormsg.' '.$errorno);
		$cmd = sprintf("%s %s%s HTTP/1.1\r\n",$method,((!isset($ulist["path"])) ? "/" : $ulist["path"]),(isset($ulist["query"])) ? sprintf("?%s",$ulist["query"]) : "")
				.sprintf("Host: %s\r\n",$ulist['host'].(empty($port) ? '' : ':'.$port));

		if(!isset($header['User-Agent'])) $header['User-Agent'] = empty($this->agent) ? (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null) : $this->agent;
		if(!isset($header['Accept'])) $header['Accept'] = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : null;
		if(!isset($header['Accept-Language'])) $header['Accept-Language'] = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null;
		if(!isset($header['Accept-Charset'])) $header['Accept-Charset'] = isset($_SERVER['HTTP_ACCEPT_CHARSET']) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : (isset($header['Accept-Language']) ? 'UTF-8' : null);
		$header['Connection'] = 'Close';

		foreach($header as $k => $v){
			if(isset($v)) $cmd .= sprintf("%s: %s\r\n",$k,$v);
		}
		if(!isset($header['Authorization']) && isset($ulist["user"]) && isset($ulist["pass"])){
			$cmd .= sprintf("Authorization: Basic %s\r\n",base64_encode(sprintf("%s:%s",urldecode($ulist["user"]),urldecode($ulist["pass"]))));
		}
		$result->cmd = $cmd.((!empty($raw)) ? ('Content-length: '.strlen($raw)."\r\n\r\n".$raw) : "\r\n");
		fwrite($fp,$result->cmd);

		while(!feof($fp) && substr($result->head,-4) != "\r\n\r\n"){
			$result->head .= fgets($fp,4096);
			self::check_timeout($fp,$url);
		}
		$result->status = (preg_match("/HTTP\/.+[\040](\d\d\d)/i",$result->head,$httpCode)) ? intval($httpCode[1]) : 0;
		$result->encode = (preg_match("/Content-Type.+charset[\s]*=[\s]*([\-\w]+)/",$result->head,$match)) ? trim($match[1]) : null;

		switch($result->status){
			case 300:
			case 301:
			case 302:
			case 303:
			case 307:
				if(preg_match("/Location:[\040](.*)/i",$result->head,$redirect_url)){
					$result->redirect = preg_replace("/[\r\n]/","",Testman_Path::absolute($url,$redirect_url[1]));
					if($method == 'GET' && $result->redirect === $result->url){
						$result->redirect = null;
					}else if($status_redirect){
						fclose($fp);
						return $this->request($result->redirect,"GET",$h,array(),$download_path,$status_redirect);
					}
				}
		}
		$download_handle = ($download_path !== null && (is_dir(dirname($download_path)) || Testman_File::mkdir(dirname($download_path),0777))) ? fopen($download_path,'wb') : null;
		if(preg_match("/^Content\-Length:[\s]+([0-9]+)\r\n/i",$result->head,$m)){
			if(0 < ($length = $m[1])){
				$rest = $length % 4096;
				$count = ($length - $rest) / 4096;

				while(!feof($fp)){
					if($count-- > 0){
						self::write_body($result,$download_handle,fread($fp,4096));
					}else{
						self::write_body($result,$download_handle,fread($fp,$rest));
						break;
					}
					self::check_timeout($fp,$url);
				}
			}
		}else if(preg_match("/Transfer\-Encoding:[\s]+chunked/i",$result->head)){
			while(!feof($fp)){
				$size = hexdec(trim(fgets($fp,4096)));
				$buffer = "";

				while($size > 0 && strlen($buffer) < $size){
					$value = fgets($fp,$size);
					if($value === feof($fp)) break;
					$buffer .= $value;
				}
				self::write_body($result,$download_handle,substr($buffer,0,$size));
				self::check_timeout($fp,$url);
			}
		}else{
			while(!feof($fp)){
				self::write_body($result,$download_handle,fread($fp,4096));
				self::check_timeout($fp,$url);
			}
		}
		fclose($fp);
		if($download_handle !== null) fclose($download_handle);
		return $result;
	}
	static private function check_timeout($fp,$url){
		$info = stream_get_meta_data($fp);
		if($info['timed_out']){
			fclose($fp);
			throw new LogicException('Connection time out. `'.$url.'`');
		}
	}
	static private function write_body(&$result,&$download_handle,$value){
		if($download_handle !== null) return fwrite($download_handle,$value);
		return $result->body .= $value;
	}
	static public function htmldecode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,"UTF-8",mb_detect_encoding($value));
			$value = preg_replace("/&#[xX]([0-9a-fA-F]+);/eu","'&#'.hexdec('\\1').';'",$value);
			$value = mb_decode_numericentity($value,array(0x0,0x10000,0,0xfffff),"UTF-8");
			$value = html_entity_decode($value,ENT_QUOTES,"UTF-8");
			$value = str_replace(array("\\\"","\\'","\\\\"),array("\"","\'","\\"),$value);
		}
		return $value;
	}
}
?><?php
/**
 * query文字列を作成する
 * @author tokushima
 */
class Testman_Http_Query{
	/**
	 * query文字列に変換する
	 * @param mixed $var query文字列化する変数
	 * @param string $name ベースとなる名前
	 * @param boolean $null nullの値を表現するか
	 * @param boolean $array 配列を表現するか
	 * @return string
	 */
	static public function get($var,$name=null,$null=true,$array=true){
		$result = '';
		foreach(self::expand_vars($vars,$var,$name,$array) as $v){
			if(($null || ($v[1] !== null && $v[1] !== '')) && is_string($v[1])) $result .= $v[0].'='.urlencode($v[1]).'&';
		}
		return (empty($result)) ? $result : substr($result,0,-1);
	}
	/**
	 *
	 * @param mixed{} $vars マージ元の値
	 * @param mixed $value 展開する値
	 * @param string $name ベースとなる名前
	 * @param boolean $array 配列を表現するか
	 */
	static public function expand_vars(&$vars,$value,$name=null,$array=true){
		if(!is_array($vars)) $vars = array();
		if($value instanceof Testman_File){
			$vars[] = array($name,$value);
		}else{
			$ar = array();
			if(is_object($value)){
				if($value instanceof \Traversable){
					foreach($value as $k => $v) $ar[$k] = $v;
				}else{
					foreach(get_object_vars($value) as $k => $v) $ar[$k] = $v;
				}
				$value = $ar;
			}
			if(is_array($value)){
				foreach($value as $k => $v){
					self::expand_vars($vars,$v,(isset($name) ? $name.(($array) ? '['.$k.']' : '') : $k),$array);
				}
			}else if(!is_numeric($name)){
				if(is_bool($value)) $value = ($value) ? 'true' : 'false';
				$vars[] = array($name,(string)$value);
			}
		}
		return $vars;
	}
}
?><?php
/**
 * @author tokushima
 */
class Testman_Path{
	/**
	 * 絶対パスを返す
	 * @param string $a
	 * @param string $b
	 * @return string
	 */
	static public function absolute($a,$b){
		$a = str_replace("\\",'/',$a);
		if($b === '' || $b === null) return $a;
		$b = str_replace("\\",'/',$b);
		if($a === '' || $a === null || preg_match("/^[a-zA-Z]+:/",$b)) return $b;
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$a,$h)){
			$a = preg_replace("/^(.+?)[".(($b[0] === '#') ? '#' : "#\?")."].*$/","\\1",$a);
			if($b[0] == '#' || $b[0] == '?') return $a.$b;
			if(substr($a,-1) != '/') $b = (substr($b,0,2) == './') ? '.'.$b : (($b[0] != '.' && $b[0] != '/') ? '../'.$b : $b);
			if($b[0] == '/' && isset($h[0])) return $h[0].$b;
		}else if($b[0] == '/'){
			return $b;
		}
		$p = array(array('://','/./','//'),array('#R#','/','/'),array("/^\/(.+)$/","/^(\w):\/(.+)$/"),array("#T#\\1","\\1#W#\\2",''),array('#R#','#T#','#W#'),array('://','/',':/'));
		$a = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$a));
		$b = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$b));
		$d = $t = $r = '';
		if(strpos($a,'#R#')){
			list($r) = explode('/',$a,2);
			$a = substr($a,strlen($r));
			$b = str_replace('#T#','',$b);
		}
		$al = preg_split("/\//",$a,-1,PREG_SPLIT_NO_EMPTY);
		$bl = preg_split("/\//",$b,-1,PREG_SPLIT_NO_EMPTY);

		for($i=0;$i<sizeof($al)-substr_count($b,'../');$i++){
			if($al[$i] != '.' && $al[$i] != '..') $d .= $al[$i].'/';
		}
		for($i=0;$i<sizeof($bl);$i++){
			if($bl[$i] != '.' && $bl[$i] != '..') $t .= '/'.$bl[$i];
		}
		$t = (!empty($d)) ? substr($t,1) : $t;
		$d = (!empty($d) && $d[0] != '/' && substr($d,0,3) != '#T#' && !strpos($d,'#W#')) ? '/'.$d : $d;
		return str_replace($p[4],$p[5],$r.$d.$t);
	}
}
?><?php
/**
 * テンプレートを処理する
 * @author tokushima
 * @var mixed{} $vars バインドされる変数
 * @var boolean $secure https://をhttp://に置換するか
 * @var string $put_block ブロックファイル
 * @var string $template_super 継承元テンプレート
 * @var string $media_url メディアファイルへのURLの基点
 * @conf boolean $display_exception 例外が発生した場合にメッセージを表示するか
 */
class Testman_Template{
	private $file;
	private $selected_template;
	private $selected_src;

	private $secure = false;
	private $vars = array();
	private $put_block;
	private $template_super;
	private $media_url;

	public function __construct($media_url=null){
		if($media_url !== null) $this->media_url($media_url);
	}
	/**
	 * メディアファイルへのURLの基点を設定
	 * @param string $url
	 * @return $this
	 */
	public function media_url($url){
		$this->media_url = str_replace("\\",'/',$url);
		if(!empty($this->media_url) && substr($this->media_url,-1) !== '/') $this->media_url = $this->media_url.'/';
	}
	public function template_super($path){
		$this->template_super = $path;
	}
	public function put_block($path){
		$this->put_block = $path;
	}
	public function secure($bool){
		$this->secure = (boolean)$bool;
	}
	public function vars($key,$value){
		$this->vars[$key] = $value;
	}
	/**
	 * 出力する
	 * @param string $file
	 * @param string $template_name
	 */
	final public function output($file,$template_name=null){
		print($this->read($file,$template_name));
		exit;
	}
	/**
	 * ファイルを読み込んで結果を返す
	 * @param string $file
	 * @param string $template_name
	 * @return string
	 */
	final public function read($file,$template_name=null){
		if(!is_file($file) && strpos($file,'://') === false) throw new \InvalidArgumentException($file.' not found');
		$this->file = $file;
		$cname = md5($this->template_super.$this->put_block.$this->file.$this->selected_template);

		if(!empty($this->put_block)){
			$src = $this->read_src($this->put_block);
			if(strpos($src,'rt:extends') !== false){
				Testman_Xml::set($x,'<:>'.$src.'</:>');
				foreach($x->in('rt:extends') as $ext) $src = str_replace($ext->plain(),'',$src);
			}
			$src = sprintf('<rt:extends href="%s" />\n',$file).$src;
			$this->file = $this->put_block;
		}else{
			$src = $this->read_src($this->file);
		}
		$src = $this->replace($src,$template_name);
		return $this->execute($src);
	}
	private function cname(){
		return md5($this->put_block.$this->file.$this->selected_template);
	}
	/**
	 * 文字列から結果を返す
	 * @param string $src
	 * @param string $template_name
	 * @return string
	 */
	final public function get($src,$template_name=null){
		return $this->execute($this->replace($src,$template_name));
	}
	private function execute($src){
		$src = $this->exec($src);
		$src = str_replace(array('#PS#','#PE#'),array('<?','?>'),$this->html_reform($src));
		return $src;
	}
	private function replace($src,$template_name){
		$this->selected_template = $template_name;
		$src = preg_replace("/([\w])\->/","\\1__PHP_ARROW__",$src);
		$src = str_replace(array("\\\\","\\\"","\\'"),array('__ESC_DESC__','__ESC_DQ__','__ESC_SQ__'),$src);
		$src = $this->replace_xtag($src);
		// FIXME init_template
		$src = $this->rtcomment($this->rtblock($this->rttemplate($src),$this->file));
		$this->selected_src = $src;
		// FIXME before_template
		$src = $this->rtif($this->rtloop($this->rtunit($this->html_form($this->html_list($src)))));
		// FIXME after_template
		$src = str_replace('__PHP_ARROW__','->',$src);
		$src = $this->parse_print_variable($src);
		$php = array(' ?>','<?php ','->');
		$str = array('__PHP_TAG_END__','__PHP_TAG_START__','__PHP_ARROW__');
		$src = str_replace($php,$str,$src);
		$src = $this->parse_url($src,$this->media_url);
		$src = str_replace($str,$php,$src);
		$src = str_replace(array('__ESC_DQ__','__ESC_SQ__','__ESC_DESC__'),array("\\\"","\\'","\\\\"),$src);
		return $src;
	}
	private function exec($_src_){
		// FIXME before_exec_template
		$this->vars('_t_',new static());
		ob_start();
			if(is_array($this->vars) && !empty($this->vars)) extract($this->vars);
			eval('?>'.$_src_);
		$_eval_src_ = ob_get_clean();

		if(strpos($_eval_src_,'Parse error: ') !== false){
			if(preg_match("/Parse error\:(.+?) in .+eval\(\)\'d code on line (\d+)/",$_eval_src_,$match)){
				list($msg,$line) = array(trim($match[1]),((int)$match[2]));
				$lines = explode("\n",$_src_);
				$plrp = substr_count(implode("\n",array_slice($lines,0,$line)),"<?php 'PLRP'; ?>\n");
				$this->error_msg($msg.' on line '.($line-$plrp).' [compile]: '.trim($lines[$line-1]));

				$lines = explode("\n",$this->selected_src);
				$this->error_msg($msg.' on line '.($line-$plrp).' [plain]: '.trim($lines[$line-1-$plrp]));
			}
		}
		$this->selected_src = null;
		// FIXME after_exec_template
		return $_eval_src_;
	}
	public function error_msg($msg){
		print($msg);
	}
	/**
	 * エラー時の処理
	 * @param string $str
	 */
	public function parse_error($str){
		// FIXME print($str);
	}
	/**
	 * 出力エラーの処理
	 * @param \Excpeption $e
	 */
	public function print_error(\Exception $e){
		// FIXME print($e->getMessage());
	}
	private function error_handler($errno,$errstr,$errfile,$errline){
		throw new \ErrorException($errstr,0,$errno,$errfile,$errline);
	}
	private function replace_xtag($src){
		if(preg_match_all("/<\?(?!php[\s\n])[\w]+ .*?\?>/s",$src,$null)){
			foreach($null[0] as $value) $src = str_replace($value,'#PS#'.substr($value,2,-2).'#PE#',$src);
		}
		return $src;
	}
	private function parse_url($src,$media=null){
		if(!empty($media) && substr($media,-1) !== '/') $media = $media.'/';
		$secure_base = ($this->secure) ? str_replace('http://','https://',$media) : null;
		if(preg_match_all("/<([^<\n]+?[\s])(src|href|background)[\s]*=[\s]*([\"\'])([^\\3\n]+?)\\3[^>]*?>/i",$src,$match)){
			foreach($match[2] as $k => $p){
				$t = null;
				if(strtolower($p) === 'href') list($t) = (preg_split("/[\s]/",strtolower($match[1][$k])));
				$src = $this->replace_parse_url($src,(($this->secure && $t !== 'a') ? $secure_base : $media),$match[0][$k],$match[4][$k]);
			}
		}
		if(preg_match_all("/[^:]:[\040]*url\(([^\n]+?)\)/",$src,$match)){
			if($this->secure) $media = $secure_base;
			foreach($match[1] as $key => $param) $src = $this->replace_parse_url($src,$media,$match[0][$key],$match[1][$key]);
		}
		return $src;
	}
	private function replace_parse_url($src,$base,$dep,$rep){
		if(!preg_match("/(^[\w]+:\/\/)|(^__PHP_TAG_START)|(^\{\\$)|(^\w+:)|(^[#\?])/",$rep)){
			$src = str_replace($dep,str_replace($rep,$this->ab_path($base,$rep),$dep),$src);
		}
		return $src;
	}
	private function ab_path($a,$b){
		if($b === '' || $b === null) return $a;
		if($a === '' || $a === null || preg_match("/^[a-zA-Z]+:/",$b)) return $b;
		if(preg_match("/^[\w]+\:\/\/[^\/]+/",$a,$h)){
			$a = preg_replace("/^(.+?)[".(($b[0] === '#') ? '#' : "#\?")."].*$/","\\1",$a);
			if($b[0] == '#' || $b[0] == '?') return $a.$b;
			if(substr($a,-1) != '/') $b = (substr($b,0,2) == './') ? '.'.$b : (($b[0] != '.' && $b[0] != '/') ? '../'.$b : $b);
			if($b[0] == '/' && isset($h[0])) return $h[0].$b;
		}else if($b[0] == '/'){
			return $b;
		}
		$p = array(array('://','/./','//'),array('#R#','/','/'),array("/^\/(.+)$/","/^(\w):\/(.+)$/"),array("#T#\\1","\\1#W#\\2",''),array('#R#','#T#','#W#'),array('://','/',':/'));
		$a = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$a));
		$b = preg_replace($p[2],$p[3],str_replace($p[0],$p[1],$b));
		$d = $t = $r = '';
		if(strpos($a,'#R#')){
			list($r) = explode('/',$a,2);
			$a = substr($a,strlen($r));
			$b = str_replace('#T#','',$b);
		}
		$al = preg_split("/\//",$a,-1,PREG_SPLIT_NO_EMPTY);
		$bl = preg_split("/\//",$b,-1,PREG_SPLIT_NO_EMPTY);

		for($i=0;$i<sizeof($al)-substr_count($b,'../');$i++){
			if($al[$i] != '.' && $al[$i] != '..') $d .= $al[$i].'/';
		}
		for($i=0;$i<sizeof($bl);$i++){
			if($bl[$i] != '.' && $bl[$i] != '..') $t .= '/'.$bl[$i];
		}
		$t = (!empty($d)) ? substr($t,1) : $t;
		$d = (!empty($d) && $d[0] != '/' && substr($d,0,3) != '#T#' && !strpos($d,'#W#')) ? '/'.$d : $d;
		return str_replace($p[4],$p[5],$r.$d.$t);
	}
	private function read_src($filename){
		$src = file_get_contents($filename);
		return (preg_match('/^http[s]*\:\/\//',$filename)) ? $this->parse_url($src,dirname($filename)) : $src;
	}
	private function rttemplate($src){
		$values = array();
		$bool = false;
		while(Testman_Xml::set($tag,$src,'rt:template')){
			$src = str_replace($tag->plain(),'',$src);
			$values[$tag->in_attr('name')] = $tag->value();
			$src = str_replace($tag->plain(),'',$src);
			$bool = true;
		}
		if(!empty($this->selected_template)){
			if(!array_key_exists($this->selected_template,$values)) throw new \LogicException('undef rt:template '.$this->selected_template);
			return $values[$this->selected_template];
		}
		return ($bool) ? implode($values) : $src;
	}
	private function rtblock($src,$filename){
		if(strpos($src,'rt:block') !== false || strpos($src,'rt:extends') !== false){
			$base_filename = $filename;
			$blocks = $paths = array();
			while(Testman_Xml::set($e,'<:>'.$this->rtcomment($src).'</:>','rt:extends') !== false){
				$href = $this->ab_path(str_replace("\\",'/',dirname($filename)),$e->in_attr('href'));
				if(!$e->is_attr('href') || !is_file($href)) throw new \LogicException('href ('.$href.') not found '.$filename);
				if($filename === $href) throw new \LogicException('Infinite Recursion Error'.$filename);
				Testman_Xml::set($bx,'<:>'.$this->rtcomment($src).'</:>',':');
				foreach($bx->in('rt:block') as $b){
					$n = $b->in_attr('name');
					if(!empty($n) && !array_key_exists($n,$blocks)){
						$blocks[$n] = $b->value();
						$paths[$n] = $filename;
					}
				}
				$src = $this->rttemplate($this->replace_xtag($this->read_src($filename = $href)));
				$this->selected_template = $e->in_attr('name');
			}
			// FIXME before_block_template
			if(empty($blocks)){
				if(Testman_Xml::set($bx,'<:>'.$src.'</:>')){
					foreach($bx->in('rt:block') as $b) $src = str_replace($b->plain(),$b->value(),$src);
				}
			}else{
				if(!empty($this->template_super)) $src = $this->read_src($this->ab_path(str_replace("\\",'/',dirname($base_filename)),$this->template_super));
				while(Testman_Xml::set($b,$src,'rt:block')){
					$n = $b->in_attr('name');
					$src = str_replace($b->plain(),(array_key_exists($n,$blocks) ? $blocks[$n] : $b->value()),$src);
				}
			}
			$this->file = $filename;
		}
		return $src;
	}
	private function rtcomment($src){
		while(Testman_Xml::set($tag,$src,'rt:comment')) $src = str_replace($tag->plain(),'',$src);
		return $src;
	}
	private function rtunit($src){
		if(strpos($src,'rt:unit') !== false){
			while(Testman_Xml::set($tag,$src,'rt:unit')){
				$tag->escape(false);
				$uniq = uniqid('');
				$param = $tag->in_attr('param');
				$var = '$'.$tag->in_attr('var','_var_'.$uniq);
				$offset = $tag->in_attr('offset',1);
				$total = $tag->in_attr('total','_total_'.$uniq);
				$cols = ($tag->is_attr('cols')) ? (ctype_digit($tag->in_attr('cols')) ? $tag->in_attr('cols') : $this->variable_string($this->parse_plain_variable($tag->in_attr('cols')))) : 1;
				$rows = ($tag->is_attr('rows')) ? (ctype_digit($tag->in_attr('rows')) ? $tag->in_attr('rows') : $this->variable_string($this->parse_plain_variable($tag->in_attr('rows')))) : 0;
				$value = $tag->value();

				$cols_count = '$_ucount_'.$uniq;
				$cols_total = '$'.$tag->in_attr('cols_total','_cols_total_'.$uniq);
				$rows_count = '$'.$tag->in_attr('counter','_counter_'.$uniq);
				$rows_total = '$'.$tag->in_attr('rows_total','_rows_total_'.$uniq);
				$ucols = '$_ucols_'.$uniq;
				$urows = '$_urows_'.$uniq;
				$ulimit = '$_ulimit_'.$uniq;
				$ufirst = '$_ufirst_'.$uniq;
				$ufirstnm = '_ufirstnm_'.$uniq;

				$ukey = '_ukey_'.$uniq;
				$uvar = '_uvar_'.$uniq;

				$src = str_replace(
							$tag->plain(),
							sprintf('<?php %s=%s; %s=%s; %s=%s=1; %s=null; %s=%s*%s; %s=array(); ?>'
									.'<rt:loop param="%s" var="%s" key="%s" total="%s" offset="%s" first="%s">'
										.'<?php if(%s <= %s){ %s[$%s]=$%s; } ?>'
										.'<rt:first><?php %s=$%s; ?></rt:first>'
										.'<rt:last><?php %s=%s; ?></rt:last>'
										.'<?php if(%s===%s){ ?>'
											.'<?php if(isset(%s)){ $%s=""; } ?>'
											.'<?php %s=sizeof(%s); ?>'
											.'<?php %s=ceil($%s/%s); ?>'
											.'%s'
											.'<?php %s=array(); %s=null; %s=1; %s++; ?>'
										.'<?php }else{ %s++; } ?>'
									.'</rt:loop>'
									,$ucols,$cols,$urows,$rows,$cols_count,$rows_count,$ufirst,$ulimit,$ucols,$urows,$var
									,$param,$uvar,$ukey,$total,$offset,$ufirstnm
										,$cols_count,$ucols,$var,$ukey,$uvar
										,$ufirst,$ufirstnm
										,$cols_count,$ucols
										,$cols_count,$ucols
											,$ufirst,$ufirstnm
											,$cols_total,$var
											,$rows_total,$total,$ucols
											,$value
											,$var,$ufirst,$cols_count,$rows_count
										,$cols_count
							)
							.($tag->is_attr('rows') ?
								sprintf('<?php for(;%s<=%s;%s++){ %s=array(); ?>%s<?php } ?>',$rows_count,$rows,$rows_count,$var,$value) : ''
							)
							,$src
						);
			}
		}
		return $src;
	}
	private function rtloop($src){
		if(strpos($src,'rt:loop') !== false){
			while(Testman_Xml::set($tag,$src,'rt:loop')){
				$tag->escape(false);
				$param = ($tag->is_attr('param')) ? $this->variable_string($this->parse_plain_variable($tag->in_attr('param'))) : null;
				$offset = ($tag->is_attr('offset')) ? (ctype_digit($tag->in_attr('offset')) ? $tag->in_attr('offset') : $this->variable_string($this->parse_plain_variable($tag->in_attr('offset')))) : 1;
				$limit = ($tag->is_attr('limit')) ? (ctype_digit($tag->in_attr('limit')) ? $tag->in_attr('limit') : $this->variable_string($this->parse_plain_variable($tag->in_attr('limit')))) : 0;
				if(empty($param) && $tag->is_attr('range')){
					list($range_start,$range_end) = explode(',',$tag->in_attr('range'),2);
					$range = ($tag->is_attr('range_step')) ? sprintf('range(%d,%d,%d)',$range_start,$range_end,$tag->in_attr('range_step')) :
																sprintf('range("%s","%s")',$range_start,$range_end);
					$param = sprintf('array_combine(%s,%s)',$range,$range);
				}
				$is_fill = false;
				$uniq = uniqid('');
				$even = $tag->in_attr('even_value','even');
				$odd = $tag->in_attr('odd_value','odd');
				$evenodd = '$'.$tag->in_attr('evenodd','loop_evenodd');

				$first_value = $tag->in_attr('first_value','first');
				$first = '$'.$tag->in_attr('first','_first_'.$uniq);
				$first_flg = '$__isfirst__'.$uniq;
				$last_value = $tag->in_attr('last_value','last');
				$last = '$'.$tag->in_attr('last','_last_'.$uniq);
				$last_flg = '$__islast__'.$uniq;
				$shortfall = '$'.$tag->in_attr('shortfall','_DEFI_'.$uniq);

				$var = '$'.$tag->in_attr('var','_var_'.$uniq);
				$key = '$'.$tag->in_attr('key','_key_'.$uniq);
				$total = '$'.$tag->in_attr('total','_total_'.$uniq);
				$vtotal = '$__vtotal__'.$uniq;
				$counter = '$'.$tag->in_attr('counter','_counter_'.$uniq);
				$loop_counter = '$'.$tag->in_attr('loop_counter','_loop_counter_'.$uniq);
				$reverse = (strtolower($tag->in_attr('reverse') === 'true'));

				$varname = '$_'.$uniq;
				$countname = '$__count__'.$uniq;
				$lcountname = '$__vcount__'.$uniq;
				$offsetname	= '$__offset__'.$uniq;
				$limitname = '$__limit__'.$uniq;

				$value = $tag->value();
				$empty_value = null;
				while(Testman_Xml::set($subtag,$value,'rt:loop')){
					$value = $this->rtloop($value);
				}
				while(Testman_Xml::set($subtag,$value,'rt:first')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$first
					,(($subtag->in_attr('last') === 'false') ? sprintf(' && (%s !== 1) ',$total) : '')
					,preg_replace("/<rt\:else[\s]*.*?>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Testman_Xml::set($subtag,$value,'rt:middle')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(!isset(%s) && !isset(%s)){ ?>%s<?php } ?>',$first,$last
					,preg_replace("/<rt\:else[\s]*.*?>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Testman_Xml::set($subtag,$value,'rt:last')){
					$value = str_replace($subtag->plain(),sprintf('<?php if(isset(%s)%s){ ?>%s<?php } ?>',$last
					,(($subtag->in_attr('first') === 'false') ? sprintf(' && (%s !== 1) ',$vtotal) : '')
					,preg_replace("/<rt\:else[\s]*.*?>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				while(Testman_Xml::set($subtag,$value,'rt:fill')){
					$is_fill = true;
					$value = str_replace($subtag->plain(),sprintf('<?php if(%s > %s){ ?>%s<?php } ?>',$lcountname,$total
					,preg_replace("/<rt\:else[\s]*.*?>/i","<?php }else{ ?>",$this->rtloop($subtag->value()))),$value);
				}
				$value = $this->rtif($value);
				if(preg_match("/^(.+)<rt\:else[\s]*.*?>(.+)$/ims",$value,$match)){
					list(,$value,$empty_value) = $match;
				}
				$src = str_replace(
							$tag->plain(),
							sprintf("<?php try{ ?>"
									."<?php "
										." %s=%s;"
										." if(is_array(%s)){"
											." if(%s){ krsort(%s); }"
											." %s=%s=sizeof(%s); %s=%s=1; %s=%s; %s=((%s>0) ? (%s + %s) : 0); "
											." %s=%s=false; %s=0; %s=%s=null;"
											." if(%s){ for(\$i=0;\$i<(%s+%s-%s);\$i++){ %s[] = null; } %s=sizeof(%s); }"
											." foreach(%s as %s => %s){"
												." if(%s <= %s){"
													." if(!%s){ %s=true; %s='%s'; }"
													." if((%s > 0 && (%s+1) == %s) || %s===%s){ %s=true; %s='%s'; %s=(%s-%s+1) * -1;}"
													." %s=((%s %% 2) === 0) ? '%s' : '%s';"
													." %s=%s; %s=%s;"
													." ?>%s<?php "
													." %s=%s=null;"
													." %s++;"
												." }"
												." %s++;"
												." if(%s > 0 && %s >= %s){ break; }"
											." }"
											." if(!%s){ ?>%s<?php } "
											." unset(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s);"
										." }"
									." ?>"
									."<?php }catch(\\Exception \$e){ \$_t_->print_error(\$e); } ?>"
									,$varname,$param
									,$varname
										,(($reverse) ? 'true' : 'false'),$varname
										,$vtotal,$total,$varname,$countname,$lcountname,$offsetname,$offset,$limitname,$limit,$offset,$limit
										,$first_flg,$last_flg,$shortfall,$first,$last
										,($is_fill ? 'true' : 'false'),$offsetname,$limitname,$total,$varname,$vtotal,$varname
										,$varname,$key,$var
											,$offsetname,$lcountname
												,$first_flg,$first_flg,$first,str_replace("'","\\'",$first_value)
												,$limitname,$lcountname,$limitname,$lcountname,$vtotal,$last_flg,$last,str_replace("'","\\'",$last_value),$shortfall,$lcountname,$limitname
												,$evenodd,$countname,$even,$odd
												,$counter,$countname,$loop_counter,$lcountname
												,$value
												,$first,$last
												,$countname
											,$lcountname
											,$limitname,$lcountname,$limitname
									,$first_flg,$empty_value
									,$var,$counter,$key,$countname,$lcountname,$offsetname,$limitname,$varname,$first,$first_flg,$last,$last_flg
							)
							,$src
						);
			}
		}
		return $src;
	}
	private function rtif($src){
		if(strpos($src,'rt:if') !== false){
			while(Testman_Xml::set($tag,$src,'rt:if')){
				$tag->escape(false);
				if(!$tag->is_attr('param')) throw new \LogicException('if');
				$arg1 = $this->variable_string($this->parse_plain_variable($tag->in_attr('param')));

				if($tag->is_attr('value')){
					$arg2 = $this->parse_plain_variable($tag->in_attr('value'));
					if($arg2 == 'true' || $arg2 == 'false' || ctype_digit((string)$arg2)){
						$cond = sprintf('<?php if(%s === %s || %s === "%s"){ ?>',$arg1,$arg2,$arg1,$arg2);
					}else{
						if($arg2 === '' || $arg2[0] != '$') $arg2 = '"'.$arg2.'"';
						$cond = sprintf('<?php if(%s === %s){ ?>',$arg1,$arg2);
					}
				}else{
					$uniq = uniqid('$I');
					$cond = sprintf("<?php try{ %s=%s; }catch(\\Exception \$e){ %s=null; } ?>",$uniq,$arg1,$uniq)
								.sprintf('<?php if(%s !== null && %s !== false && ( (!is_string(%s) && !is_array(%s)) || (is_string(%s) && %s !== "") || (is_array(%s) && !empty(%s)) ) ){ ?>',$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq,$uniq);
				}
				$src = str_replace(
							$tag->plain()
							,'<?php try{ ?>'.$cond
								.preg_replace("/<rt\:else[\s]*.*?>/i","<?php }else{ ?>",$tag->value())
							."<?php } ?>"
							."<?php }catch(\\Exception \$e){ \$_t_->print_error(\$e); } ?>"
							,$src
						);
			}
		}
		return $src;
	}
	private function parse_print_variable($src){
		foreach($this->match_variable($src) as $variable){
			$name = $this->parse_plain_variable($variable);
			$value = '<?php try{ @print('.$name.'); ?>'
						."<?php }catch(\\Exception \$e){ \$_t_->print_error(\$e); } ?>";
			$src = str_replace(array($variable.PHP_EOL,$variable),array($value."<?php 'PLRP'; ?>\n\n",$value),$src);
			$src = str_replace($variable,$value,$src);
		}
		return $src;
	}
	private function match_variable($src){
		$hash = array();
		while(preg_match("/({(\\$[\$\w][^\t]*)})/s",$src,$vars,PREG_OFFSET_CAPTURE)){
			list($value,$pos) = $vars[1];
			if($value == "") break;
			if(substr_count($value,'}') > 1){
				for($i=0,$start=0,$end=0;$i<strlen($value);$i++){
					if($value[$i] == '{'){
						$start++;
					}else if($value[$i] == '}'){
						if($start == ++$end){
							$value = substr($value,0,$i+1);
							break;
						}
					}
				}
			}
			$length	= strlen($value);
			$src = substr($src,$pos + $length);
			$hash[sprintf('%03d_%s',$length,$value)] = $value;
		}
		krsort($hash,SORT_STRING);
		return $hash;
	}
	private function parse_plain_variable($src){
		while(true){
			$array = $this->match_variable($src);
			if(sizeof($array) <= 0)	break;
			foreach($array as $v){
				$tmp = $v;
				if(preg_match_all("/([\"\'])([^\\1]+?)\\1/",$v,$match)){
					foreach($match[2] as $value) $tmp = str_replace($value,str_replace('.','__PERIOD__',$value),$tmp);
				}
				$src = str_replace($v,preg_replace('/([\w\)\]])\./','\\1->',substr($tmp,1,-1)),$src);
			}
		}
		return str_replace('[]','',str_replace('__PERIOD__','.',$src));
	}
	private function variable_string($src){
		return (empty($src) || isset($src[0]) && $src[0] == '$') ? $src : '$'.$src;
	}
	private function html_reform($src){
		if(strpos($src,'rt:aref') !== false){
			Testman_Xml::set($tag,'<:>'.$src.'</:>');
			foreach($tag->in('form') as $obj){
				if($obj->is_attr('rt:aref')){
					$bool = ($obj->in_attr('rt:aref') === 'true');
					$obj->rm_attr('rt:aref');
					$obj->escape(false);
					$value = $obj->get();

					if($bool){
						foreach($obj->in(array('input','select','textarea')) as $tag){
							if(!$tag->is_attr('rt:ref') && ($tag->is_attr('name') || $tag->is_attr('id'))){
								switch(strtolower($tag->in_attr('type','text'))){
									case 'button':
									case 'submit':
									case 'file':
										break;
									default:
										$tag->attr('rt:ref','true');
										$obj->value(str_replace($tag->plain(),$tag->get(),$obj->value()));
								}
							}
						}
						$value = $this->exec($this->parse_print_variable($this->html_input($obj->get())));
					}
					$src = str_replace($obj->plain(),$value,$src);
				}
			}
		}
		return $src;
	}
	private function html_form($src){
		Testman_Xml::set($tag,'<:>'.$src.'</:>');
		foreach($tag->in('form') as $obj){
			if($this->is_reference($obj)){
				$obj->escape(false);
				foreach($obj->in(array('input','select','textarea')) as $tag){
					if(!$tag->is_attr('rt:ref') && ($tag->is_attr('name') || $tag->is_attr('id'))){
						switch(strtolower($tag->in_attr('type','text'))){
							case 'button':
							case 'submit':
								break;
							case 'file':
								$obj->attr('enctype','multipart/form-data');
								$obj->attr('method','post');
								break;
							default:
								$tag->attr('rt:ref','true');
								$obj->value(str_replace($tag->plain(),$tag->get(),$obj->value()));
						}
					}
				}
				$src = str_replace($obj->plain(),$obj->get(),$src);
			}
		}
		return $this->html_input($src);
	}
	private function no_exception_str($value){
		return $value;
	}
	private function html_input($src){
		Testman_Xml::set($tag,'<:>'.$src.'</:>');
		foreach($tag->in(array('input','textarea','select')) as $obj){
			if('' != ($originalName = $obj->in_attr('name',$obj->in_attr('id','')))){
				$obj->escape(false);
				$type = strtolower($obj->in_attr('type','text'));
				$name = $this->parse_plain_variable($this->form_variable_name($originalName));
				$lname = strtolower($obj->name());
				$change = false;
				$uid = uniqid();

				if(substr($originalName,-2) !== '[]'){
					if($type == 'checkbox'){
						if($obj->in_attr('rt:multiple','true') === 'true') $obj->attr('name',$originalName.'[]');
						$obj->rm_attr('rt:multiple');
						$change = true;
					}else if($obj->is_attr('multiple') || $obj->in_attr('multiple') === 'multiple'){
						$obj->attr('name',$originalName.'[]');
						$obj->rm_attr('multiple');
						$obj->attr('multiple','multiple');
						$change = true;
					}
				}else if($obj->in_attr('name') !== $originalName){
					$obj->attr('name',$originalName);
					$change = true;
				}
				if($obj->is_attr('rt:param') || $obj->is_attr('rt:range')){
					switch($lname){
						case 'select':
							$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" key="%s" offset="%s" limit="%s" reverse="%s" evenodd="%s" even_value="%s" odd_value="%s" range="%s" range_step="%s">'
											.'<option value="{$%s}">{$%s}</option>'
											.'</rt:loop>'
											,$obj->in_attr('rt:param'),$obj->in_attr('rt:var','loop_var'.$uid),$obj->in_attr('rt:counter','loop_counter'.$uid)
											,$obj->in_attr('rt:key','loop_key'.$uid),$obj->in_attr('rt:offset','0'),$obj->in_attr('rt:limit','0')
											,$obj->in_attr('rt:reverse','false')
											,$obj->in_attr('rt:evenodd','loop_evenodd'.$uid),$obj->in_attr('rt:even_value','even'),$obj->in_attr('rt:odd_value','odd')
											,$obj->in_attr('rt:range'),$obj->in_attr('rt:range_step',1)
											,$obj->in_attr('rt:key','loop_key'.$uid),$obj->in_attr('rt:var','loop_var'.$uid)
							);
							$obj->value($this->rtloop($value));
							if($obj->is_attr('rt:null')) $obj->value('<option value="">'.$obj->in_attr('rt:null').'</option>'.$obj->value());
					}
					$obj->rm_attr('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd'
									,'rt:range','rt:range_step','rt:even_value','rt:odd_value');
					$change = true;
				}
				if($this->is_reference($obj)){
					switch($lname){
						case 'textarea':
							$obj->value($this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',((preg_match("/^{\$(.+)}$/",$originalName,$match)) ? '{$$'.$match[1].'}' : '{$'.$originalName.'}'))));
							break;
						case 'select':
							$select = $obj->value();
							foreach($obj->in('option') as $option){
								$option->escape(false);
								$value = $this->parse_plain_variable($option->in_attr('value'));
								if(empty($value) || $value[0] != '$') $value = sprintf("'%s'",$value);
								$option->rm_attr('selected');
								$option->plain_attr($this->check_selected($name,$value,'selected'));
								$select = str_replace($option->plain(),$option->get(),$select);
							}
							$obj->value($select);
							break;
						case 'input':
							switch($type){
								case 'checkbox':
								case 'radio':
									$value = $this->parse_plain_variable($obj->in_attr('value','true'));
									$value = (substr($value,0,1) != '$') ? sprintf("'%s'",$value) : $value;
									$obj->rm_attr('checked');
									$obj->plain_attr($this->check_selected($name,$value,'checked'));
									break;
								case 'text':
								case 'hidden':
								case 'password':
								case 'search':
								case 'url':
								case 'email':
								case 'tel':
								case 'datetime':
								case 'date':
								case 'month':
								case 'week':
								case 'time':
								case 'datetime-local':
								case 'number':
								case 'range':
								case 'color':
									$obj->attr('value',$this->no_exception_str(sprintf('{$_t_.htmlencode(%s)}',
																((preg_match("/^\{\$(.+)\}$/",$originalName,$match)) ?
																	'{$$'.$match[1].'}' :
																	'{$'.$originalName.'}'))));
									break;
							}
							break;
					}
					$change = true;
				}else if($obj->is_attr('rt:ref')){
					$obj->rm_attr('rt:ref');
					$change = true;
				}
				if($change){
					switch($lname){
						case 'textarea':
						case 'select':
							$obj->close_empty(false);
					}
					$src = str_replace($obj->plain(),$obj->get(),$src);
				}
			}
		}
		return $src;
	}
	private function check_selected($name,$value,$selected){
		return sprintf('<?php if('
					.'isset(%s) && (%s === %s '
										.' || (!is_array(%s) && ctype_digit((string)%s) && (string)%s === (string)%s)'
										.' || ((%s === "true" || %s === "false") ? (%s === (%s == "true")) : false)'
										.' || in_array(%s,((is_array(%s)) ? %s : (is_null(%s) ? array() : array(%s))),true) '
									.') '
					.'){print(" %s=\"%s\"");} ?>'
					,$name,$name,$value
					,$name,$name,$name,$value
					,$value,$value,$name,$value
					,$value,$name,$name,$name,$name
					,$selected,$selected
				);
	}
	private function html_list($src){
		if(preg_match_all('/<(table|ul|ol)\s[^>]*rt\:/i',$src,$m,PREG_OFFSET_CAPTURE)){
			$tags = array();
			foreach($m[1] as $k => $v){
				if(Testman_Xml::set($tag,substr($src,$v[1]-1),$v[0])) $tags[] = $tag;
			}
			foreach($tags as $obj){
				$obj->escape(false);
				$name = strtolower($obj->name());
				$param = $obj->in_attr('rt:param');
				$null = strtolower($obj->in_attr('rt:null'));
				$value = sprintf('<rt:loop param="%s" var="%s" counter="%s" '
									.'key="%s" offset="%s" limit="%s" '
									.'reverse="%s" '
									.'evenodd="%s" even_value="%s" odd_value="%s" '
									.'range="%s" range_step="%s" '
									.'shortfall="%s">'
								,$param,$obj->in_attr('rt:var','loop_var'),$obj->in_attr('rt:counter','loop_counter')
								,$obj->in_attr('rt:key','loop_key'),$obj->in_attr('rt:offset','0'),$obj->in_attr('rt:limit','0')
								,$obj->in_attr('rt:reverse','false')
								,$obj->in_attr('rt:evenodd','loop_evenodd'),$obj->in_attr('rt:even_value','even'),$obj->in_attr('rt:odd_value','odd')
								,$obj->in_attr('rt:range'),$obj->in_attr('rt:range_step',1)
								,$tag->in_attr('rt:shortfall','_DEFI_'.uniqid())
							);
				$rawvalue = $obj->value();
				if($name == 'table' && Testman_Xml::set($t,$rawvalue,'tbody')){
					$t->escape(false);
					$t->value($value.$this->table_tr_even_odd($t->value(),(($name == 'table') ? 'tr' : 'li'),$obj->in_attr('rt:evenodd','loop_evenodd')).'</rt:loop>');
					$value = str_replace($t->plain(),$t->get(),$rawvalue);
				}else{
					$value = $value.$this->table_tr_even_odd($rawvalue,(($name == 'table') ? 'tr' : 'li'),$obj->in_attr('rt:evenodd','loop_evenodd')).'</rt:loop>';
				}
				$obj->value($this->html_list($value));
				$obj->rm_attr('rt:param','rt:key','rt:var','rt:counter','rt:offset','rt:limit','rt:null','rt:evenodd','rt:range'
								,'rt:range_step','rt:even_value','rt:odd_value','rt:shortfall');
				$src = str_replace($obj->plain(),
						($null === 'true') ? $this->rtif(sprintf('<rt:if param="%s">',$param).$obj->get().'</rt:if>') : $obj->get(),
						$src);
			}
		}
		return $src;
	}
	private function table_tr_even_odd($src,$name,$even_odd){
		Testman_Xml::set($tag,'<:>'.$src.'</:>');
		foreach($tag->in($name) as $tr){
			$tr->escape(false);
			$class = ' '.$tr->in_attr('class').' ';
			if(preg_match('/[\s](even|odd)[\s]/',$class,$match)){
				$tr->attr('class',trim(str_replace($match[0],' {$'.$even_odd.'} ',$class)));
				$src = str_replace($tr->plain(),$tr->get(),$src);
			}
		}
		return $src;
	}
	private function form_variable_name($name){
		return (strpos($name,'[') && preg_match("/^(.+)\[([^\"\']+)\]$/",$name,$match)) ?
			'{$'.$match[1].'["'.$match[2].'"]'.'}' : '{$'.$name.'}';
	}
	private function is_reference(&$tag){
		$bool = ($tag->in_attr('rt:ref') === 'true');
		$tag->rm_attr('rt:ref');
		return $bool;
	}
	public function htmlencode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,'UTF-8',mb_detect_encoding($value));
			return htmlentities($value,ENT_QUOTES,'UTF-8');
		}
		return $value;
	}
}
?><?php
class Testman_Template_Helper{
	public function htmlencode($value){
		if(!empty($value) && is_string($value)){
			$value = mb_convert_encoding($value,'UTF-8',mb_detect_encoding($value));
			return htmlentities($value,ENT_QUOTES,'UTF-8');
		}
		return $value;
	}
	public function cond_switch($cond,$true='on',$false=''){
		return ($cond === true) ? $true : $false;
	}
	public function cond_pattern_switch($a,$b,$true='on',$false=''){
		return ($a == $b) ? $true : $false;
	}
	public function nl2ul($array){
		$ul = '<ul>';
		foreach($array as $v) $ul .= '<li>'.$v.'</li>';
		$ul .= '</ul>';
		return $ul;
	}
	public function nl2br($array){
		return str_replace(PHP_EOL,'',nl2br(implode(PHP_EOL,$array),true));
	}
}
?><?php
/**
 * テスト処理
 * @author tokushima
 */
class Testman_TestRunner{
	static private $result = array();
	static private $current_entry;
	static private $current_class;
	static private $current_method;
	static private $current_file;
	static private $current_block_name;
	static private $current_block_label;
	static private $current_block_start_time;	
	static private $start_time;
	static private $urls;

	static private $entry_dir;
	static private $test_dir;
	static private $lib_dir;
	static private $func_dir;
	
	static private $disp = true;
	
	/**
	 * 結果を取得する
	 * @return string{}
	 */
	static public function get(){
		return self::$result;
	}
	/**
	 * 結果をクリアする
	 */
	static public function clear(){
		self::$result = array();
		self::$start_time = microtime(true);
	}
	/**
	 * 開始時間
	 * @return integer
	 */
	static public function start_time(){
		if(self::$start_time === null) self::$start_time = microtime(true);
		return self::$start_time;
	}
	/**
	 * 現在実行中のエントリ
	 * @return string
	 */
	static public function current_entry(){
		return self::$current_entry;
	}
	/**
	 * 実行中のテスト名
	 */
	static public function current_name(){
		$dir = array(self::$entry_dir,self::$test_dir,self::$lib_dir);
		rsort($dir);
		$name = self::$current_file;
		foreach($dir as $f) $name = str_replace($f,'',$name);
		if(!empty(self::$current_class)) $name = $name.'@'.(self::$current_class);
		if(!empty(self::$current_method) && self::$current_method != '@') $name = $name.'#'.(self::$current_method);
		return $name;
	}
	static private function current_block_info(){
		return array(self::$current_block_name,self::$current_block_label,round((microtime(true) - (float)self::$current_block_start_time),4));
	}
	/**
	 * 判定を行う
	 * @param mixed $arg1 期待値
	 * @param mixed $arg2 実行結果
	 * @param boolean 真偽どちらで判定するか
	 * @param int $line 行番号
	 * @param string $file ファイル名
	 * @return boolean
	 */
	static public function equals($arg1,$arg2,$eq,$line,$file=null){
		$result = ($eq) ? (self::expvar($arg1) === self::expvar($arg2)) : (self::expvar($arg1) !== self::expvar($arg2));
		self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = ($result) ? array(self::current_block_info()) : array(self::current_block_info(),var_export($arg1,true),var_export($arg2,true));
		return $result;
	}
	/**
	 * メッセージを登録
	 * @param string $msg メッセージ
	 * @param int $line 行番号
	 * @param string $file ファイル名
	 */
	static public function notice($msg,$line,$file=null){
		self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = array(self::current_block_info(),'notice',$msg,$file,$line);
	}
	/**
	 * 失敗を登録
	 * @param string $msg メッセージ
	 * @param int $line 行番号
	 * @param string $file ファイル名
	 */
	static public function fail($line,$file=null){
		self::$result[(empty(self::$current_file) ? $file : self::$current_file)][self::$current_class][self::$current_method][$line][] = array(self::current_block_info(),'fail','failure',$file,$line);
	}

	
	/**
	 * テスト対象の取得
	 * @return string[]
	 */
	static public function search_path($entry_dir=null,$test_dir=null,$lib_dir=null,$func_dir=null){
		$p = function($path,$op){
			$path = empty($path) ? (str_replace('\\','/',getcwd()).'/'.$op) : $path;
			$path = str_replace('\\','/',$path);
			if(substr($path,-1) !== '/') $path = $path.'/';
			return $path;
		};
		if(!isset(static::$entry_dir)) static::$entry_dir = $p($entry_dir,'');
		if(!isset(static::$test_dir)) static::$test_dir = $p($test_dir,'test');
		if(!isset(static::$lib_dir)) static::$lib_dir = $p($lib_dir,'lib');
		if(!isset(static::$func_dir)) static::$func_dir = $p($func_dir,'func');
		
		return array(static::$entry_dir,static::$test_dir,static::$lib_dir,static::$func_dir);
	}
	/**
	 * テストを実行する
	 * @param string $class_name クラス名
	 * @param string $method メソッド名
	 * @param string $block_name ブロック名
	 * @param boolean $print_progress 実行中のブロック名を出力するか
	 * @param boolean $include_tests testsディレクトリも参照するか
	 */
	static private function run($class_name,$method_name=null,$block_name=null,$print_progress=false,$include_tests=false){
		list($entry_path,$tests_path) = self::search_path();
		if(is_file($class_name)){
			$doctest = (strpos($class_name,$tests_path) === false) ? self::get_entry_doctest($class_name) : self::get_unittest($class_name);
		}else if(is_file($f=Testman_Path::absolute($entry_path,$class_name.'.php'))){
			$doctest = self::get_entry_doctest($f);
		}else if(is_dir($f=Testman_Path::absolute($tests_path,str_replace('.','/',$class_name)))){
			foreach(new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator(
							$f,
							FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS|FilesystemIterator::UNIX_PATHS
					),
					RecursiveIteratorIterator::SELF_FIRST
			) as $e){
				if($e->isFile() && substr($e->getFilename(),-4) == '.php' && strpos($e->getPathname(),'/.') === false && strpos($e->getPathname(),'/_') === false){
					self::run($e->getPathname(),null,null,$print_progress,$include_tests);
				}
			}
			return new self();
		}else if(is_file($f=Testman_Path::absolute($tests_path,str_replace('.','/',$class_name).'.php'))){
			$doctest = self::get_unittest($f);
		}else if(is_file($f=Testman_Path::absolute($tests_path,$class_name))){
			$doctest = self::get_unittest($f);
		}else if(class_exists($f=((substr($class_name,0,1) != "\\") ? "\\" : '').str_replace('.',"\\",$class_name),true) || interface_exists($f,true) || (function_exists('trait_exists') && trait_exists($f,true))){
			$doctest = self::get_doctest($f);
		}else if(function_exists($f)){
			$doctest = self::get_func_doctest($f);
		}else{
			throw new ErrorException($class_name.' class not found');
		}
		self::$current_file = $doctest['filename'];
		self::$current_class = ($doctest['type'] == 1) ? $doctest['name'] : null;
		self::$current_entry = ($doctest['type'] == 2 || $doctest['type'] == 3) ? $doctest['name'] : null;
		self::$current_method = null;

		foreach($doctest['tests'] as $test_method_name => $tests){
			if($method_name === null || $method_name === $test_method_name){
				self::$current_method = $test_method_name;

				if(empty($tests['blocks'])){
					self::$result[self::$current_file][self::$current_class][self::$current_method][$tests['line']][] = array(self::current_block_info(),'none');
				}else{
					foreach($tests['blocks'] as $test_block){
						list($name,$label,$block) = $test_block;
						$exec_block_name = ' #'.(($class_name == $name) ? '' : $name);
						self::$current_block_name = $name;
						self::$current_block_label = $label;
						self::$current_block_start_time = microtime(true);

						if($block_name === null || $block_name === $name){
							if($print_progress && substr(PHP_OS,0,3) != 'WIN') self::stdout($exec_block_name);
							try{
								ob_start();
								if($doctest['type'] == 3){
									self::include_setup_teardown($doctest['filename'],'__setup__.php');
									include($doctest['filename']);
									self::include_setup_teardown($doctest['filename'],'__teardown__.php');
								}else{
									if(isset($doctest['tests']['@']['__setup__'])) eval($doctest['tests']['@']['__setup__'][2]);
									eval($block);
									if(isset($doctest['tests']['@']['__teardown__'])) eval($doctest['tests']['@']['__teardown__'][2]);
								}
								$result = ob_get_clean();
								if(preg_match("/(Parse|Fatal) error:.+/",$result,$match)){
									$err = (preg_match('/syntax error.+code on line\s*(\d+)/',$result,$line) ?
												'Parse error: syntax error '.$doctest['filename'].' code on line '.$line[1]
												: $match[0]);
									throw new ErrorException($err);
								}
							}catch(Exception $e){
								if(ob_get_level() > 0) $result = ob_get_clean();
								list($message,$file,$line) = array($e->getMessage(),$e->getFile(),$e->getLine());
								$trace = $e->getTrace();
								$eval = false;

								foreach($trace as $k => $t){
									if(isset($t['class']) && isset($t['function']) && ($t['class'].'::'.$t['function']) == __METHOD__ && isset($trace[$k-2])
										&& isset($trace[$k-1]['file']) && $trace[$k-1]['file'] == __FILE__ && isset($trace[$k-1]['function']) && $trace[$k-1]['function'] == 'eval'
									){
										$file = self::$current_file;
										$line = $trace[$k-2]['line'];
										$eval = true;
										break;
									}
								}
								if(!$eval && isset($trace[0]['file']) && self::$current_file == $trace[0]['file']){
									$file = $trace[0]['file'];
									$line = $trace[0]['line'];
								}
								self::$result[self::$current_file][self::$current_class][self::$current_method][$line][] = array(self::current_block_info(),'exception',$message,$file,$line);
							}
							if($print_progress && substr(PHP_OS,0,3) != 'WIN') self::stdout("\033[".strlen($exec_block_name).'D'."\033[0K");
						}
						self::$current_block_name = self::$current_block_label = null;
					}
				}
			}
		}
		if($include_tests && ($doctest['type'] == 1 || $doctest['type'] == 2)){
			$test_name = ($doctest['type'] == 1) ? str_replace("\\",'/',substr($doctest['name'],1)) : $doctest['name'];
			if(!empty($test_name) && is_dir($d=($tests_path.str_replace(array('.'),'/',$test_name)))){
				foreach(new RecursiveDirectoryIterator($d,FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS|FilesystemIterator::UNIX_PATHS) as $e){
					if(substr($e->getFilename(),-4) == '.php' && strpos($e->getPathname(),'/.') === false && strpos($e->getPathname(),'/_') === false
						&& ($block_name === null || $block_name === substr($e->getFilename(),0,-4) || $block_name === $e->getFilename())
					){
						self::run($e->getPathname(),null,null,$print_progress,$include_tests);
					}
				}
			}
		}
		return new self();
	}
	static private function include_setup_teardown($test_file,$include_file){
		if(strpos($test_file,self::$test_dir) === 0){
			if(is_file(self::$test_dir.'__funcs__.php')) include_once(self::$test_dir.'__funcs__.php');
			$inc = array();
			$dir = dirname($test_file);
			while($dir.'/' != self::$test_dir){
				if(is_file($f=($dir.'/'.$include_file))) array_unshift($inc,$f);
				$dir = dirname($dir);
			}
			if(is_file($f=(self::$test_dir.$include_file))) array_unshift($inc,$f);
			foreach($inc as $i) include($i);
		}else if(is_file($f=(dirname($test_file).'/__setup__.php'))){
			include($f);
		}
	}
	public function __toString(){
		$result = '';
		$tab = '  ';
		$success = $fail = $none = 0;

		foreach(self::$result as $file => $f){
			foreach($f as $class => $c){
				$print_head = false;

				foreach($c as $method => $m){
					foreach($m as $line => $r){
						foreach($r as $l){
							$info = array_shift($l);
							switch(sizeof($l)){
								case 0:
									$success++;
									break;
								case 1:
									$none++;
									break;
								case 2:
									$fail++;
									if(!$print_head){
										$result .= "\n";
										$result .= (empty($class) ? "*****" : str_replace("\\",'.',(substr($class,0,1) == "\\") ? substr($class,1) : $class))." [ ".$file." ]\n";
										$result .= str_repeat("-",80)."\n";
										$print_head = true;
									}
									$result .= "[".$line."]".$method.": ".self::fcolor("fail","1;31")."\n";
									$result .= $tab.str_repeat("=",70)."\n";
									ob_start();
										var_dump($l[0]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"33");
									ob_end_clean();
									$result .= "\n".$tab.str_repeat("=",70)."\n";

									ob_start();
										var_dump($l[1]);
										$result .= self::fcolor($tab.str_replace("\n","\n".$tab,ob_get_contents()),"31");
									ob_end_clean();
									$result .= "\n".$tab.str_repeat("=",70)."\n";
									break;
								case 4:
									$fail++;
									if(!$print_head){
										$result .= "\n";
										$result .= (empty($class) ? "*****" : str_replace("\\",'.',(substr($class,0,1) == "\\") ? substr($class,1) : $class))." [ ".$file." ]\n";
										$result .= str_repeat("-",80)."\n";
										$print_head = true;
									}
									$color = ($l[0] == 'exception' || $l[0] == 'fail') ? 31 : 34;
									$result .= "[".$line."]".$method.": ".self::fcolor($l[0],"1;".$color)."\n";
									$result .= $tab.str_repeat("=",70)."\n";
									$result .= self::fcolor($tab.$l[1]."\n\n".$tab.$l[2].":".$l[3],$color);
									$result .= "\n".$tab.str_repeat("=",70)."\n";
									break;
							}
						}
					}
				}
			}
		}
		$result .= "\n";
		$result .= self::fcolor(" success: ".$success." ","7;32")." ".self::fcolor(" fail: ".$fail." ","7;31")." ".self::fcolor(" none: ".$none." ","7;35")
					.sprintf(' ( %s sec / %s MByte) ',round((microtime(true) - (float)self::start_time()),4),round(number_format((memory_get_usage() / 1024 / 1024),3),2));
		$result .= "\n";
		return $result;
	}
	static private function get_unittest($filename){
		$result = array();
		$result['@']['line'] = 0;
		$result['@']['blocks'][] = array($filename,null,$filename,0);
		$name = (preg_match("/^".preg_quote(self::$test_dir,'/')."(.+)\/[^\/]+\.php$/",$filename,$match)) ? $match[1] : null;
		return array('filename'=>$filename,'type'=>3,'name'=>$name,'tests'=>$result);
	}
	static private function get_entry_doctest($filename){
		$result = array();
		$entry = basename($filename,'.php');
		$src = file_get_contents($filename);
		if(preg_match_all("/\/\*\*"."\*.+?\*\//s",$src,$doctests,PREG_OFFSET_CAPTURE)){
			foreach($doctests[0] as $doctest){
				if(isset($doctest[0][5]) && $doctest[0][5] != '*'){
					$test_start_line = sizeof(explode("\n",substr($src,0,$doctest[1]))) - 1;
					$test_block = str_repeat("\n",$test_start_line).preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array("/"."***","*"."/"),"",$doctest[0]));
					$test_block_name = preg_match("/^[\s]*#([^#].*)/",trim($test_block),$match) ? trim($match[1]) : null;
					$test_block_label = preg_match("/^[\s]*##(.+)/m",trim($test_block),$match) ? trim($match[1]) : null;
					if(trim($test_block) == '') $test_block = null;
					$result['@']['line'] = $test_start_line;
					$result['@']['blocks'][] = array($test_block_name,$test_block_label,$test_block,$test_start_line);
				}
			}
			self::merge_setup_teardown($result);
		}
		return array('filename'=>$filename,'type'=>2,'name'=>$entry,'tests'=>$result);
	}
	static private function get_func_doctest($func_name){
		$result = array();
		$r = new ReflectionFunction($func_name);
		$filename = ($r->getFileName() === false) ? $func_name : $r->getFileName();

		if(is_string($r->getFileName())){
			$src_lines = file($filename);
			$func_src = implode('',array_slice($src_lines,$r->getStartLine()-1,$r->getEndLine()-$r->getStartLine(),true));
	
			if(preg_match_all("/\/\*\*"."\*.+?\*\//s",$func_src,$doctests,PREG_OFFSET_CAPTURE)){
				foreach($doctests[0] as $doctest){
					if(isset($doctest[0][5]) && $doctest[0][5] != "*"){
						$test_start_line = $r->getStartLine() + substr_count(substr($func_src,0,$doctest[1]),"\n") - 1;
						$test_block = str_repeat("\n",$test_start_line).preg_replace("/([^\w_])self\(/ms","\\1".$func_name.'(',preg_replace("/^[\s]*\*[\s]{0,1}/m",'',str_replace(array("/"."***","*"."/"),"",$doctest[0])));
						$test_block_name = preg_match("/^[\s]*#([^#].*)/",trim($test_block),$match) ? trim($match[1]) : null;
						$test_block_label = preg_match("/^[\s]*##(.+)/m",trim($test_block),$match) ? trim($match[1]) : null;
						if(trim($test_block) == '') $test_block = null;
						$result[$func_name]['line'] = $r->getStartLine();
						$result[$func_name]['blocks'][] = array($test_block_name,$test_block_label,$test_block,$test_start_line);
					}
				}
			}else if($func_name[0] != '_'){
				$result[$func_name]['line'] = $r->getStartLine();
				$result[$func_name]['blocks'] = array();
			}
		}
		return array('filename'=>$filename,'type'=>4,'name'=>null,'tests'=>$result);
	}
	static private function get_doctest($class_name){
		$result = array();
		$rc = new ReflectionClass($class_name);
		$filename = $rc->getFileName();
		$class_src_lines = file($filename);
		$class_src = implode('',$class_src_lines);

		foreach($rc->getMethods() as $method){
			if($method->getDeclaringClass()->getName() == $rc->getName()){
				$method_src = implode('',array_slice($class_src_lines,$method->getStartLine()-1,$method->getEndLine()-$method->getStartLine(),true));
				$result = array_merge($result,self::get_method_doctest($rc->getName(),$method->getName(),$method->getStartLine(),$method->isPublic(),$method_src));
				$class_src = str_replace($method_src,str_repeat("\n",sizeof(explode("\n",$method_src)) - 1),$class_src);
			}
		}
		$result = array_merge($result,self::get_method_doctest($rc->getName(),'@',1,false,$class_src));
		self::merge_setup_teardown($result);
		return array('filename'=>$filename,'type'=>1,'name'=>$rc->getName(),'tests'=>$result);
	}
	static private function merge_setup_teardown(&$result){
		if(isset($result['@']['blocks'])){
			foreach($result['@']['blocks'] as $k => $block){
				if($block[0] == '__setup__' || $block[0] == '__teardown__'){
					$result['@'][$block[0]] = array($result['@']['blocks'][$k][3],null,$result['@']['blocks'][$k][2]);
					unset($result['@']['blocks'][$k]);
				}
			}
		}
	}
	static private function get_method_doctest($class_name,$method_name,$method_start_line,$is_public,$method_src){
		$result = array();
		if(preg_match_all("/\/\*\*"."\*.+?\*\//s",$method_src,$doctests,PREG_OFFSET_CAPTURE)){
			foreach($doctests[0] as $doctest){
				if(isset($doctest[0][5]) && $doctest[0][5] != "*"){
					$test_start_line = $method_start_line + substr_count(substr($method_src,0,$doctest[1]),"\n") - 1;
					$test_block = str_repeat("\n",$test_start_line).str_replace(array('self::','new self(','extends self{'),array($class_name.'::','new '.$class_name.'(','extends '.$class_name.'{'),preg_replace("/^[\s]*\*[\s]{0,1}/m","",str_replace(array("/"."***","*"."/"),"",$doctest[0])));
					$test_block_name = preg_match("/^[\s]*#([^#].*)/",trim($test_block),$match) ? trim($match[1]) : null;
					$test_block_label = preg_match("/^[\s]*##(.+)/m",trim($test_block),$match) ? trim($match[1]) : null;
					if(trim($test_block) == '') $test_block = null;
					$result[$method_name]['line'] = $method_start_line;
					$result[$method_name]['blocks'][] = array($test_block_name,$test_block_label,$test_block,$test_start_line);
				}
			}
		}else if($is_public && $method_name[0] != '_'){
			$result[$method_name]['line'] = $method_start_line;
			$result[$method_name]['blocks'] = array();
		}
		return $result;
	}
	/**
	 * URL情報
	 * @return array
	 */
	static public function urls(){
		return (isset(self::$urls)) ? self::$urls : array();
	}
	/**
	 * URL情報を定義する
	 * @param array $urls
	 */
	static public function set_urls(array $urls){
		if(!isset(self::$urls)) self::$urls = $urls;
	}
	
	/**
	 * テスト結果をXMLで取得する
	 */
	static public function xml($name=null,$system_err=null){
		$xml = new Testman_Xml('testsuites');
		if(!empty($name)) $xml->attr('name',$name);

		$count = $success = $fail = $none = $exception = 0;
		foreach(self::get() as $file => $f){
			$case = new Testman_Xml('testsuite');
			$case->close_empty(false);
			$case->attr('name',substr(basename($file),0,-4));
			$case->attr('file',$file);

			foreach($f as $class => $c){
				foreach($c as $method => $m){
					foreach($m as $line => $r){
						foreach($r as $l){
							$info = array_shift($l);
							$name = (($method != '@' && $method != $file) ? $method : '');
							$name .= (empty($name) ? '' : '_').((!empty($info[1]) && $info[1] != $file) ? $info[1] : ((!empty($info[0]) && $info[0] != $file) ? $info[0] : ''));
							$count++;
							$x = new Testman_Xml('testcase');
							$x->attr('name',$line.(empty($name) ? '' : '_').str_replace('\\','',$name));
							$x->attr('class',$class);
							$x->attr('file',$file);
							$x->attr('line',$line);
							$x->attr('time',$info[2]);
							
							switch(sizeof($l)){
								case 0:
									$success++;
									$case->add($x);
									break;
								case 1:
									$none++;
									break;
								case 2:
									$fail++;
									$failure = new Testman_Xml('failure');
									$failure->attr('line',$line);
									ob_start();
										var_dump($l[1]);
									$failure->value('Line. '.$line.' '.$method.': '."\n".ob_get_clean());
									$x->add($failure);
									$case->add($x);
									break;
								case 4:
									$exception++;
									$error = new Testman_Xml('error');
									$error->attr('line',$line);
									$error->value(
											'Line. '.$line.' '.$method.': '.$l[0]."\n".
											$l[1]."\n\n".$l[2].':'.$l[3]
									);
									$x->add($error);
									$case->add($x);
									break;
							}
						}
					}
				}
			}
			$xml->add($case);
		}
		$xml->attr('failures',$fail);
		$xml->attr('tests',$count);
		$xml->attr('errors',$exception);
		$xml->attr('skipped',$none);
		$xml->attr('time',round((microtime(true) - (float)self::start_time()),4));
		$xml->add(new Testman_Xml('system-out'));
		$xml->add(new Testman_Xml('system-err',$system_err));
		return $xml;
	}
	static private function expvar($var){
		if(is_numeric($var)) return strval($var);
		if(is_object($var)) $var = get_object_vars($var);
		if(is_array($var)){
			foreach($var as $key => $v){
				$var[$key] = self::expvar($v);
			}
		}
		return $var;
	}
	static private function fcolor($msg,$color='30'){
		return (php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN') ? "\033[".$color."m".$msg."\033[0m" : $msg;
	}
		
	static public function verify_format($class_name,$m=null,$b=null,$include_tests=false,$print=null){
		if(isset($print)) self::$disp = (boolean)$print;
		$f = ' '.$class_name.(isset($m) ? '::'.$m : '');
		self::stdout($f);
		$throw = null;
		$starttime = microtime(true);
		try{
			self::run($class_name,$m,$b,true,$include_tests);
		}catch(Exception $e){
			$throw = $e;
		}
		self::stdout('('.round((microtime(true) - (float)$starttime),4).' sec)'.PHP_EOL);
		if(isset($throw)) throw $throw;
		Testman_Coverage::save(true);
	}
	static public function error_print($msg,$color='1;31'){
		self::stdout(((php_sapi_name() == 'cli' && substr(PHP_OS,0,3) != 'WIN') ? "\033[".$color."m".$msg."\033[0m" : $msg).PHP_EOL);
	}
	static public function stdout($v){
		if(self::$disp) print($v);
	}
}
?><?php
/**
 * XMLを処理する
 * @author tokushima
 */
class Testman_Xml implements IteratorAggregate{
	private $attr = array();
	private $plain_attr = array();
	private $name;
	private $value;
	private $close_empty = true;

	private $plain;
	private $pos;
	private $esc = true;

	public function __construct($name=null,$value=null){
		if($value === null && is_object($name)){
			$n = explode('\\',get_class($name));
			$this->name = array_pop($n);
			$this->value($name);
		}else{
			$this->name = trim($name);
			$this->value($value);
		}
	}
	/**
	 * (non-PHPdoc)
	 * @see IteratorAggregate::getIterator()
	 */
	public function getIterator(){
		return new ArrayIterator($this->attr);
	}
	/**
	 * 値が無い場合は閉じを省略する
	 * @param boolean
	 * @return boolean
	 */
	final public function close_empty(){
		if(func_num_args() > 0) $this->close_empty = (boolean)func_get_arg(0);
		return $this->close_empty;
	}
	/**
	 * エスケープするか
	 * @param boolean $bool
	 */
	final public function escape($bool){
		$this->esc = (boolean)$bool;
		return $this;
	}
	/**
	 * setできた文字列
	 * @return string
	 */
	final public function plain(){
		return $this->plain;
	}
	/**
	 * 子要素検索時のカーソル
	 * @return integer
	 */
	final public function cur(){
		return $this->pos;
	}
	/**
	 * 要素名
	 * @return string
	 */
	final public function name($name=null){
		if(isset($name)) $this->name = $name;
		return $this->name;
	}
	private function get_value($v){
		if($v instanceof self){
			$v = $v->get();
		}else if(is_bool($v)){
			$v = ($v) ? 'true' : 'false';
		}else if($v === ''){
			$v = null;
		}else if(is_array($v) || is_object($v)){
			$r = '';
			foreach($v as $k => $c){
				if(is_numeric($k) && is_object($c)){
					$e = explode('\\',get_class($c));
					$k = array_pop($e);
				}
				if(is_numeric($k)) $k = 'data';
				$x = new self($k,$c);
				$x->escape($this->esc);
				$r .= $x->get();
			}
			$v = $r;
		}else if($this->esc && strpos($v,'<![CDATA[') === false && preg_match("/&|<|>|\&[^#\da-zA-Z]/",$v)){
			$v = '<![CDATA['.$v.']]>';
		}
		return $v;
	}
	/**
	 * 値を設定、取得する
	 * @param mixed
	 * @param boolean
	 * @return string
	 */
	final public function value(){
		if(func_num_args() > 0) $this->value = $this->get_value(func_get_arg(0));
		if(strpos($this->value,'<![CDATA[') === 0) return substr($this->value,9,-3);
		return $this->value;
	}
	/**
	 * 値を追加する
	 * ２つ目のパラメータがあるとアトリビュートの追加となる
	 * @param mixed $arg
	 */
	final public function add($arg){
		if(func_num_args() == 2){
			$this->attr(func_get_arg(0),func_get_arg(1));
		}else{
			$this->value .= $this->get_value(func_get_arg(0));
		}
		return $this;
	}
	/**
	 * アトリビュートを取得する
	 * @param string $n 取得するアトリビュート名
	 * @param string $d アトリビュートが存在しない場合の代替値
	 * @return string
	 */
	final public function in_attr($n,$d=null){
		return isset($this->attr[strtolower($n)]) ? ($this->esc ? htmlentities($this->attr[strtolower($n)],ENT_QUOTES,'UTF-8') : $this->attr[strtolower($n)]) : (isset($d) ? (string)$d : null);
	}
	/**
	 * アトリビュートから削除する
	 * パラメータが一つも無ければ全件削除
	 */
	final public function rm_attr(){
		if(func_num_args() === 0){
			$this->attr = array();
		}else{
			foreach(func_get_args() as $n) unset($this->attr[$n]);
		}
	}
	/**
	 * アトリビュートがあるか
	 * @param string $name
	 * @return boolean
	 */
	final public function is_attr($name){
		return array_key_exists($name,$this->attr);
	}
	/**
	 * アトリビュートを設定
	 * @return self $this
	 */
	final public function attr($key,$value){
		$this->attr[strtolower($key)] = is_bool($value) ? (($value) ? 'true' : 'false') : $value;
		return $this;
	}
	/**
	 * 値の無いアトリビュートを設定
	 * @param string $v
	 */
	final public function plain_attr($v){
		$this->plain_attr[] = $v;
	}
	/**
	 * XML文字列を返す
	 */
	public function get($encoding=null){
		if($this->name === null) throw new LogicException('undef name');
		$attr = '';
		$value = ($this->value === null || $this->value === '') ? null : (string)$this->value;
		foreach($this->attr as $k => $v) $attr .= ' '.$k.'="'.$this->in_attr($k).'"';
		return ((empty($encoding)) ? '' : '<?xml version="1.0" encoding="'.$encoding.'" ?'.'>'.PHP_EOL)
				.('<'.$this->name.$attr.(implode(' ',$this->plain_attr)).(($this->close_empty && !isset($value)) ? ' /' : '').'>')
				.$this->value
				.((!$this->close_empty || isset($value)) ? sprintf('</%s>',$this->name) : '');
	}
	public function __toString(){
		return $this->get();
	}
	/**
	 * 文字列からXMLを探す
	 * @param mixed $x 見つかった場合にインスタンスがセットされる
	 * @param string $plain 対象の文字列
	 * @param string $name 要素名
	 * @return boolean
	 */
	static public function set(&$x,$plain,$name=null){
		return self::_set($x,$plain,$name);
	}
	static private function _set(&$x,$plain,$name=null,$vtag=null){
		$plain = (string)$plain;
		$name = (string)$name;
		if(empty($name) && preg_match("/<([\w\:\-]+)[\s][^>]*?>|<([\w\:\-]+)>/is",$plain,$m)){
			$name = str_replace(array("\r\n","\r","\n"),'',(empty($m[1]) ? $m[2] : $m[1]));
		}
		$qname = preg_quote($name,'/');
		if(!preg_match("/<(".$qname.")([\s][^>]*?)>|<(".$qname.")>/is",$plain,$parse,PREG_OFFSET_CAPTURE)) return false;
		$x = new self();
		$x->pos = $parse[0][1];
		$balance = 0;
		$attrs = '';

		if(substr($parse[0][0],-2) == '/>'){
			$x->name = $parse[1][0];
			$x->plain = empty($vtag) ? $parse[0][0] : preg_replace('/'.preg_quote(substr($vtag,0,-1).' />','/').'/',$vtag,$parse[0][0],1);
			$attrs = $parse[2][0];
		}else if(preg_match_all("/<[\/]{0,1}".$qname."[\s][^>]*[^\/]>|<[\/]{0,1}".$qname."[\s]*>/is",$plain,$list,PREG_OFFSET_CAPTURE,$x->pos)){
			foreach($list[0] as $arg){
				if(($balance += (($arg[0][1] == '/') ? -1 : 1)) <= 0 &&
						preg_match("/^(<(".$qname.")([\s]*[^>]*)>)(.*)(<\/\\2[\s]*>)$/is",
							substr($plain,$x->pos,($arg[1] + strlen($arg[0]) - $x->pos)),
							$match
						)
				){
					$x->plain = $match[0];
					$x->name = $match[2];
					$x->value = ($match[4] === '' || $match[4] === null) ? null : $match[4];
					$attrs = $match[3];
					break;
				}
			}
			if(!isset($x->plain)){
				return self::_set($x,preg_replace('/'.preg_quote($list[0][0][0],'/').'/',substr($list[0][0][0],0,-1).' />',$plain,1),$name,$list[0][0][0]);
			}
		}
		if(!isset($x->plain)) return false;
		if(!empty($attrs)){
			if(preg_match_all("/[\s]+([\w\-\:]+)[\s]*=[\s]*([\"\'])([^\\2]*?)\\2/ms",$attrs,$attr)){
				foreach($attr[0] as $id => $value){
					$x->attr($attr[1][$id],$attr[3][$id]);
					$attrs = str_replace($value,'',$attrs);
				}
			}
			if(preg_match_all("/([\w\-]+)/",$attrs,$attr)){
				foreach($attr[1] as $v) $x->attr($v,$v);
			}
		}
		return true;
	}
	/**
	 * 指定の要素を検索する
	 * @param string $tag_name 要素名
	 * @param integer $offset 開始位置
	 * @param integer $length 取得する最大数
	 * @return XmlIterator
	 */
	public function in($name,$offset=0,$length=0){
		return new Testman_Xml_XmlIterator($name,$this->value(),$offset,$length);
	}
	/**
	 * パスで検索する
	 * @param string $path 検索文字列
	 * @return mixed
	 */
	public function f($path){
		$arg = (func_num_args() == 2) ? func_get_arg(1) : null;
		$paths = explode('.',$path);
		$last = (strpos($path,'(') === false) ? null : array_pop($paths);
		$tag = clone($this);
		$route = array();
		if($arg !== null) $arg = (is_bool($arg)) ? (($arg) ? 'true' : 'false') : strval($arg);

		foreach($paths as $p){
			$pos = 0;
			$t = null;
			if(preg_match("/^(.+)\[([\d]+?)\]$/",$p,$matchs)) list($tmp,$p,$pos) = $matchs;
			foreach($tag->in($p,$pos,1) as $t);
			if(!isset($t) || !($t instanceof self)){
				$tag = null;
				break;
			}
			$route[] = $tag = $t;
		}
		if($tag instanceof self){
			if($arg === null){
				switch($last){
					case '': return $tag;
					case 'plain()': return $tag->plain();
					case 'value()': return $tag->value();
					default:
						if(preg_match("/^(attr|in)\((.+?)\)$/",$last,$matchs)){
							list($null,$type,$name) = $matchs;
							if($type == 'in'){
								return $tag->in(trim($name));
							}else if($type == 'attr'){
								return $tag->in_attr($name);
							}
						}
						return null;
				}
			}
			if($arg instanceof self) $arg = $arg->get();
			if(is_bool($arg)) $arg = ($arg) ? 'true' : 'false';
			krsort($route,SORT_NUMERIC);
			$ltag = $rtag = $replace = null;
			$f = true;

			foreach($route as $r){
				$ltag = clone($r);
				if($f){
					switch($last){
						case 'value()':
							$replace = $arg;
							break;
						default:
							if(preg_match("/^(attr)\((.+?)\)$/",$last,$matchs)){
								list($null,$type,$name) = $matchs;
								if($type == 'attr'){
									$r->attr($name,$arg);
									$replace = $r->get();
								}else{
									return null;
								}
							}
					}
					$f = false;
				}
				$r->value(empty($rtag) ? $replace : str_replace($rtag->plain(),$replace,$r->value()));
				$replace = $r->get();
				$rtag = clone($ltag);
			}
			$this->value(str_replace($ltag->plain(),$replace,$this->value()));
			return null;
		}
		return (!empty($last) && substr($last,0,2) == 'in') ? array() : null;
	}
	/**
	 * idで検索する
	 *
	 * @param string $name 指定のID
	 * @return self
	 */
	public function id($name){
		if(preg_match("/<.+[\s]*id[\s]*=[\s]*([\"\'])".preg_quote($name)."\\1/",$this->value(),$match,PREG_OFFSET_CAPTURE)){
			if(self::set($tag,substr($this->value(),$match[0][1]))) return $tag;
		}
		return null;
	}
	/**
	 * xmlとし出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function output($encoding=null,$name=null){
		header(sprintf('Content-Type: application/xml%s',(empty($name) ? '' : sprintf('; name=%s',$name))));
		print($this->get($encoding));
		exit;
	}
	/**
	 * attachmentとして出力する
	 * @param string $encoding エンコード名
	 * @param string $name ファイル名
	 */
	public function attach($encoding=null,$name=null){
		header(sprintf('Content-Disposition: attachment%s',(empty($name) ? '' : sprintf('; filename=%s',$name))));
		$this->output($encoding,$name);
	}
}
?><?php
class Testman_Xml_XmlIterator implements Iterator{
	private $name = null;
	private $plain = null;
	private $tag = null;
	private $offset = 0;
	private $length = 0;
	private $count = 0;

	public function __construct($tag_name,$value,$offset,$length){
		$this->name = $tag_name;
		$this->plain = $value;
		$this->offset = $offset;
		$this->length = $length;
		$this->count = 0;
	}
	public function key(){
		$this->tag->name();
	}
	public function current(){
		$this->plain = substr($this->plain,0,$this->tag->cur()).substr($this->plain,$this->tag->cur() + strlen($this->tag->plain()));
		$this->count++;
		return $this->tag;
	}
	public function valid(){
		if($this->length > 0 && ($this->offset + $this->length) <= $this->count) return false;
		if(is_array($this->name)){
			$tags = array();
			foreach($this->name as $name){
				if(Testman_Xml::set($get_tag,$this->plain,$name)) $tags[$get_tag->cur()] = $get_tag;
			}
			if(empty($tags)) return false;
			ksort($tags,SORT_NUMERIC);
			foreach($tags as $this->tag) return true;
		}
		return Testman_Xml::set($this->tag,$this->plain,$this->name);
	}
	public function next(){
	}
	public function rewind(){
		for($i=0;$i<$this->offset;$i++){
			$this->valid();
			$this->current();
		}
	}
}
?><?php
$idir = dirname(__DIR__);
include_once($idir.'/init.php');
include_once($idir.'/Testman_Xml_XmlIterator.php');
include_once($idir.'/Testman_Xml.php');
include_once($idir.'/Testman_Template.php');
include_once($idir.'/Testman_Template_Helper.php');
include_once($idir.'/Testman_Path.php');
include_once($idir.'/Testman_Coverage.php');

if(isset($_GET['coverage_client'])){
	header('Content-Type: text/plain');
	header('Content-Disposition: attachment; filename="coverage_client.php"');
	print(file_get_contents(dirname(__DIR__).'/testman_coverage_client.php'));
	exit;
}
$dblist = array();
if(is_dir($report_dir)){
	foreach(new RecursiveDirectoryIterator(
			$report_dir,
			FilesystemIterator::CURRENT_AS_FILEINFO|FilesystemIterator::SKIP_DOTS|FilesystemIterator::UNIX_PATHS
	) as $e){
		if(preg_match('/^.+\.report$/',$e->getFilename())){
			$db_path = str_replace($report_dir.'/','',$e->getFilename());
			$dblist[$db_path] = $e->getMTime();
		}
	}
	if(!empty($dblist)){
		arsort($dblist);
		$dblist = array_combine(array_keys($dblist),array_keys($dblist));
	}
}
try{
	$uri = (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '');
	$uri = preg_replace('/^(.+\/)[^\/]+\.php$/','\\1',preg_replace('/^(.+)\?.*$/','\\1',$uri));
	$db = $in_value('db');
	if(empty($db) && !empty($dblist)) $db = current($dblist);
	$db_file = Testman_Path::absolute($report_dir,$db);	
	$template = new Testman_Template(Testman_Path::absolute($uri,'media'));

	switch($in_value('view_mode')){
		case 'source':
			if(isset($params['file']) && !empty($params['file'])){
				$template->vars('info',Testman_Coverage::file($db_file,$in_value('file')));
				$template->vars('file',$in_value('file'));
				$template_file = 'source.html';
			}
			break;
		case 'result':
			list($success,$fail,$none,$failure) = Testman_Coverage::test_result($db_file);
			$template->vars('success',$success);
			$template->vars('fail',$fail);
			$template->vars('none',$none);
			$template->vars('failure',$failure);
			$template_file = 'test_result.html';
			break;
		case 'all':
			list($file_list,$avg) = Testman_Coverage::all_file_list($db_file);
			$template->vars('file_list',$file_list);
			$template->vars('avg',$avg);	
			$template_file = 'coverage.html';
			break;
		case 'tree':
			$path = $in_value('path');
			list($dir_list,$file_list,$parent_path,$avg) = Testman_Coverage::dir_list($db_file,$path);
			
			$template->vars('dir_list',$dir_list);
			$template->vars('file_list',$file_list);
			$template->vars('parent_path',$parent_path);
			$template->vars('avg',$avg);
			$template->vars('path',$path);
			$template_file = 'coverage.html';
			break;
		case 'help':
			$template_file = 'help.html';
			break;
	}
	if(empty($template_file)) $template_file = 'top.html';
	$template->vars('t',new Testman_Template_Helper());
	$template->vars('dblist',$dblist);
	$template->vars('db',$db);
	$template->vars('view_mode',$in_value('view_mode'));
	$template->output(dirname(__DIR__).'/templates/'.$template_file);
}catch(Exception $e){
	die($e->getMessage());
}
?>/*!
 * Bootstrap Responsive v2.2.0
 *
 * Copyright 2012 Twitter, Inc
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Designed and built with all the love in the world @twitter by @mdo and @fat.
 */.clearfix{*zoom:1}.clearfix:before,.clearfix:after{display:table;line-height:0;content:""}.clearfix:after{clear:both}.hide-text{font:0/0 a;color:transparent;text-shadow:none;background-color:transparent;border:0}.input-block-level{display:block;width:100%;min-height:30px;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.hidden{display:none;visibility:hidden}.visible-phone{display:none!important}.visible-tablet{display:none!important}.hidden-desktop{display:none!important}.visible-desktop{display:inherit!important}@media(min-width:768px) and (max-width:979px){.hidden-desktop{display:inherit!important}.visible-desktop{display:none!important}.visible-tablet{display:inherit!important}.hidden-tablet{display:none!important}}@media(max-width:767px){.hidden-desktop{display:inherit!important}.visible-desktop{display:none!important}.visible-phone{display:inherit!important}.hidden-phone{display:none!important}}@media(min-width:1200px){.row{margin-left:-30px;*zoom:1}.row:before,.row:after{display:table;line-height:0;content:""}.row:after{clear:both}[class*="span"]{float:left;min-height:1px;margin-left:30px}.container,.navbar-static-top .container,.navbar-fixed-top .container,.navbar-fixed-bottom .container{width:1170px}.span12{width:1170px}.span11{width:1070px}.span10{width:970px}.span9{width:870px}.span8{width:770px}.span7{width:670px}.span6{width:570px}.span5{width:470px}.span4{width:370px}.span3{width:270px}.span2{width:170px}.span1{width:70px}.offset12{margin-left:1230px}.offset11{margin-left:1130px}.offset10{margin-left:1030px}.offset9{margin-left:930px}.offset8{margin-left:830px}.offset7{margin-left:730px}.offset6{margin-left:630px}.offset5{margin-left:530px}.offset4{margin-left:430px}.offset3{margin-left:330px}.offset2{margin-left:230px}.offset1{margin-left:130px}.row-fluid{width:100%;*zoom:1}.row-fluid:before,.row-fluid:after{display:table;line-height:0;content:""}.row-fluid:after{clear:both}.row-fluid [class*="span"]{display:block;float:left;width:100%;min-height:30px;margin-left:2.564102564102564%;*margin-left:2.5109110747408616%;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.row-fluid [class*="span"]:first-child{margin-left:0}.row-fluid .controls-row [class*="span"]+[class*="span"]{margin-left:2.564102564102564%}.row-fluid .span12{width:100%;*width:99.94680851063829%}.row-fluid .span11{width:91.45299145299145%;*width:91.39979996362975%}.row-fluid .span10{width:82.90598290598291%;*width:82.8527914166212%}.row-fluid .span9{width:74.35897435897436%;*width:74.30578286961266%}.row-fluid .span8{width:65.81196581196582%;*width:65.75877432260411%}.row-fluid .span7{width:57.26495726495726%;*width:57.21176577559556%}.row-fluid .span6{width:48.717948717948715%;*width:48.664757228587014%}.row-fluid .span5{width:40.17094017094017%;*width:40.11774868157847%}.row-fluid .span4{width:31.623931623931625%;*width:31.570740134569924%}.row-fluid .span3{width:23.076923076923077%;*width:23.023731587561375%}.row-fluid .span2{width:14.52991452991453%;*width:14.476723040552828%}.row-fluid .span1{width:5.982905982905983%;*width:5.929714493544281%}.row-fluid .offset12{margin-left:105.12820512820512%;*margin-left:105.02182214948171%}.row-fluid .offset12:first-child{margin-left:102.56410256410257%;*margin-left:102.45771958537915%}.row-fluid .offset11{margin-left:96.58119658119658%;*margin-left:96.47481360247316%}.row-fluid .offset11:first-child{margin-left:94.01709401709402%;*margin-left:93.91071103837061%}.row-fluid .offset10{margin-left:88.03418803418803%;*margin-left:87.92780505546462%}.row-fluid .offset10:first-child{margin-left:85.47008547008548%;*margin-left:85.36370249136206%}.row-fluid .offset9{margin-left:79.48717948717949%;*margin-left:79.38079650845607%}.row-fluid .offset9:first-child{margin-left:76.92307692307693%;*margin-left:76.81669394435352%}.row-fluid .offset8{margin-left:70.94017094017094%;*margin-left:70.83378796144753%}.row-fluid .offset8:first-child{margin-left:68.37606837606839%;*margin-left:68.26968539734497%}.row-fluid .offset7{margin-left:62.393162393162385%;*margin-left:62.28677941443899%}.row-fluid .offset7:first-child{margin-left:59.82905982905982%;*margin-left:59.72267685033642%}.row-fluid .offset6{margin-left:53.84615384615384%;*margin-left:53.739770867430444%}.row-fluid .offset6:first-child{margin-left:51.28205128205128%;*margin-left:51.175668303327875%}.row-fluid .offset5{margin-left:45.299145299145295%;*margin-left:45.1927623204219%}.row-fluid .offset5:first-child{margin-left:42.73504273504273%;*margin-left:42.62865975631933%}.row-fluid .offset4{margin-left:36.75213675213675%;*margin-left:36.645753773413354%}.row-fluid .offset4:first-child{margin-left:34.18803418803419%;*margin-left:34.081651209310785%}.row-fluid .offset3{margin-left:28.205128205128204%;*margin-left:28.0987452264048%}.row-fluid .offset3:first-child{margin-left:25.641025641025642%;*margin-left:25.53464266230224%}.row-fluid .offset2{margin-left:19.65811965811966%;*margin-left:19.551736679396257%}.row-fluid .offset2:first-child{margin-left:17.094017094017094%;*margin-left:16.98763411529369%}.row-fluid .offset1{margin-left:11.11111111111111%;*margin-left:11.004728132387708%}.row-fluid .offset1:first-child{margin-left:8.547008547008547%;*margin-left:8.440625568285142%}input,textarea,.uneditable-input{margin-left:0}.controls-row [class*="span"]+[class*="span"]{margin-left:30px}input.span12,textarea.span12,.uneditable-input.span12{width:1156px}input.span11,textarea.span11,.uneditable-input.span11{width:1056px}input.span10,textarea.span10,.uneditable-input.span10{width:956px}input.span9,textarea.span9,.uneditable-input.span9{width:856px}input.span8,textarea.span8,.uneditable-input.span8{width:756px}input.span7,textarea.span7,.uneditable-input.span7{width:656px}input.span6,textarea.span6,.uneditable-input.span6{width:556px}input.span5,textarea.span5,.uneditable-input.span5{width:456px}input.span4,textarea.span4,.uneditable-input.span4{width:356px}input.span3,textarea.span3,.uneditable-input.span3{width:256px}input.span2,textarea.span2,.uneditable-input.span2{width:156px}input.span1,textarea.span1,.uneditable-input.span1{width:56px}.thumbnails{margin-left:-30px}.thumbnails>li{margin-left:30px}.row-fluid .thumbnails{margin-left:0}}@media(min-width:768px) and (max-width:979px){.row{margin-left:-20px;*zoom:1}.row:before,.row:after{display:table;line-height:0;content:""}.row:after{clear:both}[class*="span"]{float:left;min-height:1px;margin-left:20px}.container,.navbar-static-top .container,.navbar-fixed-top .container,.navbar-fixed-bottom .container{width:724px}.span12{width:724px}.span11{width:662px}.span10{width:600px}.span9{width:538px}.span8{width:476px}.span7{width:414px}.span6{width:352px}.span5{width:290px}.span4{width:228px}.span3{width:166px}.span2{width:104px}.span1{width:42px}.offset12{margin-left:764px}.offset11{margin-left:702px}.offset10{margin-left:640px}.offset9{margin-left:578px}.offset8{margin-left:516px}.offset7{margin-left:454px}.offset6{margin-left:392px}.offset5{margin-left:330px}.offset4{margin-left:268px}.offset3{margin-left:206px}.offset2{margin-left:144px}.offset1{margin-left:82px}.row-fluid{width:100%;*zoom:1}.row-fluid:before,.row-fluid:after{display:table;line-height:0;content:""}.row-fluid:after{clear:both}.row-fluid [class*="span"]{display:block;float:left;width:100%;min-height:30px;margin-left:2.7624309392265194%;*margin-left:2.709239449864817%;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.row-fluid [class*="span"]:first-child{margin-left:0}.row-fluid .controls-row [class*="span"]+[class*="span"]{margin-left:2.7624309392265194%}.row-fluid .span12{width:100%;*width:99.94680851063829%}.row-fluid .span11{width:91.43646408839778%;*width:91.38327259903608%}.row-fluid .span10{width:82.87292817679558%;*width:82.81973668743387%}.row-fluid .span9{width:74.30939226519337%;*width:74.25620077583166%}.row-fluid .span8{width:65.74585635359117%;*width:65.69266486422946%}.row-fluid .span7{width:57.18232044198895%;*width:57.12912895262725%}.row-fluid .span6{width:48.61878453038674%;*width:48.56559304102504%}.row-fluid .span5{width:40.05524861878453%;*width:40.00205712942283%}.row-fluid .span4{width:31.491712707182323%;*width:31.43852121782062%}.row-fluid .span3{width:22.92817679558011%;*width:22.87498530621841%}.row-fluid .span2{width:14.3646408839779%;*width:14.311449394616199%}.row-fluid .span1{width:5.801104972375691%;*width:5.747913483013988%}.row-fluid .offset12{margin-left:105.52486187845304%;*margin-left:105.41847889972962%}.row-fluid .offset12:first-child{margin-left:102.76243093922652%;*margin-left:102.6560479605031%}.row-fluid .offset11{margin-left:96.96132596685082%;*margin-left:96.8549429881274%}.row-fluid .offset11:first-child{margin-left:94.1988950276243%;*margin-left:94.09251204890089%}.row-fluid .offset10{margin-left:88.39779005524862%;*margin-left:88.2914070765252%}.row-fluid .offset10:first-child{margin-left:85.6353591160221%;*margin-left:85.52897613729868%}.row-fluid .offset9{margin-left:79.8342541436464%;*margin-left:79.72787116492299%}.row-fluid .offset9:first-child{margin-left:77.07182320441989%;*margin-left:76.96544022569647%}.row-fluid .offset8{margin-left:71.2707182320442%;*margin-left:71.16433525332079%}.row-fluid .offset8:first-child{margin-left:68.50828729281768%;*margin-left:68.40190431409427%}.row-fluid .offset7{margin-left:62.70718232044199%;*margin-left:62.600799341718584%}.row-fluid .offset7:first-child{margin-left:59.94475138121547%;*margin-left:59.838368402492065%}.row-fluid .offset6{margin-left:54.14364640883978%;*margin-left:54.037263430116376%}.row-fluid .offset6:first-child{margin-left:51.38121546961326%;*margin-left:51.27483249088986%}.row-fluid .offset5{margin-left:45.58011049723757%;*margin-left:45.47372751851417%}.row-fluid .offset5:first-child{margin-left:42.81767955801105%;*margin-left:42.71129657928765%}.row-fluid .offset4{margin-left:37.01657458563536%;*margin-left:36.91019160691196%}.row-fluid .offset4:first-child{margin-left:34.25414364640884%;*margin-left:34.14776066768544%}.row-fluid .offset3{margin-left:28.45303867403315%;*margin-left:28.346655695309746%}.row-fluid .offset3:first-child{margin-left:25.69060773480663%;*margin-left:25.584224756083227%}.row-fluid .offset2{margin-left:19.88950276243094%;*margin-left:19.783119783707537%}.row-fluid .offset2:first-child{margin-left:17.12707182320442%;*margin-left:17.02068884448102%}.row-fluid .offset1{margin-left:11.32596685082873%;*margin-left:11.219583872105325%}.row-fluid .offset1:first-child{margin-left:8.56353591160221%;*margin-left:8.457152932878806%}input,textarea,.uneditable-input{margin-left:0}.controls-row [class*="span"]+[class*="span"]{margin-left:20px}input.span12,textarea.span12,.uneditable-input.span12{width:710px}input.span11,textarea.span11,.uneditable-input.span11{width:648px}input.span10,textarea.span10,.uneditable-input.span10{width:586px}input.span9,textarea.span9,.uneditable-input.span9{width:524px}input.span8,textarea.span8,.uneditable-input.span8{width:462px}input.span7,textarea.span7,.uneditable-input.span7{width:400px}input.span6,textarea.span6,.uneditable-input.span6{width:338px}input.span5,textarea.span5,.uneditable-input.span5{width:276px}input.span4,textarea.span4,.uneditable-input.span4{width:214px}input.span3,textarea.span3,.uneditable-input.span3{width:152px}input.span2,textarea.span2,.uneditable-input.span2{width:90px}input.span1,textarea.span1,.uneditable-input.span1{width:28px}}@media(max-width:767px){body{padding-right:20px;padding-left:20px}.navbar-fixed-top,.navbar-fixed-bottom,.navbar-static-top{margin-right:-20px;margin-left:-20px}.container-fluid{padding:0}.dl-horizontal dt{float:none;width:auto;clear:none;text-align:left}.dl-horizontal dd{margin-left:0}.container{width:auto}.row-fluid{width:100%}.row,.thumbnails{margin-left:0}.thumbnails>li{float:none;margin-left:0}[class*="span"],.uneditable-input[class*="span"],.row-fluid [class*="span"]{display:block;float:none;width:100%;margin-left:0;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.span12,.row-fluid .span12{width:100%;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.row-fluid [class*="offset"]:first-child{margin-left:0}.input-large,.input-xlarge,.input-xxlarge,input[class*="span"],select[class*="span"],textarea[class*="span"],.uneditable-input{display:block;width:100%;min-height:30px;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.input-prepend input,.input-append input,.input-prepend input[class*="span"],.input-append input[class*="span"]{display:inline-block;width:auto}.controls-row [class*="span"]+[class*="span"]{margin-left:0}.modal{position:fixed;top:20px;right:20px;left:20px;width:auto;margin:0}.modal.fade{top:-100px}.modal.fade.in{top:20px}}@media(max-width:480px){.nav-collapse{-webkit-transform:translate3d(0,0,0)}.page-header h1 small{display:block;line-height:20px}input[type="checkbox"],input[type="radio"]{border:1px solid #ccc}.form-horizontal .control-label{float:none;width:auto;padding-top:0;text-align:left}.form-horizontal .controls{margin-left:0}.form-horizontal .control-list{padding-top:0}.form-horizontal .form-actions{padding-right:10px;padding-left:10px}.media .pull-left,.media .pull-right{display:block;float:none;margin-bottom:10px}.media-object{margin-right:0;margin-left:0}.modal{top:10px;right:10px;left:10px}.modal-header .close{padding:10px;margin:-10px}.carousel-caption{position:static}}@media(max-width:979px){body{padding-top:0}.navbar-fixed-top,.navbar-fixed-bottom{position:static}.navbar-fixed-top{margin-bottom:20px}.navbar-fixed-bottom{margin-top:20px}.navbar-fixed-top .navbar-inner,.navbar-fixed-bottom .navbar-inner{padding:5px}.navbar .container{width:auto;padding:0}.navbar .brand{padding-right:10px;padding-left:10px;margin:0 0 0 -5px}.nav-collapse{clear:both}.nav-collapse .nav{float:none;margin:0 0 10px}.nav-collapse .nav>li{float:none}.nav-collapse .nav>li>a{margin-bottom:2px}.nav-collapse .nav>.divider-vertical{display:none}.nav-collapse .nav .nav-header{color:#777;text-shadow:none}.nav-collapse .nav>li>a,.nav-collapse .dropdown-menu a{padding:9px 15px;font-weight:bold;color:#777;-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px}.nav-collapse .btn{padding:4px 10px 4px;font-weight:normal;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}.nav-collapse .dropdown-menu li+li a{margin-bottom:2px}.nav-collapse .nav>li>a:hover,.nav-collapse .dropdown-menu a:hover{background-color:#f2f2f2}.navbar-inverse .nav-collapse .nav>li>a,.navbar-inverse .nav-collapse .dropdown-menu a{color:#999}.navbar-inverse .nav-collapse .nav>li>a:hover,.navbar-inverse .nav-collapse .dropdown-menu a:hover{background-color:#111}.nav-collapse.in .btn-group{padding:0;margin-top:5px}.nav-collapse .dropdown-menu{position:static;top:auto;left:auto;display:none;float:none;max-width:none;padding:0;margin:0 15px;background-color:transparent;border:0;-webkit-border-radius:0;-moz-border-radius:0;border-radius:0;-webkit-box-shadow:none;-moz-box-shadow:none;box-shadow:none}.nav-collapse .open>.dropdown-menu{display:block}.nav-collapse .dropdown-menu:before,.nav-collapse .dropdown-menu:after{display:none}.nav-collapse .dropdown-menu .divider{display:none}.nav-collapse .nav>li>.dropdown-menu:before,.nav-collapse .nav>li>.dropdown-menu:after{display:none}.nav-collapse .navbar-form,.nav-collapse .navbar-search{float:none;padding:10px 15px;margin:10px 0;border-top:1px solid #f2f2f2;border-bottom:1px solid #f2f2f2;-webkit-box-shadow:inset 0 1px 0 rgba(255,255,255,0.1),0 1px 0 rgba(255,255,255,0.1);-moz-box-shadow:inset 0 1px 0 rgba(255,255,255,0.1),0 1px 0 rgba(255,255,255,0.1);box-shadow:inset 0 1px 0 rgba(255,255,255,0.1),0 1px 0 rgba(255,255,255,0.1)}.navbar-inverse .nav-collapse .navbar-form,.navbar-inverse .nav-collapse .navbar-search{border-top-color:#111;border-bottom-color:#111}.navbar .nav-collapse .nav.pull-right{float:none;margin-left:0}.nav-collapse,.nav-collapse.collapse{height:0;overflow:hidden}.navbar .btn-navbar{display:block}.navbar-static .navbar-inner{padding-right:10px;padding-left:10px}}@media(min-width:980px){.nav-collapse.collapse{height:auto!important;overflow:visible!important}}
/*!
 * Bootstrap v2.2.0
 *
 * Copyright 2012 Twitter, Inc
 * Licensed under the Apache License v2.0
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Designed and built with all the love in the world @twitter by @mdo and @fat.
 */article,aside,details,figcaption,figure,footer,header,hgroup,nav,section{display:block}audio,canvas,video{display:inline-block;*display:inline;*zoom:1}audio:not([controls]){display:none}html{font-size:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}a:focus{outline:thin dotted #333;outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}a:hover,a:active{outline:0}sub,sup{position:relative;font-size:75%;line-height:0;vertical-align:baseline}sup{top:-0.5em}sub{bottom:-0.25em}img{width:auto\9;height:auto;max-width:100%;vertical-align:middle;border:0;-ms-interpolation-mode:bicubic}#map_canvas img,.google-maps img{max-width:none}button,input,select,textarea{margin:0;font-size:100%;vertical-align:middle}button,input{*overflow:visible;line-height:normal}button::-moz-focus-inner,input::-moz-focus-inner{padding:0;border:0}button,html input[type="button"],input[type="reset"],input[type="submit"]{cursor:pointer;-webkit-appearance:button}input[type="search"]{-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box;-webkit-appearance:textfield}input[type="search"]::-webkit-search-decoration,input[type="search"]::-webkit-search-cancel-button{-webkit-appearance:none}textarea{overflow:auto;vertical-align:top}.clearfix{*zoom:1}.clearfix:before,.clearfix:after{display:table;line-height:0;content:""}.clearfix:after{clear:both}.hide-text{font:0/0 a;color:transparent;text-shadow:none;background-color:transparent;border:0}.input-block-level{display:block;width:100%;min-height:30px;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}body{margin:0;font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:14px;line-height:20px;color:#333;background-color:#fff}a{color:#08c;text-decoration:none}a:hover{color:#005580;text-decoration:underline}.img-rounded{-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px}.img-polaroid{padding:4px;background-color:#fff;border:1px solid #ccc;border:1px solid rgba(0,0,0,0.2);-webkit-box-shadow:0 1px 3px rgba(0,0,0,0.1);-moz-box-shadow:0 1px 3px rgba(0,0,0,0.1);box-shadow:0 1px 3px rgba(0,0,0,0.1)}.img-circle{-webkit-border-radius:500px;-moz-border-radius:500px;border-radius:500px}.row{margin-left:-20px;*zoom:1}.row:before,.row:after{display:table;line-height:0;content:""}.row:after{clear:both}[class*="span"]{float:left;min-height:1px;margin-left:20px}.container,.navbar-static-top .container,.navbar-fixed-top .container,.navbar-fixed-bottom .container{width:940px}.span12{width:940px}.span11{width:860px}.span10{width:780px}.span9{width:700px}.span8{width:620px}.span7{width:540px}.span6{width:460px}.span5{width:380px}.span4{width:300px}.span3{width:220px}.span2{width:140px}.span1{width:60px}.offset12{margin-left:980px}.offset11{margin-left:900px}.offset10{margin-left:820px}.offset9{margin-left:740px}.offset8{margin-left:660px}.offset7{margin-left:580px}.offset6{margin-left:500px}.offset5{margin-left:420px}.offset4{margin-left:340px}.offset3{margin-left:260px}.offset2{margin-left:180px}.offset1{margin-left:100px}.row-fluid{width:100%;*zoom:1}.row-fluid:before,.row-fluid:after{display:table;line-height:0;content:""}.row-fluid:after{clear:both}.row-fluid [class*="span"]{display:block;float:left;width:100%;min-height:30px;margin-left:2.127659574468085%;*margin-left:2.074468085106383%;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.row-fluid [class*="span"]:first-child{margin-left:0}.row-fluid .controls-row [class*="span"]+[class*="span"]{margin-left:2.127659574468085%}.row-fluid .span12{width:100%;*width:99.94680851063829%}.row-fluid .span11{width:91.48936170212765%;*width:91.43617021276594%}.row-fluid .span10{width:82.97872340425532%;*width:82.92553191489361%}.row-fluid .span9{width:74.46808510638297%;*width:74.41489361702126%}.row-fluid .span8{width:65.95744680851064%;*width:65.90425531914893%}.row-fluid .span7{width:57.44680851063829%;*width:57.39361702127659%}.row-fluid .span6{width:48.93617021276595%;*width:48.88297872340425%}.row-fluid .span5{width:40.42553191489362%;*width:40.37234042553192%}.row-fluid .span4{width:31.914893617021278%;*width:31.861702127659576%}.row-fluid .span3{width:23.404255319148934%;*width:23.351063829787233%}.row-fluid .span2{width:14.893617021276595%;*width:14.840425531914894%}.row-fluid .span1{width:6.382978723404255%;*width:6.329787234042553%}.row-fluid .offset12{margin-left:104.25531914893617%;*margin-left:104.14893617021275%}.row-fluid .offset12:first-child{margin-left:102.12765957446808%;*margin-left:102.02127659574467%}.row-fluid .offset11{margin-left:95.74468085106382%;*margin-left:95.6382978723404%}.row-fluid .offset11:first-child{margin-left:93.61702127659574%;*margin-left:93.51063829787232%}.row-fluid .offset10{margin-left:87.23404255319149%;*margin-left:87.12765957446807%}.row-fluid .offset10:first-child{margin-left:85.1063829787234%;*margin-left:84.99999999999999%}.row-fluid .offset9{margin-left:78.72340425531914%;*margin-left:78.61702127659572%}.row-fluid .offset9:first-child{margin-left:76.59574468085106%;*margin-left:76.48936170212764%}.row-fluid .offset8{margin-left:70.2127659574468%;*margin-left:70.10638297872339%}.row-fluid .offset8:first-child{margin-left:68.08510638297872%;*margin-left:67.9787234042553%}.row-fluid .offset7{margin-left:61.70212765957446%;*margin-left:61.59574468085106%}.row-fluid .offset7:first-child{margin-left:59.574468085106375%;*margin-left:59.46808510638297%}.row-fluid .offset6{margin-left:53.191489361702125%;*margin-left:53.085106382978715%}.row-fluid .offset6:first-child{margin-left:51.063829787234035%;*margin-left:50.95744680851063%}.row-fluid .offset5{margin-left:44.68085106382979%;*margin-left:44.57446808510638%}.row-fluid .offset5:first-child{margin-left:42.5531914893617%;*margin-left:42.4468085106383%}.row-fluid .offset4{margin-left:36.170212765957444%;*margin-left:36.06382978723405%}.row-fluid .offset4:first-child{margin-left:34.04255319148936%;*margin-left:33.93617021276596%}.row-fluid .offset3{margin-left:27.659574468085104%;*margin-left:27.5531914893617%}.row-fluid .offset3:first-child{margin-left:25.53191489361702%;*margin-left:25.425531914893618%}.row-fluid .offset2{margin-left:19.148936170212764%;*margin-left:19.04255319148936%}.row-fluid .offset2:first-child{margin-left:17.02127659574468%;*margin-left:16.914893617021278%}.row-fluid .offset1{margin-left:10.638297872340425%;*margin-left:10.53191489361702%}.row-fluid .offset1:first-child{margin-left:8.51063829787234%;*margin-left:8.404255319148938%}[class*="span"].hide,.row-fluid [class*="span"].hide{display:none}[class*="span"].pull-right,.row-fluid [class*="span"].pull-right{float:right}.container{margin-right:auto;margin-left:auto;*zoom:1}.container:before,.container:after{display:table;line-height:0;content:""}.container:after{clear:both}.container-fluid{padding-right:20px;padding-left:20px;*zoom:1}.container-fluid:before,.container-fluid:after{display:table;line-height:0;content:""}.container-fluid:after{clear:both}p{margin:0 0 10px}.lead{margin-bottom:20px;font-size:21px;font-weight:200;line-height:30px}small{font-size:85%}strong{font-weight:bold}em{font-style:italic}cite{font-style:normal}.muted{color:#999}.text-warning{color:#c09853}a.text-warning:hover{color:#a47e3c}.text-error{color:#b94a48}a.text-error:hover{color:#953b39}.text-info{color:#3a87ad}a.text-info:hover{color:#2d6987}.text-success{color:#468847}a.text-success:hover{color:#356635}h1,h2,h3,h4,h5,h6{margin:10px 0;font-family:inherit;font-weight:bold;line-height:20px;color:inherit;text-rendering:optimizelegibility}h1 small,h2 small,h3 small,h4 small,h5 small,h6 small{font-weight:normal;line-height:1;color:#999}h1,h2,h3{line-height:40px}h1{font-size:38.5px}h2{font-size:31.5px}h3{font-size:24.5px}h4{font-size:17.5px}h5{font-size:14px}h6{font-size:11.9px}h1 small{font-size:24.5px}h2 small{font-size:17.5px}h3 small{font-size:14px}h4 small{font-size:14px}.page-header{padding-bottom:9px;margin:20px 0 30px;border-bottom:1px solid #eee}ul,ol{padding:0;margin:0 0 10px 25px}ul ul,ul ol,ol ol,ol ul{margin-bottom:0}li{line-height:20px}ul.unstyled,ol.unstyled{margin-left:0;list-style:none}dl{margin-bottom:20px}dt,dd{line-height:20px}dt{font-weight:bold}dd{margin-left:10px}.dl-horizontal{*zoom:1}.dl-horizontal:before,.dl-horizontal:after{display:table;line-height:0;content:""}.dl-horizontal:after{clear:both}.dl-horizontal dt{float:left;width:160px;overflow:hidden;clear:left;text-align:right;text-overflow:ellipsis;white-space:nowrap}.dl-horizontal dd{margin-left:180px}hr{margin:20px 0;border:0;border-top:1px solid #eee;border-bottom:1px solid #fff}abbr[title],abbr[data-original-title]{cursor:help;border-bottom:1px dotted #999}abbr.initialism{font-size:90%;text-transform:uppercase}blockquote{padding:0 0 0 15px;margin:0 0 20px;border-left:5px solid #eee}blockquote p{margin-bottom:0;font-size:16px;font-weight:300;line-height:25px}blockquote small{display:block;line-height:20px;color:#999}blockquote small:before{content:'\2014 \00A0'}blockquote.pull-right{float:right;padding-right:15px;padding-left:0;border-right:5px solid #eee;border-left:0}blockquote.pull-right p,blockquote.pull-right small{text-align:right}blockquote.pull-right small:before{content:''}blockquote.pull-right small:after{content:'\00A0 \2014'}q:before,q:after,blockquote:before,blockquote:after{content:""}address{display:block;margin-bottom:20px;font-style:normal;line-height:20px}code,pre{padding:0 3px 2px;font-family:Monaco,Menlo,Consolas,"Courier New",monospace;font-size:12px;color:#333;-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px}code{padding:2px 4px;color:#d14;background-color:#f7f7f9;border:1px solid #e1e1e8}pre{display:block;padding:9.5px;margin:0 0 10px;font-size:13px;line-height:20px;word-break:break-all;word-wrap:break-word;white-space:pre;white-space:pre-wrap;background-color:#f5f5f5;border:1px solid #ccc;border:1px solid rgba(0,0,0,0.15);-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}pre.prettyprint{margin-bottom:20px}pre code{padding:0;color:inherit;background-color:transparent;border:0}.pre-scrollable{max-height:340px;overflow-y:scroll}form{margin:0 0 20px}fieldset{padding:0;margin:0;border:0}legend{display:block;width:100%;padding:0;margin-bottom:20px;font-size:21px;line-height:40px;color:#333;border:0;border-bottom:1px solid #e5e5e5}legend small{font-size:15px;color:#999}label,input,button,select,textarea{font-size:14px;font-weight:normal;line-height:20px}input,button,select,textarea{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif}label{display:block;margin-bottom:5px}select,textarea,input[type="text"],input[type="password"],input[type="datetime"],input[type="datetime-local"],input[type="date"],input[type="month"],input[type="time"],input[type="week"],input[type="number"],input[type="email"],input[type="url"],input[type="search"],input[type="tel"],input[type="color"],.uneditable-input{display:inline-block;height:20px;padding:4px 6px;margin-bottom:10px;font-size:14px;line-height:20px;color:#555;vertical-align:middle;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}input,textarea,.uneditable-input{width:206px}textarea{height:auto}textarea,input[type="text"],input[type="password"],input[type="datetime"],input[type="datetime-local"],input[type="date"],input[type="month"],input[type="time"],input[type="week"],input[type="number"],input[type="email"],input[type="url"],input[type="search"],input[type="tel"],input[type="color"],.uneditable-input{background-color:#fff;border:1px solid #ccc;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);-webkit-transition:border linear .2s,box-shadow linear .2s;-moz-transition:border linear .2s,box-shadow linear .2s;-o-transition:border linear .2s,box-shadow linear .2s;transition:border linear .2s,box-shadow linear .2s}textarea:focus,input[type="text"]:focus,input[type="password"]:focus,input[type="datetime"]:focus,input[type="datetime-local"]:focus,input[type="date"]:focus,input[type="month"]:focus,input[type="time"]:focus,input[type="week"]:focus,input[type="number"]:focus,input[type="email"]:focus,input[type="url"]:focus,input[type="search"]:focus,input[type="tel"]:focus,input[type="color"]:focus,.uneditable-input:focus{border-color:rgba(82,168,236,0.8);outline:0;outline:thin dotted \9;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 8px rgba(82,168,236,0.6);-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 8px rgba(82,168,236,0.6);box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 8px rgba(82,168,236,0.6)}input[type="radio"],input[type="checkbox"]{margin:4px 0 0;margin-top:1px \9;*margin-top:0;line-height:normal;cursor:pointer}input[type="file"],input[type="image"],input[type="submit"],input[type="reset"],input[type="button"],input[type="radio"],input[type="checkbox"]{width:auto}select,input[type="file"]{height:30px;*margin-top:4px;line-height:30px}select{width:220px;background-color:#fff;border:1px solid #ccc}select[multiple],select[size]{height:auto}select:focus,input[type="file"]:focus,input[type="radio"]:focus,input[type="checkbox"]:focus{outline:thin dotted #333;outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}.uneditable-input,.uneditable-textarea{color:#999;cursor:not-allowed;background-color:#fcfcfc;border-color:#ccc;-webkit-box-shadow:inset 0 1px 2px rgba(0,0,0,0.025);-moz-box-shadow:inset 0 1px 2px rgba(0,0,0,0.025);box-shadow:inset 0 1px 2px rgba(0,0,0,0.025)}.uneditable-input{overflow:hidden;white-space:nowrap}.uneditable-textarea{width:auto;height:auto}input:-moz-placeholder,textarea:-moz-placeholder{color:#999}input:-ms-input-placeholder,textarea:-ms-input-placeholder{color:#999}input::-webkit-input-placeholder,textarea::-webkit-input-placeholder{color:#999}.radio,.checkbox{min-height:20px;padding-left:20px}.radio input[type="radio"],.checkbox input[type="checkbox"]{float:left;margin-left:-20px}.controls>.radio:first-child,.controls>.checkbox:first-child{padding-top:5px}.radio.inline,.checkbox.inline{display:inline-block;padding-top:5px;margin-bottom:0;vertical-align:middle}.radio.inline+.radio.inline,.checkbox.inline+.checkbox.inline{margin-left:10px}.input-mini{width:60px}.input-small{width:90px}.input-medium{width:150px}.input-large{width:210px}.input-xlarge{width:270px}.input-xxlarge{width:530px}input[class*="span"],select[class*="span"],textarea[class*="span"],.uneditable-input[class*="span"],.row-fluid input[class*="span"],.row-fluid select[class*="span"],.row-fluid textarea[class*="span"],.row-fluid .uneditable-input[class*="span"]{float:none;margin-left:0}.input-append input[class*="span"],.input-append .uneditable-input[class*="span"],.input-prepend input[class*="span"],.input-prepend .uneditable-input[class*="span"],.row-fluid input[class*="span"],.row-fluid select[class*="span"],.row-fluid textarea[class*="span"],.row-fluid .uneditable-input[class*="span"],.row-fluid .input-prepend [class*="span"],.row-fluid .input-append [class*="span"]{display:inline-block}input,textarea,.uneditable-input{margin-left:0}.controls-row [class*="span"]+[class*="span"]{margin-left:20px}input.span12,textarea.span12,.uneditable-input.span12{width:926px}input.span11,textarea.span11,.uneditable-input.span11{width:846px}input.span10,textarea.span10,.uneditable-input.span10{width:766px}input.span9,textarea.span9,.uneditable-input.span9{width:686px}input.span8,textarea.span8,.uneditable-input.span8{width:606px}input.span7,textarea.span7,.uneditable-input.span7{width:526px}input.span6,textarea.span6,.uneditable-input.span6{width:446px}input.span5,textarea.span5,.uneditable-input.span5{width:366px}input.span4,textarea.span4,.uneditable-input.span4{width:286px}input.span3,textarea.span3,.uneditable-input.span3{width:206px}input.span2,textarea.span2,.uneditable-input.span2{width:126px}input.span1,textarea.span1,.uneditable-input.span1{width:46px}.controls-row{*zoom:1}.controls-row:before,.controls-row:after{display:table;line-height:0;content:""}.controls-row:after{clear:both}.controls-row [class*="span"],.row-fluid .controls-row [class*="span"]{float:left}.controls-row .checkbox[class*="span"],.controls-row .radio[class*="span"]{padding-top:5px}input[disabled],select[disabled],textarea[disabled],input[readonly],select[readonly],textarea[readonly]{cursor:not-allowed;background-color:#eee}input[type="radio"][disabled],input[type="checkbox"][disabled],input[type="radio"][readonly],input[type="checkbox"][readonly]{background-color:transparent}.control-group.warning>label,.control-group.warning .help-block,.control-group.warning .help-inline{color:#c09853}.control-group.warning .checkbox,.control-group.warning .radio,.control-group.warning input,.control-group.warning select,.control-group.warning textarea{color:#c09853}.control-group.warning input,.control-group.warning select,.control-group.warning textarea{border-color:#c09853;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);box-shadow:inset 0 1px 1px rgba(0,0,0,0.075)}.control-group.warning input:focus,.control-group.warning select:focus,.control-group.warning textarea:focus{border-color:#a47e3c;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #dbc59e;-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #dbc59e;box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #dbc59e}.control-group.warning .input-prepend .add-on,.control-group.warning .input-append .add-on{color:#c09853;background-color:#fcf8e3;border-color:#c09853}.control-group.error>label,.control-group.error .help-block,.control-group.error .help-inline{color:#b94a48}.control-group.error .checkbox,.control-group.error .radio,.control-group.error input,.control-group.error select,.control-group.error textarea{color:#b94a48}.control-group.error input,.control-group.error select,.control-group.error textarea{border-color:#b94a48;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);box-shadow:inset 0 1px 1px rgba(0,0,0,0.075)}.control-group.error input:focus,.control-group.error select:focus,.control-group.error textarea:focus{border-color:#953b39;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #d59392;-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #d59392;box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #d59392}.control-group.error .input-prepend .add-on,.control-group.error .input-append .add-on{color:#b94a48;background-color:#f2dede;border-color:#b94a48}.control-group.success>label,.control-group.success .help-block,.control-group.success .help-inline{color:#468847}.control-group.success .checkbox,.control-group.success .radio,.control-group.success input,.control-group.success select,.control-group.success textarea{color:#468847}.control-group.success input,.control-group.success select,.control-group.success textarea{border-color:#468847;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);box-shadow:inset 0 1px 1px rgba(0,0,0,0.075)}.control-group.success input:focus,.control-group.success select:focus,.control-group.success textarea:focus{border-color:#356635;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #7aba7b;-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #7aba7b;box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #7aba7b}.control-group.success .input-prepend .add-on,.control-group.success .input-append .add-on{color:#468847;background-color:#dff0d8;border-color:#468847}.control-group.info>label,.control-group.info .help-block,.control-group.info .help-inline{color:#3a87ad}.control-group.info .checkbox,.control-group.info .radio,.control-group.info input,.control-group.info select,.control-group.info textarea{color:#3a87ad}.control-group.info input,.control-group.info select,.control-group.info textarea{border-color:#3a87ad;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075);box-shadow:inset 0 1px 1px rgba(0,0,0,0.075)}.control-group.info input:focus,.control-group.info select:focus,.control-group.info textarea:focus{border-color:#2d6987;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #7ab5d3;-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #7ab5d3;box-shadow:inset 0 1px 1px rgba(0,0,0,0.075),0 0 6px #7ab5d3}.control-group.info .input-prepend .add-on,.control-group.info .input-append .add-on{color:#3a87ad;background-color:#d9edf7;border-color:#3a87ad}input:focus:required:invalid,textarea:focus:required:invalid,select:focus:required:invalid{color:#b94a48;border-color:#ee5f5b}input:focus:required:invalid:focus,textarea:focus:required:invalid:focus,select:focus:required:invalid:focus{border-color:#e9322d;-webkit-box-shadow:0 0 6px #f8b9b7;-moz-box-shadow:0 0 6px #f8b9b7;box-shadow:0 0 6px #f8b9b7}.form-actions{padding:19px 20px 20px;margin-top:20px;margin-bottom:20px;background-color:#f5f5f5;border-top:1px solid #e5e5e5;*zoom:1}.form-actions:before,.form-actions:after{display:table;line-height:0;content:""}.form-actions:after{clear:both}.help-block,.help-inline{color:#595959}.help-block{display:block;margin-bottom:10px}.help-inline{display:inline-block;*display:inline;padding-left:5px;vertical-align:middle;*zoom:1}.input-append,.input-prepend{margin-bottom:5px;font-size:0;white-space:nowrap}.input-append input,.input-prepend input,.input-append select,.input-prepend select,.input-append .uneditable-input,.input-prepend .uneditable-input,.input-append .dropdown-menu,.input-prepend .dropdown-menu{font-size:14px}.input-append input,.input-prepend input,.input-append select,.input-prepend select,.input-append .uneditable-input,.input-prepend .uneditable-input{position:relative;margin-bottom:0;*margin-left:0;vertical-align:top;-webkit-border-radius:0 4px 4px 0;-moz-border-radius:0 4px 4px 0;border-radius:0 4px 4px 0}.input-append input:focus,.input-prepend input:focus,.input-append select:focus,.input-prepend select:focus,.input-append .uneditable-input:focus,.input-prepend .uneditable-input:focus{z-index:2}.input-append .add-on,.input-prepend .add-on{display:inline-block;width:auto;height:20px;min-width:16px;padding:4px 5px;font-size:14px;font-weight:normal;line-height:20px;text-align:center;text-shadow:0 1px 0 #fff;background-color:#eee;border:1px solid #ccc}.input-append .add-on,.input-prepend .add-on,.input-append .btn,.input-prepend .btn{vertical-align:top;-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.input-append .active,.input-prepend .active{background-color:#a9dba9;border-color:#46a546}.input-prepend .add-on,.input-prepend .btn{margin-right:-1px}.input-prepend .add-on:first-child,.input-prepend .btn:first-child{-webkit-border-radius:4px 0 0 4px;-moz-border-radius:4px 0 0 4px;border-radius:4px 0 0 4px}.input-append input,.input-append select,.input-append .uneditable-input{-webkit-border-radius:4px 0 0 4px;-moz-border-radius:4px 0 0 4px;border-radius:4px 0 0 4px}.input-append input+.btn-group .btn,.input-append select+.btn-group .btn,.input-append .uneditable-input+.btn-group .btn{-webkit-border-radius:0 4px 4px 0;-moz-border-radius:0 4px 4px 0;border-radius:0 4px 4px 0}.input-append .add-on,.input-append .btn,.input-append .btn-group{margin-left:-1px}.input-append .add-on:last-child,.input-append .btn:last-child{-webkit-border-radius:0 4px 4px 0;-moz-border-radius:0 4px 4px 0;border-radius:0 4px 4px 0}.input-prepend.input-append input,.input-prepend.input-append select,.input-prepend.input-append .uneditable-input{-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.input-prepend.input-append input+.btn-group .btn,.input-prepend.input-append select+.btn-group .btn,.input-prepend.input-append .uneditable-input+.btn-group .btn{-webkit-border-radius:0 4px 4px 0;-moz-border-radius:0 4px 4px 0;border-radius:0 4px 4px 0}.input-prepend.input-append .add-on:first-child,.input-prepend.input-append .btn:first-child{margin-right:-1px;-webkit-border-radius:4px 0 0 4px;-moz-border-radius:4px 0 0 4px;border-radius:4px 0 0 4px}.input-prepend.input-append .add-on:last-child,.input-prepend.input-append .btn:last-child{margin-left:-1px;-webkit-border-radius:0 4px 4px 0;-moz-border-radius:0 4px 4px 0;border-radius:0 4px 4px 0}.input-prepend.input-append .btn-group:first-child{margin-left:0}input.search-query{padding-right:14px;padding-right:4px \9;padding-left:14px;padding-left:4px \9;margin-bottom:0;-webkit-border-radius:15px;-moz-border-radius:15px;border-radius:15px}.form-search .input-append .search-query,.form-search .input-prepend .search-query{-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.form-search .input-append .search-query{-webkit-border-radius:14px 0 0 14px;-moz-border-radius:14px 0 0 14px;border-radius:14px 0 0 14px}.form-search .input-append .btn{-webkit-border-radius:0 14px 14px 0;-moz-border-radius:0 14px 14px 0;border-radius:0 14px 14px 0}.form-search .input-prepend .search-query{-webkit-border-radius:0 14px 14px 0;-moz-border-radius:0 14px 14px 0;border-radius:0 14px 14px 0}.form-search .input-prepend .btn{-webkit-border-radius:14px 0 0 14px;-moz-border-radius:14px 0 0 14px;border-radius:14px 0 0 14px}.form-search input,.form-inline input,.form-horizontal input,.form-search textarea,.form-inline textarea,.form-horizontal textarea,.form-search select,.form-inline select,.form-horizontal select,.form-search .help-inline,.form-inline .help-inline,.form-horizontal .help-inline,.form-search .uneditable-input,.form-inline .uneditable-input,.form-horizontal .uneditable-input,.form-search .input-prepend,.form-inline .input-prepend,.form-horizontal .input-prepend,.form-search .input-append,.form-inline .input-append,.form-horizontal .input-append{display:inline-block;*display:inline;margin-bottom:0;vertical-align:middle;*zoom:1}.form-search .hide,.form-inline .hide,.form-horizontal .hide{display:none}.form-search label,.form-inline label,.form-search .btn-group,.form-inline .btn-group{display:inline-block}.form-search .input-append,.form-inline .input-append,.form-search .input-prepend,.form-inline .input-prepend{margin-bottom:0}.form-search .radio,.form-search .checkbox,.form-inline .radio,.form-inline .checkbox{padding-left:0;margin-bottom:0;vertical-align:middle}.form-search .radio input[type="radio"],.form-search .checkbox input[type="checkbox"],.form-inline .radio input[type="radio"],.form-inline .checkbox input[type="checkbox"]{float:left;margin-right:3px;margin-left:0}.control-group{margin-bottom:10px}legend+.control-group{margin-top:20px;-webkit-margin-top-collapse:separate}.form-horizontal .control-group{margin-bottom:20px;*zoom:1}.form-horizontal .control-group:before,.form-horizontal .control-group:after{display:table;line-height:0;content:""}.form-horizontal .control-group:after{clear:both}.form-horizontal .control-label{float:left;width:160px;padding-top:5px;text-align:right}.form-horizontal .controls{*display:inline-block;*padding-left:20px;margin-left:180px;*margin-left:0}.form-horizontal .controls:first-child{*padding-left:180px}.form-horizontal .help-block{margin-bottom:0}.form-horizontal input+.help-block,.form-horizontal select+.help-block,.form-horizontal textarea+.help-block{margin-top:10px}.form-horizontal .form-actions{padding-left:180px}table{max-width:100%;background-color:transparent;border-collapse:collapse;border-spacing:0}.table{width:100%;margin-bottom:20px}.table th,.table td{padding:8px;line-height:20px;text-align:left;vertical-align:top;border-top:1px solid #ddd}.table th{font-weight:bold}.table thead th{vertical-align:bottom}.table caption+thead tr:first-child th,.table caption+thead tr:first-child td,.table colgroup+thead tr:first-child th,.table colgroup+thead tr:first-child td,.table thead:first-child tr:first-child th,.table thead:first-child tr:first-child td{border-top:0}.table tbody+tbody{border-top:2px solid #ddd}.table-condensed th,.table-condensed td{padding:4px 5px}.table-bordered{border:1px solid #ddd;border-collapse:separate;*border-collapse:collapse;border-left:0;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}.table-bordered th,.table-bordered td{border-left:1px solid #ddd}.table-bordered caption+thead tr:first-child th,.table-bordered caption+tbody tr:first-child th,.table-bordered caption+tbody tr:first-child td,.table-bordered colgroup+thead tr:first-child th,.table-bordered colgroup+tbody tr:first-child th,.table-bordered colgroup+tbody tr:first-child td,.table-bordered thead:first-child tr:first-child th,.table-bordered tbody:first-child tr:first-child th,.table-bordered tbody:first-child tr:first-child td{border-top:0}.table-bordered thead:first-child tr:first-child th:first-child,.table-bordered tbody:first-child tr:first-child td:first-child{-webkit-border-top-left-radius:4px;border-top-left-radius:4px;-moz-border-radius-topleft:4px}.table-bordered thead:first-child tr:first-child th:last-child,.table-bordered tbody:first-child tr:first-child td:last-child{-webkit-border-top-right-radius:4px;border-top-right-radius:4px;-moz-border-radius-topright:4px}.table-bordered thead:last-child tr:last-child th:first-child,.table-bordered tbody:last-child tr:last-child td:first-child,.table-bordered tfoot:last-child tr:last-child td:first-child{-webkit-border-radius:0 0 0 4px;-moz-border-radius:0 0 0 4px;border-radius:0 0 0 4px;-webkit-border-bottom-left-radius:4px;border-bottom-left-radius:4px;-moz-border-radius-bottomleft:4px}.table-bordered thead:last-child tr:last-child th:last-child,.table-bordered tbody:last-child tr:last-child td:last-child,.table-bordered tfoot:last-child tr:last-child td:last-child{-webkit-border-bottom-right-radius:4px;border-bottom-right-radius:4px;-moz-border-radius-bottomright:4px}.table-bordered caption+thead tr:first-child th:first-child,.table-bordered caption+tbody tr:first-child td:first-child,.table-bordered colgroup+thead tr:first-child th:first-child,.table-bordered colgroup+tbody tr:first-child td:first-child{-webkit-border-top-left-radius:4px;border-top-left-radius:4px;-moz-border-radius-topleft:4px}.table-bordered caption+thead tr:first-child th:last-child,.table-bordered caption+tbody tr:first-child td:last-child,.table-bordered colgroup+thead tr:first-child th:last-child,.table-bordered colgroup+tbody tr:first-child td:last-child{-webkit-border-top-right-radius:4px;border-top-right-radius:4px;-moz-border-radius-topright:4px}.table-striped tbody tr:nth-child(odd) td,.table-striped tbody tr:nth-child(odd) th{background-color:#f9f9f9}.table-hover tbody tr:hover td,.table-hover tbody tr:hover th{background-color:#f5f5f5}table td[class*="span"],table th[class*="span"],.row-fluid table td[class*="span"],.row-fluid table th[class*="span"]{display:table-cell;float:none;margin-left:0}.table td.span1,.table th.span1{float:none;width:44px;margin-left:0}.table td.span2,.table th.span2{float:none;width:124px;margin-left:0}.table td.span3,.table th.span3{float:none;width:204px;margin-left:0}.table td.span4,.table th.span4{float:none;width:284px;margin-left:0}.table td.span5,.table th.span5{float:none;width:364px;margin-left:0}.table td.span6,.table th.span6{float:none;width:444px;margin-left:0}.table td.span7,.table th.span7{float:none;width:524px;margin-left:0}.table td.span8,.table th.span8{float:none;width:604px;margin-left:0}.table td.span9,.table th.span9{float:none;width:684px;margin-left:0}.table td.span10,.table th.span10{float:none;width:764px;margin-left:0}.table td.span11,.table th.span11{float:none;width:844px;margin-left:0}.table td.span12,.table th.span12{float:none;width:924px;margin-left:0}.table tbody tr.success td{background-color:#dff0d8}.table tbody tr.error td{background-color:#f2dede}.table tbody tr.warning td{background-color:#fcf8e3}.table tbody tr.info td{background-color:#d9edf7}.table-hover tbody tr.success:hover td{background-color:#d0e9c6}.table-hover tbody tr.error:hover td{background-color:#ebcccc}.table-hover tbody tr.warning:hover td{background-color:#faf2cc}.table-hover tbody tr.info:hover td{background-color:#c4e3f3}[class^="icon-"],[class*=" icon-"]{display:inline-block;width:14px;height:14px;margin-top:1px;*margin-right:.3em;line-height:14px;vertical-align:text-top;background-image:url("../img/glyphicons-halflings.png");background-position:14px 14px;background-repeat:no-repeat}.icon-white,.nav-pills>.active>a>[class^="icon-"],.nav-pills>.active>a>[class*=" icon-"],.nav-list>.active>a>[class^="icon-"],.nav-list>.active>a>[class*=" icon-"],.navbar-inverse .nav>.active>a>[class^="icon-"],.navbar-inverse .nav>.active>a>[class*=" icon-"],.dropdown-menu>li>a:hover>[class^="icon-"],.dropdown-menu>li>a:hover>[class*=" icon-"],.dropdown-menu>.active>a>[class^="icon-"],.dropdown-menu>.active>a>[class*=" icon-"],.dropdown-submenu:hover>a>[class^="icon-"],.dropdown-submenu:hover>a>[class*=" icon-"]{background-image:url("../img/glyphicons-halflings-white.png")}.icon-glass{background-position:0 0}.icon-music{background-position:-24px 0}.icon-search{background-position:-48px 0}.icon-envelope{background-position:-72px 0}.icon-heart{background-position:-96px 0}.icon-star{background-position:-120px 0}.icon-star-empty{background-position:-144px 0}.icon-user{background-position:-168px 0}.icon-film{background-position:-192px 0}.icon-th-large{background-position:-216px 0}.icon-th{background-position:-240px 0}.icon-th-list{background-position:-264px 0}.icon-ok{background-position:-288px 0}.icon-remove{background-position:-312px 0}.icon-zoom-in{background-position:-336px 0}.icon-zoom-out{background-position:-360px 0}.icon-off{background-position:-384px 0}.icon-signal{background-position:-408px 0}.icon-cog{background-position:-432px 0}.icon-trash{background-position:-456px 0}.icon-home{background-position:0 -24px}.icon-file{background-position:-24px -24px}.icon-time{background-position:-48px -24px}.icon-road{background-position:-72px -24px}.icon-download-alt{background-position:-96px -24px}.icon-download{background-position:-120px -24px}.icon-upload{background-position:-144px -24px}.icon-inbox{background-position:-168px -24px}.icon-play-circle{background-position:-192px -24px}.icon-repeat{background-position:-216px -24px}.icon-refresh{background-position:-240px -24px}.icon-list-alt{background-position:-264px -24px}.icon-lock{background-position:-287px -24px}.icon-flag{background-position:-312px -24px}.icon-headphones{background-position:-336px -24px}.icon-volume-off{background-position:-360px -24px}.icon-volume-down{background-position:-384px -24px}.icon-volume-up{background-position:-408px -24px}.icon-qrcode{background-position:-432px -24px}.icon-barcode{background-position:-456px -24px}.icon-tag{background-position:0 -48px}.icon-tags{background-position:-25px -48px}.icon-book{background-position:-48px -48px}.icon-bookmark{background-position:-72px -48px}.icon-print{background-position:-96px -48px}.icon-camera{background-position:-120px -48px}.icon-font{background-position:-144px -48px}.icon-bold{background-position:-167px -48px}.icon-italic{background-position:-192px -48px}.icon-text-height{background-position:-216px -48px}.icon-text-width{background-position:-240px -48px}.icon-align-left{background-position:-264px -48px}.icon-align-center{background-position:-288px -48px}.icon-align-right{background-position:-312px -48px}.icon-align-justify{background-position:-336px -48px}.icon-list{background-position:-360px -48px}.icon-indent-left{background-position:-384px -48px}.icon-indent-right{background-position:-408px -48px}.icon-facetime-video{background-position:-432px -48px}.icon-picture{background-position:-456px -48px}.icon-pencil{background-position:0 -72px}.icon-map-marker{background-position:-24px -72px}.icon-adjust{background-position:-48px -72px}.icon-tint{background-position:-72px -72px}.icon-edit{background-position:-96px -72px}.icon-share{background-position:-120px -72px}.icon-check{background-position:-144px -72px}.icon-move{background-position:-168px -72px}.icon-step-backward{background-position:-192px -72px}.icon-fast-backward{background-position:-216px -72px}.icon-backward{background-position:-240px -72px}.icon-play{background-position:-264px -72px}.icon-pause{background-position:-288px -72px}.icon-stop{background-position:-312px -72px}.icon-forward{background-position:-336px -72px}.icon-fast-forward{background-position:-360px -72px}.icon-step-forward{background-position:-384px -72px}.icon-eject{background-position:-408px -72px}.icon-chevron-left{background-position:-432px -72px}.icon-chevron-right{background-position:-456px -72px}.icon-plus-sign{background-position:0 -96px}.icon-minus-sign{background-position:-24px -96px}.icon-remove-sign{background-position:-48px -96px}.icon-ok-sign{background-position:-72px -96px}.icon-question-sign{background-position:-96px -96px}.icon-info-sign{background-position:-120px -96px}.icon-screenshot{background-position:-144px -96px}.icon-remove-circle{background-position:-168px -96px}.icon-ok-circle{background-position:-192px -96px}.icon-ban-circle{background-position:-216px -96px}.icon-arrow-left{background-position:-240px -96px}.icon-arrow-right{background-position:-264px -96px}.icon-arrow-up{background-position:-289px -96px}.icon-arrow-down{background-position:-312px -96px}.icon-share-alt{background-position:-336px -96px}.icon-resize-full{background-position:-360px -96px}.icon-resize-small{background-position:-384px -96px}.icon-plus{background-position:-408px -96px}.icon-minus{background-position:-433px -96px}.icon-asterisk{background-position:-456px -96px}.icon-exclamation-sign{background-position:0 -120px}.icon-gift{background-position:-24px -120px}.icon-leaf{background-position:-48px -120px}.icon-fire{background-position:-72px -120px}.icon-eye-open{background-position:-96px -120px}.icon-eye-close{background-position:-120px -120px}.icon-warning-sign{background-position:-144px -120px}.icon-plane{background-position:-168px -120px}.icon-calendar{background-position:-192px -120px}.icon-random{width:16px;background-position:-216px -120px}.icon-comment{background-position:-240px -120px}.icon-magnet{background-position:-264px -120px}.icon-chevron-up{background-position:-288px -120px}.icon-chevron-down{background-position:-313px -119px}.icon-retweet{background-position:-336px -120px}.icon-shopping-cart{background-position:-360px -120px}.icon-folder-close{background-position:-384px -120px}.icon-folder-open{width:16px;background-position:-408px -120px}.icon-resize-vertical{background-position:-432px -119px}.icon-resize-horizontal{background-position:-456px -118px}.icon-hdd{background-position:0 -144px}.icon-bullhorn{background-position:-24px -144px}.icon-bell{background-position:-48px -144px}.icon-certificate{background-position:-72px -144px}.icon-thumbs-up{background-position:-96px -144px}.icon-thumbs-down{background-position:-120px -144px}.icon-hand-right{background-position:-144px -144px}.icon-hand-left{background-position:-168px -144px}.icon-hand-up{background-position:-192px -144px}.icon-hand-down{background-position:-216px -144px}.icon-circle-arrow-right{background-position:-240px -144px}.icon-circle-arrow-left{background-position:-264px -144px}.icon-circle-arrow-up{background-position:-288px -144px}.icon-circle-arrow-down{background-position:-312px -144px}.icon-globe{background-position:-336px -144px}.icon-wrench{background-position:-360px -144px}.icon-tasks{background-position:-384px -144px}.icon-filter{background-position:-408px -144px}.icon-briefcase{background-position:-432px -144px}.icon-fullscreen{background-position:-456px -144px}.dropup,.dropdown{position:relative}.dropdown-toggle{*margin-bottom:-3px}.dropdown-toggle:active,.open .dropdown-toggle{outline:0}.caret{display:inline-block;width:0;height:0;vertical-align:top;border-top:4px solid #000;border-right:4px solid transparent;border-left:4px solid transparent;content:""}.dropdown .caret{margin-top:8px;margin-left:2px}.dropdown-menu{position:absolute;top:100%;left:0;z-index:1000;display:none;float:left;min-width:160px;padding:5px 0;margin:2px 0 0;list-style:none;background-color:#fff;border:1px solid #ccc;border:1px solid rgba(0,0,0,0.2);*border-right-width:2px;*border-bottom-width:2px;-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px;-webkit-box-shadow:0 5px 10px rgba(0,0,0,0.2);-moz-box-shadow:0 5px 10px rgba(0,0,0,0.2);box-shadow:0 5px 10px rgba(0,0,0,0.2);-webkit-background-clip:padding-box;-moz-background-clip:padding;background-clip:padding-box}.dropdown-menu.pull-right{right:0;left:auto}.dropdown-menu .divider{*width:100%;height:1px;margin:9px 1px;*margin:-5px 0 5px;overflow:hidden;background-color:#e5e5e5;border-bottom:1px solid #fff}.dropdown-menu li>a{display:block;padding:3px 20px;clear:both;font-weight:normal;line-height:20px;color:#333;white-space:nowrap}.dropdown-menu li>a:hover,.dropdown-menu li>a:focus,.dropdown-submenu:hover>a{color:#fff;text-decoration:none;background-color:#0081c2;background-image:-moz-linear-gradient(top,#08c,#0077b3);background-image:-webkit-gradient(linear,0 0,0 100%,from(#08c),to(#0077b3));background-image:-webkit-linear-gradient(top,#08c,#0077b3);background-image:-o-linear-gradient(top,#08c,#0077b3);background-image:linear-gradient(to bottom,#08c,#0077b3);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff0088cc',endColorstr='#ff0077b3',GradientType=0)}.dropdown-menu .active>a,.dropdown-menu .active>a:hover{color:#333;text-decoration:none;background-color:#0081c2;background-image:-moz-linear-gradient(top,#08c,#0077b3);background-image:-webkit-gradient(linear,0 0,0 100%,from(#08c),to(#0077b3));background-image:-webkit-linear-gradient(top,#08c,#0077b3);background-image:-o-linear-gradient(top,#08c,#0077b3);background-image:linear-gradient(to bottom,#08c,#0077b3);background-repeat:repeat-x;outline:0;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff0088cc',endColorstr='#ff0077b3',GradientType=0)}.dropdown-menu .disabled>a,.dropdown-menu .disabled>a:hover{color:#999}.dropdown-menu .disabled>a:hover{text-decoration:none;cursor:default;background-color:transparent;background-image:none}.open{*z-index:1000}.open>.dropdown-menu{display:block}.pull-right>.dropdown-menu{right:0;left:auto}.dropup .caret,.navbar-fixed-bottom .dropdown .caret{border-top:0;border-bottom:4px solid #000;content:""}.dropup .dropdown-menu,.navbar-fixed-bottom .dropdown .dropdown-menu{top:auto;bottom:100%;margin-bottom:1px}.dropdown-submenu{position:relative}.dropdown-submenu>.dropdown-menu{top:0;left:100%;margin-top:-6px;margin-left:-1px;-webkit-border-radius:0 6px 6px 6px;-moz-border-radius:0 6px 6px 6px;border-radius:0 6px 6px 6px}.dropdown-submenu:hover>.dropdown-menu{display:block}.dropup .dropdown-submenu>.dropdown-menu{top:auto;bottom:0;margin-top:0;margin-bottom:-2px;-webkit-border-radius:5px 5px 5px 0;-moz-border-radius:5px 5px 5px 0;border-radius:5px 5px 5px 0}.dropdown-submenu>a:after{display:block;float:right;width:0;height:0;margin-top:5px;margin-right:-10px;border-color:transparent;border-left-color:#ccc;border-style:solid;border-width:5px 0 5px 5px;content:" "}.dropdown-submenu:hover>a:after{border-left-color:#fff}.dropdown-submenu.pull-left{float:none}.dropdown-submenu.pull-left>.dropdown-menu{left:-100%;margin-left:10px;-webkit-border-radius:6px 0 6px 6px;-moz-border-radius:6px 0 6px 6px;border-radius:6px 0 6px 6px}.dropdown .dropdown-menu .nav-header{padding-right:20px;padding-left:20px}.typeahead{margin-top:2px;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}.well{min-height:20px;padding:19px;margin-bottom:20px;background-color:#f5f5f5;border:1px solid #e3e3e3;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,0.05);-moz-box-shadow:inset 0 1px 1px rgba(0,0,0,0.05);box-shadow:inset 0 1px 1px rgba(0,0,0,0.05)}.well blockquote{border-color:#ddd;border-color:rgba(0,0,0,0.15)}.well-large{padding:24px;-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px}.well-small{padding:9px;-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px}.fade{opacity:0;-webkit-transition:opacity .15s linear;-moz-transition:opacity .15s linear;-o-transition:opacity .15s linear;transition:opacity .15s linear}.fade.in{opacity:1}.collapse{position:relative;height:0;overflow:hidden;-webkit-transition:height .35s ease;-moz-transition:height .35s ease;-o-transition:height .35s ease;transition:height .35s ease}.collapse.in{height:auto}.close{float:right;font-size:20px;font-weight:bold;line-height:20px;color:#000;text-shadow:0 1px 0 #fff;opacity:.2;filter:alpha(opacity=20)}.close:hover{color:#000;text-decoration:none;cursor:pointer;opacity:.4;filter:alpha(opacity=40)}button.close{padding:0;cursor:pointer;background:transparent;border:0;-webkit-appearance:none}.btn{display:inline-block;*display:inline;padding:4px 12px;margin-bottom:0;*margin-left:.3em;font-size:14px;line-height:20px;*line-height:20px;color:#333;text-align:center;text-shadow:0 1px 1px rgba(255,255,255,0.75);vertical-align:middle;cursor:pointer;background-color:#f5f5f5;*background-color:#e6e6e6;background-image:-moz-linear-gradient(top,#fff,#e6e6e6);background-image:-webkit-gradient(linear,0 0,0 100%,from(#fff),to(#e6e6e6));background-image:-webkit-linear-gradient(top,#fff,#e6e6e6);background-image:-o-linear-gradient(top,#fff,#e6e6e6);background-image:linear-gradient(to bottom,#fff,#e6e6e6);background-repeat:repeat-x;border:1px solid #bbb;*border:0;border-color:#e6e6e6 #e6e6e6 #bfbfbf;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);border-bottom-color:#a2a2a2;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffffffff',endColorstr='#ffe6e6e6',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false);*zoom:1;-webkit-box-shadow:inset 0 1px 0 rgba(255,255,255,0.2),0 1px 2px rgba(0,0,0,0.05);-moz-box-shadow:inset 0 1px 0 rgba(255,255,255,0.2),0 1px 2px rgba(0,0,0,0.05);box-shadow:inset 0 1px 0 rgba(255,255,255,0.2),0 1px 2px rgba(0,0,0,0.05)}.btn:hover,.btn:active,.btn.active,.btn.disabled,.btn[disabled]{color:#333;background-color:#e6e6e6;*background-color:#d9d9d9}.btn:active,.btn.active{background-color:#ccc \9}.btn:first-child{*margin-left:0}.btn:hover{color:#333;text-decoration:none;background-color:#e6e6e6;*background-color:#d9d9d9;background-position:0 -15px;-webkit-transition:background-position .1s linear;-moz-transition:background-position .1s linear;-o-transition:background-position .1s linear;transition:background-position .1s linear}.btn:focus{outline:thin dotted #333;outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}.btn.active,.btn:active{background-color:#e6e6e6;background-color:#d9d9d9 \9;background-image:none;outline:0;-webkit-box-shadow:inset 0 2px 4px rgba(0,0,0,0.15),0 1px 2px rgba(0,0,0,0.05);-moz-box-shadow:inset 0 2px 4px rgba(0,0,0,0.15),0 1px 2px rgba(0,0,0,0.05);box-shadow:inset 0 2px 4px rgba(0,0,0,0.15),0 1px 2px rgba(0,0,0,0.05)}.btn.disabled,.btn[disabled]{cursor:default;background-color:#e6e6e6;background-image:none;opacity:.65;filter:alpha(opacity=65);-webkit-box-shadow:none;-moz-box-shadow:none;box-shadow:none}.btn-large{padding:11px 19px;font-size:17.5px;-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px}.btn-large [class^="icon-"],.btn-large [class*=" icon-"]{margin-top:2px}.btn-small{padding:2px 10px;font-size:11.9px;-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px}.btn-small [class^="icon-"],.btn-small [class*=" icon-"]{margin-top:0}.btn-mini{padding:1px 6px;font-size:10.5px;-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px}.btn-block{display:block;width:100%;padding-right:0;padding-left:0;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}.btn-block+.btn-block{margin-top:5px}input[type="submit"].btn-block,input[type="reset"].btn-block,input[type="button"].btn-block{width:100%}.btn-primary.active,.btn-warning.active,.btn-danger.active,.btn-success.active,.btn-info.active,.btn-inverse.active{color:rgba(255,255,255,0.75)}.btn{border-color:#c5c5c5;border-color:rgba(0,0,0,0.15) rgba(0,0,0,0.15) rgba(0,0,0,0.25)}.btn-primary{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#006dcc;*background-color:#04c;background-image:-moz-linear-gradient(top,#08c,#04c);background-image:-webkit-gradient(linear,0 0,0 100%,from(#08c),to(#04c));background-image:-webkit-linear-gradient(top,#08c,#04c);background-image:-o-linear-gradient(top,#08c,#04c);background-image:linear-gradient(to bottom,#08c,#04c);background-repeat:repeat-x;border-color:#04c #04c #002a80;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff0088cc',endColorstr='#ff0044cc',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false)}.btn-primary:hover,.btn-primary:active,.btn-primary.active,.btn-primary.disabled,.btn-primary[disabled]{color:#fff;background-color:#04c;*background-color:#003bb3}.btn-primary:active,.btn-primary.active{background-color:#039 \9}.btn-warning{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#faa732;*background-color:#f89406;background-image:-moz-linear-gradient(top,#fbb450,#f89406);background-image:-webkit-gradient(linear,0 0,0 100%,from(#fbb450),to(#f89406));background-image:-webkit-linear-gradient(top,#fbb450,#f89406);background-image:-o-linear-gradient(top,#fbb450,#f89406);background-image:linear-gradient(to bottom,#fbb450,#f89406);background-repeat:repeat-x;border-color:#f89406 #f89406 #ad6704;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#fffbb450',endColorstr='#fff89406',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false)}.btn-warning:hover,.btn-warning:active,.btn-warning.active,.btn-warning.disabled,.btn-warning[disabled]{color:#fff;background-color:#f89406;*background-color:#df8505}.btn-warning:active,.btn-warning.active{background-color:#c67605 \9}.btn-danger{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#da4f49;*background-color:#bd362f;background-image:-moz-linear-gradient(top,#ee5f5b,#bd362f);background-image:-webkit-gradient(linear,0 0,0 100%,from(#ee5f5b),to(#bd362f));background-image:-webkit-linear-gradient(top,#ee5f5b,#bd362f);background-image:-o-linear-gradient(top,#ee5f5b,#bd362f);background-image:linear-gradient(to bottom,#ee5f5b,#bd362f);background-repeat:repeat-x;border-color:#bd362f #bd362f #802420;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffee5f5b',endColorstr='#ffbd362f',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false)}.btn-danger:hover,.btn-danger:active,.btn-danger.active,.btn-danger.disabled,.btn-danger[disabled]{color:#fff;background-color:#bd362f;*background-color:#a9302a}.btn-danger:active,.btn-danger.active{background-color:#942a25 \9}.btn-success{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#5bb75b;*background-color:#51a351;background-image:-moz-linear-gradient(top,#62c462,#51a351);background-image:-webkit-gradient(linear,0 0,0 100%,from(#62c462),to(#51a351));background-image:-webkit-linear-gradient(top,#62c462,#51a351);background-image:-o-linear-gradient(top,#62c462,#51a351);background-image:linear-gradient(to bottom,#62c462,#51a351);background-repeat:repeat-x;border-color:#51a351 #51a351 #387038;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff62c462',endColorstr='#ff51a351',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false)}.btn-success:hover,.btn-success:active,.btn-success.active,.btn-success.disabled,.btn-success[disabled]{color:#fff;background-color:#51a351;*background-color:#499249}.btn-success:active,.btn-success.active{background-color:#408140 \9}.btn-info{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#49afcd;*background-color:#2f96b4;background-image:-moz-linear-gradient(top,#5bc0de,#2f96b4);background-image:-webkit-gradient(linear,0 0,0 100%,from(#5bc0de),to(#2f96b4));background-image:-webkit-linear-gradient(top,#5bc0de,#2f96b4);background-image:-o-linear-gradient(top,#5bc0de,#2f96b4);background-image:linear-gradient(to bottom,#5bc0de,#2f96b4);background-repeat:repeat-x;border-color:#2f96b4 #2f96b4 #1f6377;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff5bc0de',endColorstr='#ff2f96b4',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false)}.btn-info:hover,.btn-info:active,.btn-info.active,.btn-info.disabled,.btn-info[disabled]{color:#fff;background-color:#2f96b4;*background-color:#2a85a0}.btn-info:active,.btn-info.active{background-color:#24748c \9}.btn-inverse{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#363636;*background-color:#222;background-image:-moz-linear-gradient(top,#444,#222);background-image:-webkit-gradient(linear,0 0,0 100%,from(#444),to(#222));background-image:-webkit-linear-gradient(top,#444,#222);background-image:-o-linear-gradient(top,#444,#222);background-image:linear-gradient(to bottom,#444,#222);background-repeat:repeat-x;border-color:#222 #222 #000;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff444444',endColorstr='#ff222222',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false)}.btn-inverse:hover,.btn-inverse:active,.btn-inverse.active,.btn-inverse.disabled,.btn-inverse[disabled]{color:#fff;background-color:#222;*background-color:#151515}.btn-inverse:active,.btn-inverse.active{background-color:#080808 \9}button.btn,input[type="submit"].btn{*padding-top:3px;*padding-bottom:3px}button.btn::-moz-focus-inner,input[type="submit"].btn::-moz-focus-inner{padding:0;border:0}button.btn.btn-large,input[type="submit"].btn.btn-large{*padding-top:7px;*padding-bottom:7px}button.btn.btn-small,input[type="submit"].btn.btn-small{*padding-top:3px;*padding-bottom:3px}button.btn.btn-mini,input[type="submit"].btn.btn-mini{*padding-top:1px;*padding-bottom:1px}.btn-link,.btn-link:active,.btn-link[disabled]{background-color:transparent;background-image:none;-webkit-box-shadow:none;-moz-box-shadow:none;box-shadow:none}.btn-link{color:#08c;cursor:pointer;border-color:transparent;-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.btn-link:hover{color:#005580;text-decoration:underline;background-color:transparent}.btn-link[disabled]:hover{color:#333;text-decoration:none}.btn-group{position:relative;display:inline-block;*display:inline;*margin-left:.3em;font-size:0;white-space:nowrap;vertical-align:middle;*zoom:1}.btn-group:first-child{*margin-left:0}.btn-group+.btn-group{margin-left:5px}.btn-toolbar{margin-top:10px;margin-bottom:10px;font-size:0}.btn-toolbar .btn+.btn,.btn-toolbar .btn-group+.btn,.btn-toolbar .btn+.btn-group{margin-left:5px}.btn-group>.btn{position:relative;-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.btn-group>.btn+.btn{margin-left:-1px}.btn-group>.btn,.btn-group>.dropdown-menu{font-size:14px}.btn-group>.btn-mini{font-size:11px}.btn-group>.btn-small{font-size:12px}.btn-group>.btn-large{font-size:16px}.btn-group>.btn:first-child{margin-left:0;-webkit-border-bottom-left-radius:4px;border-bottom-left-radius:4px;-webkit-border-top-left-radius:4px;border-top-left-radius:4px;-moz-border-radius-bottomleft:4px;-moz-border-radius-topleft:4px}.btn-group>.btn:last-child,.btn-group>.dropdown-toggle{-webkit-border-top-right-radius:4px;border-top-right-radius:4px;-webkit-border-bottom-right-radius:4px;border-bottom-right-radius:4px;-moz-border-radius-topright:4px;-moz-border-radius-bottomright:4px}.btn-group>.btn.large:first-child{margin-left:0;-webkit-border-bottom-left-radius:6px;border-bottom-left-radius:6px;-webkit-border-top-left-radius:6px;border-top-left-radius:6px;-moz-border-radius-bottomleft:6px;-moz-border-radius-topleft:6px}.btn-group>.btn.large:last-child,.btn-group>.large.dropdown-toggle{-webkit-border-top-right-radius:6px;border-top-right-radius:6px;-webkit-border-bottom-right-radius:6px;border-bottom-right-radius:6px;-moz-border-radius-topright:6px;-moz-border-radius-bottomright:6px}.btn-group>.btn:hover,.btn-group>.btn:focus,.btn-group>.btn:active,.btn-group>.btn.active{z-index:2}.btn-group .dropdown-toggle:active,.btn-group.open .dropdown-toggle{outline:0}.btn-group>.btn+.dropdown-toggle{*padding-top:5px;padding-right:8px;*padding-bottom:5px;padding-left:8px;-webkit-box-shadow:inset 1px 0 0 rgba(255,255,255,0.125),inset 0 1px 0 rgba(255,255,255,0.2),0 1px 2px rgba(0,0,0,0.05);-moz-box-shadow:inset 1px 0 0 rgba(255,255,255,0.125),inset 0 1px 0 rgba(255,255,255,0.2),0 1px 2px rgba(0,0,0,0.05);box-shadow:inset 1px 0 0 rgba(255,255,255,0.125),inset 0 1px 0 rgba(255,255,255,0.2),0 1px 2px rgba(0,0,0,0.05)}.btn-group>.btn-mini+.dropdown-toggle{*padding-top:2px;padding-right:5px;*padding-bottom:2px;padding-left:5px}.btn-group>.btn-small+.dropdown-toggle{*padding-top:5px;*padding-bottom:4px}.btn-group>.btn-large+.dropdown-toggle{*padding-top:7px;padding-right:12px;*padding-bottom:7px;padding-left:12px}.btn-group.open .dropdown-toggle{background-image:none;-webkit-box-shadow:inset 0 2px 4px rgba(0,0,0,0.15),0 1px 2px rgba(0,0,0,0.05);-moz-box-shadow:inset 0 2px 4px rgba(0,0,0,0.15),0 1px 2px rgba(0,0,0,0.05);box-shadow:inset 0 2px 4px rgba(0,0,0,0.15),0 1px 2px rgba(0,0,0,0.05)}.btn-group.open .btn.dropdown-toggle{background-color:#e6e6e6}.btn-group.open .btn-primary.dropdown-toggle{background-color:#04c}.btn-group.open .btn-warning.dropdown-toggle{background-color:#f89406}.btn-group.open .btn-danger.dropdown-toggle{background-color:#bd362f}.btn-group.open .btn-success.dropdown-toggle{background-color:#51a351}.btn-group.open .btn-info.dropdown-toggle{background-color:#2f96b4}.btn-group.open .btn-inverse.dropdown-toggle{background-color:#222}.btn .caret{margin-top:8px;margin-left:0}.btn-mini .caret,.btn-small .caret,.btn-large .caret{margin-top:6px}.btn-large .caret{border-top-width:5px;border-right-width:5px;border-left-width:5px}.dropup .btn-large .caret{border-bottom-width:5px}.btn-primary .caret,.btn-warning .caret,.btn-danger .caret,.btn-info .caret,.btn-success .caret,.btn-inverse .caret{border-top-color:#fff;border-bottom-color:#fff}.btn-group-vertical{display:inline-block;*display:inline;*zoom:1}.btn-group-vertical .btn{display:block;float:none;width:100%;-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.btn-group-vertical .btn+.btn{margin-top:-1px;margin-left:0}.btn-group-vertical .btn:first-child{-webkit-border-radius:4px 4px 0 0;-moz-border-radius:4px 4px 0 0;border-radius:4px 4px 0 0}.btn-group-vertical .btn:last-child{-webkit-border-radius:0 0 4px 4px;-moz-border-radius:0 0 4px 4px;border-radius:0 0 4px 4px}.btn-group-vertical .btn-large:first-child{-webkit-border-radius:6px 6px 0 0;-moz-border-radius:6px 6px 0 0;border-radius:6px 6px 0 0}.btn-group-vertical .btn-large:last-child{-webkit-border-radius:0 0 6px 6px;-moz-border-radius:0 0 6px 6px;border-radius:0 0 6px 6px}.alert{padding:8px 35px 8px 14px;margin-bottom:20px;color:#c09853;text-shadow:0 1px 0 rgba(255,255,255,0.5);background-color:#fcf8e3;border:1px solid #fbeed5;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}.alert h4{margin:0}.alert .close{position:relative;top:-2px;right:-21px;line-height:20px}.alert-success{color:#468847;background-color:#dff0d8;border-color:#d6e9c6}.alert-danger,.alert-error{color:#b94a48;background-color:#f2dede;border-color:#eed3d7}.alert-info{color:#3a87ad;background-color:#d9edf7;border-color:#bce8f1}.alert-block{padding-top:14px;padding-bottom:14px}.alert-block>p,.alert-block>ul{margin-bottom:0}.alert-block p+p{margin-top:5px}.nav{margin-bottom:20px;margin-left:0;list-style:none}.nav>li>a{display:block}.nav>li>a:hover{text-decoration:none;background-color:#eee}.nav>.pull-right{float:right}.nav-header{display:block;padding:3px 15px;font-size:11px;font-weight:bold;line-height:20px;color:#999;text-shadow:0 1px 0 rgba(255,255,255,0.5);text-transform:uppercase}.nav li+.nav-header{margin-top:9px}.nav-list{padding-right:15px;padding-left:15px;margin-bottom:0}.nav-list>li>a,.nav-list .nav-header{margin-right:-15px;margin-left:-15px;text-shadow:0 1px 0 rgba(255,255,255,0.5)}.nav-list>li>a{padding:3px 15px}.nav-list>.active>a,.nav-list>.active>a:hover{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.2);background-color:#08c}.nav-list [class^="icon-"],.nav-list [class*=" icon-"]{margin-right:2px}.nav-list .divider{*width:100%;height:1px;margin:9px 1px;*margin:-5px 0 5px;overflow:hidden;background-color:#e5e5e5;border-bottom:1px solid #fff}.nav-tabs,.nav-pills{*zoom:1}.nav-tabs:before,.nav-pills:before,.nav-tabs:after,.nav-pills:after{display:table;line-height:0;content:""}.nav-tabs:after,.nav-pills:after{clear:both}.nav-tabs>li,.nav-pills>li{float:left}.nav-tabs>li>a,.nav-pills>li>a{padding-right:12px;padding-left:12px;margin-right:2px;line-height:14px}.nav-tabs{border-bottom:1px solid #ddd}.nav-tabs>li{margin-bottom:-1px}.nav-tabs>li>a{padding-top:8px;padding-bottom:8px;line-height:20px;border:1px solid transparent;-webkit-border-radius:4px 4px 0 0;-moz-border-radius:4px 4px 0 0;border-radius:4px 4px 0 0}.nav-tabs>li>a:hover{border-color:#eee #eee #ddd}.nav-tabs>.active>a,.nav-tabs>.active>a:hover{color:#555;cursor:default;background-color:#fff;border:1px solid #ddd;border-bottom-color:transparent}.nav-pills>li>a{padding-top:8px;padding-bottom:8px;margin-top:2px;margin-bottom:2px;-webkit-border-radius:5px;-moz-border-radius:5px;border-radius:5px}.nav-pills>.active>a,.nav-pills>.active>a:hover{color:#fff;background-color:#08c}.nav-stacked>li{float:none}.nav-stacked>li>a{margin-right:0}.nav-tabs.nav-stacked{border-bottom:0}.nav-tabs.nav-stacked>li>a{border:1px solid #ddd;-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.nav-tabs.nav-stacked>li:first-child>a{-webkit-border-top-right-radius:4px;border-top-right-radius:4px;-webkit-border-top-left-radius:4px;border-top-left-radius:4px;-moz-border-radius-topright:4px;-moz-border-radius-topleft:4px}.nav-tabs.nav-stacked>li:last-child>a{-webkit-border-bottom-right-radius:4px;border-bottom-right-radius:4px;-webkit-border-bottom-left-radius:4px;border-bottom-left-radius:4px;-moz-border-radius-bottomright:4px;-moz-border-radius-bottomleft:4px}.nav-tabs.nav-stacked>li>a:hover{z-index:2;border-color:#ddd}.nav-pills.nav-stacked>li>a{margin-bottom:3px}.nav-pills.nav-stacked>li:last-child>a{margin-bottom:1px}.nav-tabs .dropdown-menu{-webkit-border-radius:0 0 6px 6px;-moz-border-radius:0 0 6px 6px;border-radius:0 0 6px 6px}.nav-pills .dropdown-menu{-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px}.nav .dropdown-toggle .caret{margin-top:6px;border-top-color:#08c;border-bottom-color:#08c}.nav .dropdown-toggle:hover .caret{border-top-color:#005580;border-bottom-color:#005580}.nav-tabs .dropdown-toggle .caret{margin-top:8px}.nav .active .dropdown-toggle .caret{border-top-color:#fff;border-bottom-color:#fff}.nav-tabs .active .dropdown-toggle .caret{border-top-color:#555;border-bottom-color:#555}.nav>.dropdown.active>a:hover{cursor:pointer}.nav-tabs .open .dropdown-toggle,.nav-pills .open .dropdown-toggle,.nav>li.dropdown.open.active>a:hover{color:#fff;background-color:#999;border-color:#999}.nav li.dropdown.open .caret,.nav li.dropdown.open.active .caret,.nav li.dropdown.open a:hover .caret{border-top-color:#fff;border-bottom-color:#fff;opacity:1;filter:alpha(opacity=100)}.tabs-stacked .open>a:hover{border-color:#999}.tabbable{*zoom:1}.tabbable:before,.tabbable:after{display:table;line-height:0;content:""}.tabbable:after{clear:both}.tab-content{overflow:auto}.tabs-below>.nav-tabs,.tabs-right>.nav-tabs,.tabs-left>.nav-tabs{border-bottom:0}.tab-content>.tab-pane,.pill-content>.pill-pane{display:none}.tab-content>.active,.pill-content>.active{display:block}.tabs-below>.nav-tabs{border-top:1px solid #ddd}.tabs-below>.nav-tabs>li{margin-top:-1px;margin-bottom:0}.tabs-below>.nav-tabs>li>a{-webkit-border-radius:0 0 4px 4px;-moz-border-radius:0 0 4px 4px;border-radius:0 0 4px 4px}.tabs-below>.nav-tabs>li>a:hover{border-top-color:#ddd;border-bottom-color:transparent}.tabs-below>.nav-tabs>.active>a,.tabs-below>.nav-tabs>.active>a:hover{border-color:transparent #ddd #ddd #ddd}.tabs-left>.nav-tabs>li,.tabs-right>.nav-tabs>li{float:none}.tabs-left>.nav-tabs>li>a,.tabs-right>.nav-tabs>li>a{min-width:74px;margin-right:0;margin-bottom:3px}.tabs-left>.nav-tabs{float:left;margin-right:19px;border-right:1px solid #ddd}.tabs-left>.nav-tabs>li>a{margin-right:-1px;-webkit-border-radius:4px 0 0 4px;-moz-border-radius:4px 0 0 4px;border-radius:4px 0 0 4px}.tabs-left>.nav-tabs>li>a:hover{border-color:#eee #ddd #eee #eee}.tabs-left>.nav-tabs .active>a,.tabs-left>.nav-tabs .active>a:hover{border-color:#ddd transparent #ddd #ddd;*border-right-color:#fff}.tabs-right>.nav-tabs{float:right;margin-left:19px;border-left:1px solid #ddd}.tabs-right>.nav-tabs>li>a{margin-left:-1px;-webkit-border-radius:0 4px 4px 0;-moz-border-radius:0 4px 4px 0;border-radius:0 4px 4px 0}.tabs-right>.nav-tabs>li>a:hover{border-color:#eee #eee #eee #ddd}.tabs-right>.nav-tabs .active>a,.tabs-right>.nav-tabs .active>a:hover{border-color:#ddd #ddd #ddd transparent;*border-left-color:#fff}.nav>.disabled>a{color:#999}.nav>.disabled>a:hover{text-decoration:none;cursor:default;background-color:transparent}.navbar{*position:relative;*z-index:2;margin-bottom:20px;overflow:visible;color:#777}.navbar-inner{min-height:40px;padding-right:20px;padding-left:20px;background-color:#fafafa;background-image:-moz-linear-gradient(top,#fff,#f2f2f2);background-image:-webkit-gradient(linear,0 0,0 100%,from(#fff),to(#f2f2f2));background-image:-webkit-linear-gradient(top,#fff,#f2f2f2);background-image:-o-linear-gradient(top,#fff,#f2f2f2);background-image:linear-gradient(to bottom,#fff,#f2f2f2);background-repeat:repeat-x;border:1px solid #d4d4d4;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffffffff',endColorstr='#fff2f2f2',GradientType=0);*zoom:1;-webkit-box-shadow:0 1px 4px rgba(0,0,0,0.065);-moz-box-shadow:0 1px 4px rgba(0,0,0,0.065);box-shadow:0 1px 4px rgba(0,0,0,0.065)}.navbar-inner:before,.navbar-inner:after{display:table;line-height:0;content:""}.navbar-inner:after{clear:both}.navbar .container{width:auto}.nav-collapse.collapse{height:auto;overflow:visible}.navbar .brand{display:block;float:left;padding:10px 20px 10px;margin-left:-20px;font-size:20px;font-weight:200;color:#777;text-shadow:0 1px 0 #fff}.navbar .brand:hover{text-decoration:none}.navbar-text{margin-bottom:0;line-height:40px}.navbar-link{color:#777}.navbar-link:hover{color:#333}.navbar .divider-vertical{height:40px;margin:0 9px;border-right:1px solid #fff;border-left:1px solid #f2f2f2}.navbar .btn,.navbar .btn-group{margin-top:5px}.navbar .btn-group .btn,.navbar .input-prepend .btn,.navbar .input-append .btn{margin-top:0}.navbar-form{margin-bottom:0;*zoom:1}.navbar-form:before,.navbar-form:after{display:table;line-height:0;content:""}.navbar-form:after{clear:both}.navbar-form input,.navbar-form select,.navbar-form .radio,.navbar-form .checkbox{margin-top:5px}.navbar-form input,.navbar-form select,.navbar-form .btn{display:inline-block;margin-bottom:0}.navbar-form input[type="image"],.navbar-form input[type="checkbox"],.navbar-form input[type="radio"]{margin-top:3px}.navbar-form .input-append,.navbar-form .input-prepend{margin-top:6px;white-space:nowrap}.navbar-form .input-append input,.navbar-form .input-prepend input{margin-top:0}.navbar-search{position:relative;float:left;margin-top:5px;margin-bottom:0}.navbar-search .search-query{padding:4px 14px;margin-bottom:0;font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;font-size:13px;font-weight:normal;line-height:1;-webkit-border-radius:15px;-moz-border-radius:15px;border-radius:15px}.navbar-static-top{position:static;margin-bottom:0}.navbar-static-top .navbar-inner{-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.navbar-fixed-top,.navbar-fixed-bottom{position:fixed;right:0;left:0;z-index:1030;margin-bottom:0}.navbar-fixed-top .navbar-inner,.navbar-static-top .navbar-inner{border-width:0 0 1px}.navbar-fixed-bottom .navbar-inner{border-width:1px 0 0}.navbar-fixed-top .navbar-inner,.navbar-fixed-bottom .navbar-inner{padding-right:0;padding-left:0;-webkit-border-radius:0;-moz-border-radius:0;border-radius:0}.navbar-static-top .container,.navbar-fixed-top .container,.navbar-fixed-bottom .container{width:940px}.navbar-fixed-top{top:0}.navbar-fixed-top .navbar-inner,.navbar-static-top .navbar-inner{-webkit-box-shadow:0 1px 10px rgba(0,0,0,0.1);-moz-box-shadow:0 1px 10px rgba(0,0,0,0.1);box-shadow:0 1px 10px rgba(0,0,0,0.1)}.navbar-fixed-bottom{bottom:0}.navbar-fixed-bottom .navbar-inner{-webkit-box-shadow:0 -1px 10px rgba(0,0,0,0.1);-moz-box-shadow:0 -1px 10px rgba(0,0,0,0.1);box-shadow:0 -1px 10px rgba(0,0,0,0.1)}.navbar .nav{position:relative;left:0;display:block;float:left;margin:0 10px 0 0}.navbar .nav.pull-right{float:right;margin-right:0}.navbar .nav>li{float:left}.navbar .nav>li>a{float:none;padding:10px 15px 10px;color:#777;text-decoration:none;text-shadow:0 1px 0 #fff}.navbar .nav .dropdown-toggle .caret{margin-top:8px}.navbar .nav>li>a:focus,.navbar .nav>li>a:hover{color:#333;text-decoration:none;background-color:transparent}.navbar .nav>.active>a,.navbar .nav>.active>a:hover,.navbar .nav>.active>a:focus{color:#555;text-decoration:none;background-color:#e5e5e5;-webkit-box-shadow:inset 0 3px 8px rgba(0,0,0,0.125);-moz-box-shadow:inset 0 3px 8px rgba(0,0,0,0.125);box-shadow:inset 0 3px 8px rgba(0,0,0,0.125)}.navbar .btn-navbar{display:none;float:right;padding:7px 10px;margin-right:5px;margin-left:5px;color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#ededed;*background-color:#e5e5e5;background-image:-moz-linear-gradient(top,#f2f2f2,#e5e5e5);background-image:-webkit-gradient(linear,0 0,0 100%,from(#f2f2f2),to(#e5e5e5));background-image:-webkit-linear-gradient(top,#f2f2f2,#e5e5e5);background-image:-o-linear-gradient(top,#f2f2f2,#e5e5e5);background-image:linear-gradient(to bottom,#f2f2f2,#e5e5e5);background-repeat:repeat-x;border-color:#e5e5e5 #e5e5e5 #bfbfbf;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#fff2f2f2',endColorstr='#ffe5e5e5',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false);-webkit-box-shadow:inset 0 1px 0 rgba(255,255,255,0.1),0 1px 0 rgba(255,255,255,0.075);-moz-box-shadow:inset 0 1px 0 rgba(255,255,255,0.1),0 1px 0 rgba(255,255,255,0.075);box-shadow:inset 0 1px 0 rgba(255,255,255,0.1),0 1px 0 rgba(255,255,255,0.075)}.navbar .btn-navbar:hover,.navbar .btn-navbar:active,.navbar .btn-navbar.active,.navbar .btn-navbar.disabled,.navbar .btn-navbar[disabled]{color:#fff;background-color:#e5e5e5;*background-color:#d9d9d9}.navbar .btn-navbar:active,.navbar .btn-navbar.active{background-color:#ccc \9}.navbar .btn-navbar .icon-bar{display:block;width:18px;height:2px;background-color:#f5f5f5;-webkit-border-radius:1px;-moz-border-radius:1px;border-radius:1px;-webkit-box-shadow:0 1px 0 rgba(0,0,0,0.25);-moz-box-shadow:0 1px 0 rgba(0,0,0,0.25);box-shadow:0 1px 0 rgba(0,0,0,0.25)}.btn-navbar .icon-bar+.icon-bar{margin-top:3px}.navbar .nav>li>.dropdown-menu:before{position:absolute;top:-7px;left:9px;display:inline-block;border-right:7px solid transparent;border-bottom:7px solid #ccc;border-left:7px solid transparent;border-bottom-color:rgba(0,0,0,0.2);content:''}.navbar .nav>li>.dropdown-menu:after{position:absolute;top:-6px;left:10px;display:inline-block;border-right:6px solid transparent;border-bottom:6px solid #fff;border-left:6px solid transparent;content:''}.navbar-fixed-bottom .nav>li>.dropdown-menu:before{top:auto;bottom:-7px;border-top:7px solid #ccc;border-bottom:0;border-top-color:rgba(0,0,0,0.2)}.navbar-fixed-bottom .nav>li>.dropdown-menu:after{top:auto;bottom:-6px;border-top:6px solid #fff;border-bottom:0}.navbar .nav li.dropdown.open>.dropdown-toggle,.navbar .nav li.dropdown.active>.dropdown-toggle,.navbar .nav li.dropdown.open.active>.dropdown-toggle{color:#555;background-color:#e5e5e5}.navbar .nav li.dropdown>.dropdown-toggle .caret{border-top-color:#777;border-bottom-color:#777}.navbar .nav li.dropdown.open>.dropdown-toggle .caret,.navbar .nav li.dropdown.active>.dropdown-toggle .caret,.navbar .nav li.dropdown.open.active>.dropdown-toggle .caret{border-top-color:#555;border-bottom-color:#555}.navbar .pull-right>li>.dropdown-menu,.navbar .nav>li>.dropdown-menu.pull-right{right:0;left:auto}.navbar .pull-right>li>.dropdown-menu:before,.navbar .nav>li>.dropdown-menu.pull-right:before{right:12px;left:auto}.navbar .pull-right>li>.dropdown-menu:after,.navbar .nav>li>.dropdown-menu.pull-right:after{right:13px;left:auto}.navbar .pull-right>li>.dropdown-menu .dropdown-menu,.navbar .nav>li>.dropdown-menu.pull-right .dropdown-menu{right:100%;left:auto;margin-right:-1px;margin-left:0;-webkit-border-radius:6px 0 6px 6px;-moz-border-radius:6px 0 6px 6px;border-radius:6px 0 6px 6px}.navbar-inverse{color:#999}.navbar-inverse .navbar-inner{background-color:#1b1b1b;background-image:-moz-linear-gradient(top,#222,#111);background-image:-webkit-gradient(linear,0 0,0 100%,from(#222),to(#111));background-image:-webkit-linear-gradient(top,#222,#111);background-image:-o-linear-gradient(top,#222,#111);background-image:linear-gradient(to bottom,#222,#111);background-repeat:repeat-x;border-color:#252525;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff222222',endColorstr='#ff111111',GradientType=0)}.navbar-inverse .brand,.navbar-inverse .nav>li>a{color:#999;text-shadow:0 -1px 0 rgba(0,0,0,0.25)}.navbar-inverse .brand:hover,.navbar-inverse .nav>li>a:hover{color:#fff}.navbar-inverse .nav>li>a:focus,.navbar-inverse .nav>li>a:hover{color:#fff;background-color:transparent}.navbar-inverse .nav .active>a,.navbar-inverse .nav .active>a:hover,.navbar-inverse .nav .active>a:focus{color:#fff;background-color:#111}.navbar-inverse .navbar-link{color:#999}.navbar-inverse .navbar-link:hover{color:#fff}.navbar-inverse .divider-vertical{border-right-color:#222;border-left-color:#111}.navbar-inverse .nav li.dropdown.open>.dropdown-toggle,.navbar-inverse .nav li.dropdown.active>.dropdown-toggle,.navbar-inverse .nav li.dropdown.open.active>.dropdown-toggle{color:#fff;background-color:#111}.navbar-inverse .nav li.dropdown>.dropdown-toggle .caret{border-top-color:#999;border-bottom-color:#999}.navbar-inverse .nav li.dropdown.open>.dropdown-toggle .caret,.navbar-inverse .nav li.dropdown.active>.dropdown-toggle .caret,.navbar-inverse .nav li.dropdown.open.active>.dropdown-toggle .caret{border-top-color:#fff;border-bottom-color:#fff}.navbar-inverse .navbar-search .search-query{color:#fff;background-color:#515151;border-color:#111;-webkit-box-shadow:inset 0 1px 2px rgba(0,0,0,0.1),0 1px 0 rgba(255,255,255,0.15);-moz-box-shadow:inset 0 1px 2px rgba(0,0,0,0.1),0 1px 0 rgba(255,255,255,0.15);box-shadow:inset 0 1px 2px rgba(0,0,0,0.1),0 1px 0 rgba(255,255,255,0.15);-webkit-transition:none;-moz-transition:none;-o-transition:none;transition:none}.navbar-inverse .navbar-search .search-query:-moz-placeholder{color:#ccc}.navbar-inverse .navbar-search .search-query:-ms-input-placeholder{color:#ccc}.navbar-inverse .navbar-search .search-query::-webkit-input-placeholder{color:#ccc}.navbar-inverse .navbar-search .search-query:focus,.navbar-inverse .navbar-search .search-query.focused{padding:5px 15px;color:#333;text-shadow:0 1px 0 #fff;background-color:#fff;border:0;outline:0;-webkit-box-shadow:0 0 3px rgba(0,0,0,0.15);-moz-box-shadow:0 0 3px rgba(0,0,0,0.15);box-shadow:0 0 3px rgba(0,0,0,0.15)}.navbar-inverse .btn-navbar{color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#0e0e0e;*background-color:#040404;background-image:-moz-linear-gradient(top,#151515,#040404);background-image:-webkit-gradient(linear,0 0,0 100%,from(#151515),to(#040404));background-image:-webkit-linear-gradient(top,#151515,#040404);background-image:-o-linear-gradient(top,#151515,#040404);background-image:linear-gradient(to bottom,#151515,#040404);background-repeat:repeat-x;border-color:#040404 #040404 #000;border-color:rgba(0,0,0,0.1) rgba(0,0,0,0.1) rgba(0,0,0,0.25);filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff151515',endColorstr='#ff040404',GradientType=0);filter:progid:DXImageTransform.Microsoft.gradient(enabled=false)}.navbar-inverse .btn-navbar:hover,.navbar-inverse .btn-navbar:active,.navbar-inverse .btn-navbar.active,.navbar-inverse .btn-navbar.disabled,.navbar-inverse .btn-navbar[disabled]{color:#fff;background-color:#040404;*background-color:#000}.navbar-inverse .btn-navbar:active,.navbar-inverse .btn-navbar.active{background-color:#000 \9}.breadcrumb{padding:8px 15px;margin:0 0 20px;list-style:none;background-color:#f5f5f5;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}.breadcrumb li{display:inline-block;*display:inline;text-shadow:0 1px 0 #fff;*zoom:1}.breadcrumb .divider{padding:0 5px;color:#ccc}.breadcrumb .active{color:#999}.pagination{margin:20px 0}.pagination ul{display:inline-block;*display:inline;margin-bottom:0;margin-left:0;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;*zoom:1;-webkit-box-shadow:0 1px 2px rgba(0,0,0,0.05);-moz-box-shadow:0 1px 2px rgba(0,0,0,0.05);box-shadow:0 1px 2px rgba(0,0,0,0.05)}.pagination ul>li{display:inline}.pagination ul>li>a,.pagination ul>li>span{float:left;padding:4px 12px;line-height:20px;text-decoration:none;background-color:#fff;border:1px solid #ddd;border-left-width:0}.pagination ul>li>a:hover,.pagination ul>.active>a,.pagination ul>.active>span{background-color:#f5f5f5}.pagination ul>.active>a,.pagination ul>.active>span{color:#999;cursor:default}.pagination ul>.disabled>span,.pagination ul>.disabled>a,.pagination ul>.disabled>a:hover{color:#999;cursor:default;background-color:transparent}.pagination ul>li:first-child>a,.pagination ul>li:first-child>span{border-left-width:1px;-webkit-border-bottom-left-radius:4px;border-bottom-left-radius:4px;-webkit-border-top-left-radius:4px;border-top-left-radius:4px;-moz-border-radius-bottomleft:4px;-moz-border-radius-topleft:4px}.pagination ul>li:last-child>a,.pagination ul>li:last-child>span{-webkit-border-top-right-radius:4px;border-top-right-radius:4px;-webkit-border-bottom-right-radius:4px;border-bottom-right-radius:4px;-moz-border-radius-topright:4px;-moz-border-radius-bottomright:4px}.pagination-centered{text-align:center}.pagination-right{text-align:right}.pagination-large ul>li>a,.pagination-large ul>li>span{padding:11px 19px;font-size:17.5px}.pagination-large ul>li:first-child>a,.pagination-large ul>li:first-child>span{-webkit-border-bottom-left-radius:6px;border-bottom-left-radius:6px;-webkit-border-top-left-radius:6px;border-top-left-radius:6px;-moz-border-radius-bottomleft:6px;-moz-border-radius-topleft:6px}.pagination-large ul>li:last-child>a,.pagination-large ul>li:last-child>span{-webkit-border-top-right-radius:6px;border-top-right-radius:6px;-webkit-border-bottom-right-radius:6px;border-bottom-right-radius:6px;-moz-border-radius-topright:6px;-moz-border-radius-bottomright:6px}.pagination-mini ul>li:first-child>a,.pagination-small ul>li:first-child>a,.pagination-mini ul>li:first-child>span,.pagination-small ul>li:first-child>span{-webkit-border-bottom-left-radius:3px;border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-top-left-radius:3px;-moz-border-radius-bottomleft:3px;-moz-border-radius-topleft:3px}.pagination-mini ul>li:last-child>a,.pagination-small ul>li:last-child>a,.pagination-mini ul>li:last-child>span,.pagination-small ul>li:last-child>span{-webkit-border-top-right-radius:3px;border-top-right-radius:3px;-webkit-border-bottom-right-radius:3px;border-bottom-right-radius:3px;-moz-border-radius-topright:3px;-moz-border-radius-bottomright:3px}.pagination-small ul>li>a,.pagination-small ul>li>span{padding:2px 10px;font-size:11.9px}.pagination-mini ul>li>a,.pagination-mini ul>li>span{padding:1px 6px;font-size:10.5px}.pager{margin:20px 0;text-align:center;list-style:none;*zoom:1}.pager:before,.pager:after{display:table;line-height:0;content:""}.pager:after{clear:both}.pager li{display:inline}.pager li>a,.pager li>span{display:inline-block;padding:5px 14px;background-color:#fff;border:1px solid #ddd;-webkit-border-radius:15px;-moz-border-radius:15px;border-radius:15px}.pager li>a:hover{text-decoration:none;background-color:#f5f5f5}.pager .next>a,.pager .next>span{float:right}.pager .previous>a,.pager .previous>span{float:left}.pager .disabled>a,.pager .disabled>a:hover,.pager .disabled>span{color:#999;cursor:default;background-color:#fff}.modal-backdrop{position:fixed;top:0;right:0;bottom:0;left:0;z-index:1040;background-color:#000}.modal-backdrop.fade{opacity:0}.modal-backdrop,.modal-backdrop.fade.in{opacity:.8;filter:alpha(opacity=80)}.modal{position:fixed;top:50%;left:50%;z-index:1050;width:560px;margin:-250px 0 0 -280px;background-color:#fff;border:1px solid #999;border:1px solid rgba(0,0,0,0.3);*border:1px solid #999;-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px;outline:0;-webkit-box-shadow:0 3px 7px rgba(0,0,0,0.3);-moz-box-shadow:0 3px 7px rgba(0,0,0,0.3);box-shadow:0 3px 7px rgba(0,0,0,0.3);-webkit-background-clip:padding-box;-moz-background-clip:padding-box;background-clip:padding-box}.modal.fade{top:-25%;-webkit-transition:opacity .3s linear,top .3s ease-out;-moz-transition:opacity .3s linear,top .3s ease-out;-o-transition:opacity .3s linear,top .3s ease-out;transition:opacity .3s linear,top .3s ease-out}.modal.fade.in{top:50%}.modal-header{padding:9px 15px;border-bottom:1px solid #eee}.modal-header .close{margin-top:2px}.modal-header h3{margin:0;line-height:30px}.modal-body{max-height:400px;padding:15px;overflow-y:auto}.modal-form{margin-bottom:0}.modal-footer{padding:14px 15px 15px;margin-bottom:0;text-align:right;background-color:#f5f5f5;border-top:1px solid #ddd;-webkit-border-radius:0 0 6px 6px;-moz-border-radius:0 0 6px 6px;border-radius:0 0 6px 6px;*zoom:1;-webkit-box-shadow:inset 0 1px 0 #fff;-moz-box-shadow:inset 0 1px 0 #fff;box-shadow:inset 0 1px 0 #fff}.modal-footer:before,.modal-footer:after{display:table;line-height:0;content:""}.modal-footer:after{clear:both}.modal-footer .btn+.btn{margin-bottom:0;margin-left:5px}.modal-footer .btn-group .btn+.btn{margin-left:-1px}.modal-footer .btn-block+.btn-block{margin-left:0}.tooltip{position:absolute;z-index:1030;display:block;padding:5px;font-size:11px;opacity:0;filter:alpha(opacity=0);visibility:visible}.tooltip.in{opacity:.8;filter:alpha(opacity=80)}.tooltip.top{margin-top:-3px}.tooltip.right{margin-left:3px}.tooltip.bottom{margin-top:3px}.tooltip.left{margin-left:-3px}.tooltip-inner{max-width:200px;padding:3px 8px;color:#fff;text-align:center;text-decoration:none;background-color:#000;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}.tooltip-arrow{position:absolute;width:0;height:0;border-color:transparent;border-style:solid}.tooltip.top .tooltip-arrow{bottom:0;left:50%;margin-left:-5px;border-top-color:#000;border-width:5px 5px 0}.tooltip.right .tooltip-arrow{top:50%;left:0;margin-top:-5px;border-right-color:#000;border-width:5px 5px 5px 0}.tooltip.left .tooltip-arrow{top:50%;right:0;margin-top:-5px;border-left-color:#000;border-width:5px 0 5px 5px}.tooltip.bottom .tooltip-arrow{top:0;left:50%;margin-left:-5px;border-bottom-color:#000;border-width:0 5px 5px}.popover{position:absolute;top:0;left:0;z-index:1010;display:none;width:236px;padding:1px;background-color:#fff;border:1px solid #ccc;border:1px solid rgba(0,0,0,0.2);-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px;-webkit-box-shadow:0 5px 10px rgba(0,0,0,0.2);-moz-box-shadow:0 5px 10px rgba(0,0,0,0.2);box-shadow:0 5px 10px rgba(0,0,0,0.2);-webkit-background-clip:padding-box;-moz-background-clip:padding;background-clip:padding-box}.popover.top{margin-top:-10px}.popover.right{margin-left:10px}.popover.bottom{margin-top:10px}.popover.left{margin-left:-10px}.popover-title{padding:8px 14px;margin:0;font-size:14px;font-weight:normal;line-height:18px;background-color:#f7f7f7;border-bottom:1px solid #ebebeb;-webkit-border-radius:5px 5px 0 0;-moz-border-radius:5px 5px 0 0;border-radius:5px 5px 0 0}.popover-content{padding:9px 14px}.popover-content p,.popover-content ul,.popover-content ol{margin-bottom:0}.popover .arrow,.popover .arrow:after{position:absolute;display:inline-block;width:0;height:0;border-color:transparent;border-style:solid}.popover .arrow:after{z-index:-1;content:""}.popover.top .arrow{bottom:-10px;left:50%;margin-left:-10px;border-top-color:#fff;border-width:10px 10px 0}.popover.top .arrow:after{bottom:-1px;left:-11px;border-top-color:rgba(0,0,0,0.25);border-width:11px 11px 0}.popover.right .arrow{top:50%;left:-10px;margin-top:-10px;border-right-color:#fff;border-width:10px 10px 10px 0}.popover.right .arrow:after{bottom:-11px;left:-1px;border-right-color:rgba(0,0,0,0.25);border-width:11px 11px 11px 0}.popover.bottom .arrow{top:-10px;left:50%;margin-left:-10px;border-bottom-color:#fff;border-width:0 10px 10px}.popover.bottom .arrow:after{top:-1px;left:-11px;border-bottom-color:rgba(0,0,0,0.25);border-width:0 11px 11px}.popover.left .arrow{top:50%;right:-10px;margin-top:-10px;border-left-color:#fff;border-width:10px 0 10px 10px}.popover.left .arrow:after{right:-1px;bottom:-11px;border-left-color:rgba(0,0,0,0.25);border-width:11px 0 11px 11px}.thumbnails{margin-left:-20px;list-style:none;*zoom:1}.thumbnails:before,.thumbnails:after{display:table;line-height:0;content:""}.thumbnails:after{clear:both}.row-fluid .thumbnails{margin-left:0}.thumbnails>li{float:left;margin-bottom:20px;margin-left:20px}.thumbnail{display:block;padding:4px;line-height:20px;border:1px solid #ddd;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;-webkit-box-shadow:0 1px 3px rgba(0,0,0,0.055);-moz-box-shadow:0 1px 3px rgba(0,0,0,0.055);box-shadow:0 1px 3px rgba(0,0,0,0.055);-webkit-transition:all .2s ease-in-out;-moz-transition:all .2s ease-in-out;-o-transition:all .2s ease-in-out;transition:all .2s ease-in-out}a.thumbnail:hover{border-color:#08c;-webkit-box-shadow:0 1px 4px rgba(0,105,214,0.25);-moz-box-shadow:0 1px 4px rgba(0,105,214,0.25);box-shadow:0 1px 4px rgba(0,105,214,0.25)}.thumbnail>img{display:block;max-width:100%;margin-right:auto;margin-left:auto}.thumbnail .caption{padding:9px;color:#555}.media,.media-body{overflow:hidden;*overflow:visible;zoom:1}.media,.media .media{margin-top:15px}.media:first-child{margin-top:0}.media-object{display:block}.media-heading{margin:0 0 5px}.media .pull-left{margin-right:10px}.media .pull-right{margin-left:10px}.media-list{margin-left:0;list-style:none}.label,.badge{display:inline-block;padding:2px 4px;font-size:11.844px;font-weight:bold;line-height:14px;color:#fff;text-shadow:0 -1px 0 rgba(0,0,0,0.25);white-space:nowrap;vertical-align:baseline;background-color:#999}.label{-webkit-border-radius:3px;-moz-border-radius:3px;border-radius:3px}.badge{padding-right:9px;padding-left:9px;-webkit-border-radius:9px;-moz-border-radius:9px;border-radius:9px}a.label:hover,a.badge:hover{color:#fff;text-decoration:none;cursor:pointer}.label-important,.badge-important{background-color:#b94a48}.label-important[href],.badge-important[href]{background-color:#953b39}.label-warning,.badge-warning{background-color:#f89406}.label-warning[href],.badge-warning[href]{background-color:#c67605}.label-success,.badge-success{background-color:#468847}.label-success[href],.badge-success[href]{background-color:#356635}.label-info,.badge-info{background-color:#3a87ad}.label-info[href],.badge-info[href]{background-color:#2d6987}.label-inverse,.badge-inverse{background-color:#333}.label-inverse[href],.badge-inverse[href]{background-color:#1a1a1a}.btn .label,.btn .badge{position:relative;top:-1px}.btn-mini .label,.btn-mini .badge{top:0}@-webkit-keyframes progress-bar-stripes{from{background-position:40px 0}to{background-position:0 0}}@-moz-keyframes progress-bar-stripes{from{background-position:40px 0}to{background-position:0 0}}@-ms-keyframes progress-bar-stripes{from{background-position:40px 0}to{background-position:0 0}}@-o-keyframes progress-bar-stripes{from{background-position:0 0}to{background-position:40px 0}}@keyframes progress-bar-stripes{from{background-position:40px 0}to{background-position:0 0}}.progress{height:20px;margin-bottom:20px;overflow:hidden;background-color:#f7f7f7;background-image:-moz-linear-gradient(top,#f5f5f5,#f9f9f9);background-image:-webkit-gradient(linear,0 0,0 100%,from(#f5f5f5),to(#f9f9f9));background-image:-webkit-linear-gradient(top,#f5f5f5,#f9f9f9);background-image:-o-linear-gradient(top,#f5f5f5,#f9f9f9);background-image:linear-gradient(to bottom,#f5f5f5,#f9f9f9);background-repeat:repeat-x;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#fff5f5f5',endColorstr='#fff9f9f9',GradientType=0);-webkit-box-shadow:inset 0 1px 2px rgba(0,0,0,0.1);-moz-box-shadow:inset 0 1px 2px rgba(0,0,0,0.1);box-shadow:inset 0 1px 2px rgba(0,0,0,0.1)}.progress .bar{float:left;width:0;height:100%;font-size:12px;color:#fff;text-align:center;text-shadow:0 -1px 0 rgba(0,0,0,0.25);background-color:#0e90d2;background-image:-moz-linear-gradient(top,#149bdf,#0480be);background-image:-webkit-gradient(linear,0 0,0 100%,from(#149bdf),to(#0480be));background-image:-webkit-linear-gradient(top,#149bdf,#0480be);background-image:-o-linear-gradient(top,#149bdf,#0480be);background-image:linear-gradient(to bottom,#149bdf,#0480be);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff149bdf',endColorstr='#ff0480be',GradientType=0);-webkit-box-shadow:inset 0 -1px 0 rgba(0,0,0,0.15);-moz-box-shadow:inset 0 -1px 0 rgba(0,0,0,0.15);box-shadow:inset 0 -1px 0 rgba(0,0,0,0.15);-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;-webkit-transition:width .6s ease;-moz-transition:width .6s ease;-o-transition:width .6s ease;transition:width .6s ease}.progress .bar+.bar{-webkit-box-shadow:inset 1px 0 0 rgba(0,0,0,0.15),inset 0 -1px 0 rgba(0,0,0,0.15);-moz-box-shadow:inset 1px 0 0 rgba(0,0,0,0.15),inset 0 -1px 0 rgba(0,0,0,0.15);box-shadow:inset 1px 0 0 rgba(0,0,0,0.15),inset 0 -1px 0 rgba(0,0,0,0.15)}.progress-striped .bar{background-color:#149bdf;background-image:-webkit-gradient(linear,0 100%,100% 0,color-stop(0.25,rgba(255,255,255,0.15)),color-stop(0.25,transparent),color-stop(0.5,transparent),color-stop(0.5,rgba(255,255,255,0.15)),color-stop(0.75,rgba(255,255,255,0.15)),color-stop(0.75,transparent),to(transparent));background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-moz-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-o-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);-webkit-background-size:40px 40px;-moz-background-size:40px 40px;-o-background-size:40px 40px;background-size:40px 40px}.progress.active .bar{-webkit-animation:progress-bar-stripes 2s linear infinite;-moz-animation:progress-bar-stripes 2s linear infinite;-ms-animation:progress-bar-stripes 2s linear infinite;-o-animation:progress-bar-stripes 2s linear infinite;animation:progress-bar-stripes 2s linear infinite}.progress-danger .bar,.progress .bar-danger{background-color:#dd514c;background-image:-moz-linear-gradient(top,#ee5f5b,#c43c35);background-image:-webkit-gradient(linear,0 0,0 100%,from(#ee5f5b),to(#c43c35));background-image:-webkit-linear-gradient(top,#ee5f5b,#c43c35);background-image:-o-linear-gradient(top,#ee5f5b,#c43c35);background-image:linear-gradient(to bottom,#ee5f5b,#c43c35);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffee5f5b',endColorstr='#ffc43c35',GradientType=0)}.progress-danger.progress-striped .bar,.progress-striped .bar-danger{background-color:#ee5f5b;background-image:-webkit-gradient(linear,0 100%,100% 0,color-stop(0.25,rgba(255,255,255,0.15)),color-stop(0.25,transparent),color-stop(0.5,transparent),color-stop(0.5,rgba(255,255,255,0.15)),color-stop(0.75,rgba(255,255,255,0.15)),color-stop(0.75,transparent),to(transparent));background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-moz-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-o-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent)}.progress-success .bar,.progress .bar-success{background-color:#5eb95e;background-image:-moz-linear-gradient(top,#62c462,#57a957);background-image:-webkit-gradient(linear,0 0,0 100%,from(#62c462),to(#57a957));background-image:-webkit-linear-gradient(top,#62c462,#57a957);background-image:-o-linear-gradient(top,#62c462,#57a957);background-image:linear-gradient(to bottom,#62c462,#57a957);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff62c462',endColorstr='#ff57a957',GradientType=0)}.progress-success.progress-striped .bar,.progress-striped .bar-success{background-color:#62c462;background-image:-webkit-gradient(linear,0 100%,100% 0,color-stop(0.25,rgba(255,255,255,0.15)),color-stop(0.25,transparent),color-stop(0.5,transparent),color-stop(0.5,rgba(255,255,255,0.15)),color-stop(0.75,rgba(255,255,255,0.15)),color-stop(0.75,transparent),to(transparent));background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-moz-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-o-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent)}.progress-info .bar,.progress .bar-info{background-color:#4bb1cf;background-image:-moz-linear-gradient(top,#5bc0de,#339bb9);background-image:-webkit-gradient(linear,0 0,0 100%,from(#5bc0de),to(#339bb9));background-image:-webkit-linear-gradient(top,#5bc0de,#339bb9);background-image:-o-linear-gradient(top,#5bc0de,#339bb9);background-image:linear-gradient(to bottom,#5bc0de,#339bb9);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff5bc0de',endColorstr='#ff339bb9',GradientType=0)}.progress-info.progress-striped .bar,.progress-striped .bar-info{background-color:#5bc0de;background-image:-webkit-gradient(linear,0 100%,100% 0,color-stop(0.25,rgba(255,255,255,0.15)),color-stop(0.25,transparent),color-stop(0.5,transparent),color-stop(0.5,rgba(255,255,255,0.15)),color-stop(0.75,rgba(255,255,255,0.15)),color-stop(0.75,transparent),to(transparent));background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-moz-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-o-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent)}.progress-warning .bar,.progress .bar-warning{background-color:#faa732;background-image:-moz-linear-gradient(top,#fbb450,#f89406);background-image:-webkit-gradient(linear,0 0,0 100%,from(#fbb450),to(#f89406));background-image:-webkit-linear-gradient(top,#fbb450,#f89406);background-image:-o-linear-gradient(top,#fbb450,#f89406);background-image:linear-gradient(to bottom,#fbb450,#f89406);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#fffbb450',endColorstr='#fff89406',GradientType=0)}.progress-warning.progress-striped .bar,.progress-striped .bar-warning{background-color:#fbb450;background-image:-webkit-gradient(linear,0 100%,100% 0,color-stop(0.25,rgba(255,255,255,0.15)),color-stop(0.25,transparent),color-stop(0.5,transparent),color-stop(0.5,rgba(255,255,255,0.15)),color-stop(0.75,rgba(255,255,255,0.15)),color-stop(0.75,transparent),to(transparent));background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-moz-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:-o-linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent);background-image:linear-gradient(45deg,rgba(255,255,255,0.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,0.15) 50%,rgba(255,255,255,0.15) 75%,transparent 75%,transparent)}.accordion{margin-bottom:20px}.accordion-group{margin-bottom:2px;border:1px solid #e5e5e5;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px}.accordion-heading{border-bottom:0}.accordion-heading .accordion-toggle{display:block;padding:8px 15px}.accordion-toggle{cursor:pointer}.accordion-inner{padding:9px 15px;border-top:1px solid #e5e5e5}.carousel{position:relative;margin-bottom:20px;line-height:1}.carousel-inner{position:relative;width:100%;overflow:hidden}.carousel .item{position:relative;display:none;-webkit-transition:.6s ease-in-out left;-moz-transition:.6s ease-in-out left;-o-transition:.6s ease-in-out left;transition:.6s ease-in-out left}.carousel .item>img{display:block;line-height:1}.carousel .active,.carousel .next,.carousel .prev{display:block}.carousel .active{left:0}.carousel .next,.carousel .prev{position:absolute;top:0;width:100%}.carousel .next{left:100%}.carousel .prev{left:-100%}.carousel .next.left,.carousel .prev.right{left:0}.carousel .active.left{left:-100%}.carousel .active.right{left:100%}.carousel-control{position:absolute;top:40%;left:15px;width:40px;height:40px;margin-top:-20px;font-size:60px;font-weight:100;line-height:30px;color:#fff;text-align:center;background:#222;border:3px solid #fff;-webkit-border-radius:23px;-moz-border-radius:23px;border-radius:23px;opacity:.5;filter:alpha(opacity=50)}.carousel-control.right{right:15px;left:auto}.carousel-control:hover{color:#fff;text-decoration:none;opacity:.9;filter:alpha(opacity=90)}.carousel-caption{position:absolute;right:0;bottom:0;left:0;padding:15px;background:#333;background:rgba(0,0,0,0.75)}.carousel-caption h4,.carousel-caption p{line-height:20px;color:#fff}.carousel-caption h4{margin:0 0 5px}.carousel-caption p{margin-bottom:0}.hero-unit{padding:60px;margin-bottom:30px;font-size:18px;font-weight:200;line-height:30px;color:inherit;background-color:#eee;-webkit-border-radius:6px;-moz-border-radius:6px;border-radius:6px}.hero-unit h1{margin-bottom:0;font-size:60px;line-height:1;letter-spacing:-1px;color:inherit}.hero-unit li{line-height:30px}.pull-right{float:right}.pull-left{float:left}.hide{display:none}.show{display:block}.invisible{visibility:hidden}.affix{position:fixed}
�PNG

   IHDR  �   �   �ӳ{  �PLTE���      ������   ������mmm   ���������      ������������   ���      ���������   ���������������������      ������   ��ⰰ�������������������   ���   ���   ��������������������ᒒ�������������ttt������   ��󻻻������������bbb������������������������������������������������������������������eeeggg��𶶶���������������������������������xxx��������������������������������󛛛�����������������������������������Ƽ��������������������������������������������������   �����������������������������������������������������������������������������������������������������������������������������������몪������������������֢�����������UUU������������������������������������������������������������   ��������������鿿�����������������rO�   �tRNS  ���#�_
/�����oS��?��C�kD���OS_������6��>4!~a�@1�_'o�n�ҋ���M���3�BQj��p&%!l��"Xqr;�� A[�<`�am}4�3/0I��PCM!6(*gK&YQ�GDP,�`�{VP�-�x�)h�7�e1]��W��$��1�b�zSܕcO��]����U;Zi<N#�)	86pV��:h�#�0Z�Q�JN��EDT��~��^  -IDATx^읇#Ǚ��b'4A$Ah �)�p�3�<M�F9Y9X��,�r�i���ھ��|�s��t9�s��޿�X� k��jv�@�l_��I��*~h��>�'y�"�������؆�K64�Y�*.v�@���c.};��tN%�DI����	!Z�Џ5L�H�2�6 ��ɯ��"��-b�E,,)�ʏ �B���>m�����n��6pm�R�O
wm@���V�#?�'C�ȑZ#��q����b��|$�:�)��/E�%��nR�q�C�hn��%�i�̓�����}l�m
?i�d�d�"�,��� `�H�"r.z�����~���(b�Q�U&��)�5��X#�����EM���R<�*p[�[%.�O�̣��k7� lIo�������J�F��lV!̡ăuH�`��������&�,�z��Rk$���|$�l���Xb�����jߪ�dU��?Σ$H���W��$U�'���H�E3*խ����U\}���(�
�zhVk}g�u�Rk$��%�|�T�|��ck�獳"��D����_W+����.Q���)�@���ƽ�H����b�s��l��T���D���R �2Xm�#a��3lY��z�j����㒚#!�	4�J��8�( ��c�v���t]�a��T����	��� D΅��Q?^-��_^$:\���V	�$��N|�=(v�Z'q�6�Z�׆���B5V���!y���3��K��㱿b� v4��x����R]al��!��I�o�P�@�t��Vy����L��٪ml�ڿI�Ub|[*��lke'*�Wd���d���D�ӝ}\W��_Wߝ����r�N�?���vޫ�۲X%��0u��oui*��JV��Ʀ�b%�}���i5I�YlN�E-w�ς�f_W3m�I��������-�m����Q)�S��k��TC7��m�<"��܌�b�T|��'��$�Ҙ�����R&>��Op��������6�������t���S��N\�ׯL��m��\�����r@�3�u�T
b7��t.5.q���3�r0�=�8T����i�J�\��6uF
��R�32^���'Ū�����x��I�	��F�8O{%8��kJ��MS�ȴd�BEd����W��CY��O:/O�N/�I��_=��xFE��! �=��i:o�~��� y�?��'��'��[͓[͓[͓[͓[ͭ��.�U>�$�P�Ʀ�c%�]��\c��:�|	�,e�S�Z,�o��Xr����X�!�R����@�Z�v� �0��> ?�*��<��� |����N6�0��;{�a�d��2��v+D��^t����[q !�۞V}��f��ۨϏ���Y��eॗ��)Vy�l|"f�U��q��@�Ǽ�4Y-��Y��-!�6a���B:o%�J��I���UQ|�U�K�O�`��=\����:�0���x��Pa��u�@��!�K��P�d�xhw1>�$j΍��v��Zd���x��S�UA�&[UR�d��7�ø��z�k��/���r�U^������w:I.�VǮ���c>q�.!�zS�r&���2�)Wg�	��R	-�i�Q	8����Pa\О�U%�iݡ��U�_=��p��	�Lu��(�N�?���0?�Æ:]�ά���t�B%�U|�����NsorN��f��	�,�P	!�v"
Y�6�hL�_� @ @�b�s�c���qg�v4|��|0lϟ���$S��9����bʱ��j#���~�����?o��}����}7sAPm:IV�=n���
!���{��{��h��Eࢪ�8�s �u ��oL���T�$��;V����s��cq�D�3������༂3.D�B������ B4 �&�V'��T�	`��D� 6����Ϸ�q�y�j�8V����*���X%���@s�\�jrN�$�|�=5�Ά '�mU��i��K��i�%C��I�:ssaƅ`*`��=�l��)>�u՘MeuS����I�_�O��L��_�}�o&���jz��� p���{�����lu�:O������)�s�%Q@��$�<]f�	��xO%��PCbhr2����� ���� PK���p�f5�Në3^o�����]�e�J����i�B��464��^t���uٲ�U֌:G4'���22Y�p���u�G'/Py�4?���.��SB�P_>����I	1t3Γ�B�ɭ�ɭ�ɭ�ɭ�V��V��V��V��Vs���]�!�67(��g�����y��@��4>Q�� ��V�F�}^Xׇ�ڼ���j���e�26	L���%��Y�G�h���l�C�}�)��<
�!�E����E�P�ZWZ���V+�@�R5{@ou�ɐ�4���&� ���H���6�e�y V��݀�Vť�����cqZ�ޒ�r��J��yB��y���Fz��FN�$��Hb����*+�jՏq�э�� ګ�kݿU�X��l�e������1����d�0d^�-�B%���}����{Y���%r�*�j5Ak5�u��"�,�:~�Ҹ�Y��~
h����SA�~��6���fu�lՇf��{ȵQtATH�Z�k���ƭ/ _���S��n���
�u']b�]|m`�B��ā����J,O$��du]�Zs��FL�:������a�����Ǚ���T4�o�~by?wp�j滥�A����(�x�]�����f���~an֧/����^�d�ڲ�c��� �Շ,!��1��i&�xi_VK@ip�̓9���Vi%a;��L?�0J�*���Ū5���U����'���x^�6�V[�^ �{�eU���|�:0�=0���d۫o���*J�q%�[���Y�N��.sQ�L�ud�[2��9�I��:W�n��������m�Xl�ڃ�6�!l�Nl��V�էKU���jV�\J%�Uߊ��B��LcKf�b��>a�=�b�~�R]aG%[����js@�<i�[Х*^.d;UI�R+�OD�2e�ܶ� ��Q��N3�4"1�������g�0���u�\��I}����wFV��4y/D��j��j��jn5On5On5On5On5��h�,ҷUr��]��]L^����%J��D���iɭ��G�ԝߴ�/ �%='q�å)����:��Q�<�X�.��'���[�@�P����v�/ɼ����>/9�MطݘU�>yɲX�@}����F��t�g^��vO\��Ӹwv�p���z3��K5i�!$P>�ā����'��Vƛ���L�2r��@�UM��K��Z�����6���tw�맟¦b�m�1�h|�|�]}~�0��MjA ����(J� ����JP68�C&yr��׉e}�j�_c�J�?�I0��k��>�W����	������|�B�ޝ�."TEXd� ��8��!cw�*E(�J)���!�[W"�j_���ТeX_��XB;���o��O0~?�:P�C�(.����[�����!Wq�%��*le��Y)E�<^�K�Z�T�60�.���#���A\���5;Rm�tkd�/8�)5~����^0� #�Ckg���e��y)����Ͷ��Ժ� �6ĥ�<�(?��&���u�A��V���m0^h�.�t�xR*��a��'�:,�H�|�ō���l5z�;8+e�#b'#|�}2�w(|Kc�J��l6�����w��^�Տ�o��i��3H��R	��̔9�,Y�gP�ְ:N�[5S����R��!���[)��]���i}` ���m���N�4Х���v�`|;f�(��F�lt���L�8��÷Z#�AO%��Y)N�U�5Y��e��d�J�E�3dZذ���<�x����ɝ��e �@�Pڧ���F�TR��2S�·��Φ/u�Z�~�C�3���X�z���U���x�\2�s���e �D��D.���fBO&en�'i��R%��?Fy�VsS~$u��m��w()��r��o�0*D���i!3�:On[B�!sʇB�p>ݣHT�1��;�8M�jnʏ��Ӥ��qp�1h�^�<��<��<���j��j��j��j��jn�����q�(qp�Ok���}���I?TY8H��mh�yK�̝u5�����I�t�e�nQBޗ`�R��`��E�P��ڦ����x�����>�>����yt�{?|��'j)�����}YU���U���{�@V�/�J1�F+���7䀉[OW�O[������y���UY������!?B��D%�D��Wj�>-Ai6x�z)���U 	R�����7d���@�g�� ��\�so�)�a�4�zf�[�W+���>�����P��>�
|��qL��G8�v���ȣ��l�j���2Z��t��+��V��A�6g<�/��Q�H��SrΣ����d}��Y�q��g]�sY]�;]F�C�@5�Y��Ֆ�5�C�3�8o�)k�1'��d6�>T*�ʆ��Uz(�m)��CD`��He/�.�:�zN��9pgo&N�C�׃�އ�>�W��հ_��Hj��)�Xe6F� �7p�m�-�`'�c���.����AZ=���^�e8��F�;<����J1{��+8'�ɪ'�և\A�*����[���R$U�Y)V��AyɃ�w)�Ec#<�T����\vW<�U1�IؘCDo��Yo��]�wm�aw��:B� :'�Z+�v�}��|�0��q���1�P�΃�*��u��T��7 �F3��9���A}$���f�+�o����[��I�5��ʰ�޽x(&����i ��ʼY���:c�Pp*��b��¸J���j�V7 l�jtsNk��v����[�fy3��g]�������u����鲱���g�J��E�0)Vił��ù�����\vW<�Ug�t �e�~B�[����A�����H�J��'�.��n��&	1Ԕ��	��o%gͱ_��N�
���5�.W���3y/D��d�yr���<��<��<��<��<���j�ܪ{�����waw�:6�dJ�;&���3�ptl���as������W_U���T�_'9{?�a���Ԭ���l /0���dHgqll�c����8�R�y�����m=ˢ�_�ͺ�[Է71�x"�"��S�IfV��r���x3�3y�) h����h�ՠ��0���?�r��5�x�����_�-����j����������чoO:��$��� XBX J��ѣ�1����#ֈu7�`�zu2��{�\;��uܗ�9@�0��V$2X���S�����&���Ba��[�O�~��j�N2ߠȪ/����jz_� ��n A��������~���u��h @GL�O�eɵ��?T���f<V�����e��@���* �-}�e��@��0Zt�/~������Xm0�*���*��H'\������u��S�E��m�Lֻ��6������;+{l��5۽����?u*����_�	Ni-:�I@,;�]����W�Y�`	*���߀n �SO��~�n���W�P�.��c����Z�T�u��� Po^ǃ7����w��B�RB�W�_m�dj���������B���6�:��*��H����]�����d�Q>�{R��������t�n(��z�!S�7o
����Ie���w �3]��bܗ���8�5|�i��Ϡ��R� �JkʱZ�RO+�8�U&�:]�Z�ieR����<I��~�|�d���,�j��릟�{��;�7�U �݌�X�B���`�����[�u5~�=z�q굵Ű�޹e ��b�c5���o���{;����ߩ�@;���n*T�ĵ2�$ܨ��0�'�Y-?
�j�[�Z��j����ӭ�v���i�-�*rD{�mL-,L�=��y��m��x���c:���We����vұ��oÏń�
��"dF����8[�T}ӵF�-�I��V��lV[P�����)DVC�8ݪ}|kZ������{����Y�|��xrr��xa���G_���>�(��J�M�ޗ7����Z@��5�a^�\G�z��s���ρU��*�rM�e�zT�^�:ɬ��ͦX=>�$
bi>�U&X�Qoybb�G��k��8� �
�Ҙ���n).Ս���� o���^M�m�d�Z���i�$s��o�o��*{�4���eLb�Lٳ"�"mx:�`:m�k�[�geT���ެ)���'0*T��B�{!��I��'��'��'��'��[͓[͓[͓[͓[]�Z���jQ�.e�'/��y�vQ�71�(Z&���X��?(_��Z�����){t�ڀm�Z���W�Ϗ�)��-C����jq�n�,̋�"�Iv���UL�!h���꛿���s�k��AcrN���佚ф���VE4�0�y�X��~�4zʸV㳰%��,���)f���qt�p�u�~�������*���^��0:����ܲ�3�3���J��O�(�����ZB?K�^ �v]�un��l����W����i0�p6��[착�C_5X�#�[��wX3�b��廫�R�{���NK�A�����e S���e�|���w��x���s��o>�P\儔ԕ6�;nV�m�f�I$�����V͓J- �J%֌��0��Uw�YЎ�S����n�u��m�藮���xz��˗V�ƫ�I�vn�W��_�qL�Z����"_�X�z���� 8�]Ap�����?��C�� ���5�4��3�zw(�{7e�*Ȳ`۰�!A�Q�:�KUn�����z�]�1y�V���Ga��C��m0�PY
ٚUx6TT&�hV�9V�
���Ӭ�zÑ� 1[�X�z�Z�����9�e�r�q�J���ND�/���g��X��*9o���N6�D��`�{���I�%�M�z9�T�Q�������7f�\"j��_3����~xB�'���ܷ��Y��]*KЌ�%"���5�"��qxq~���ƕ=����j���S�>j�V�&~]2�xz�F����1X��_y��D��<#N����RB��}K����/���i��y�����!V^��˿e�J���}/Fk��A��7��� ��S���+.�(ec���J:�z����W�Z���몖w���Q������~a����̈́�p�6,e5��,�+����,���������t�v�%O^O��O}�ן -O��7>e��kC�6�wa�_��C���|���9���*�����W��A�)�U�Jg�8<�Z���x^?���2�u��Y�����*^?��ڇKC�Z�[�����0.���C��@m�����$-��/~�|�Y��[e�w�eQ ���׶&c��O�4s|��c��J���ws��X�8/��6�/ڼ;�'F�LN^�8]��ead�Z1'�� ����^�������L��sBd�%�+M��`��SK��8פ����*��)gl�#�3"��gъ�S�����qtcxx��|H>����=��:�����m�j������U����v�q�y��s�ܒ�Lgl�C6+[F�SWg���9���wV3�1�A	��N��D�<�����$5e�(s������[�    ۨb�����aF.��]�K���    IEND�B`��PNG

   IHDR  �   �   ��   tEXtSoftware Adobe ImageReadyq�e<  1�IDATx��}ml\E��W��^ɺ�D$|nw'��;vю�8�m0��k<f�8ـ�<�h3$� b,mn��� ғ����0��L Y`6s'>�Q������S������n�S�V�;1K�G���s�ԩ�>Uo���TU�1cƖ�Yuּ��c��a&���#C,pؚ��>kں����U�LW
-s�n�3V�q��~N����o��c���I�~L� �{��-	���H8%_��M�£w�B��6EW��,Ģp������Y�2+�(Y���@��&��A�/�����3kX�h�ߍ�-a����A���<>P���'\���J�;(�}�# �Qz������:4��%m?nf�ntK*����l�9J���+�D��I��Yu1Y���Z^��(]YYE��f@���О�lX��z]�U�t�	��u��&�5-P���W�}��@t�|�#L��Y�=��s��܂���,w#+�R�+?�Ƌa�x��	X�0�"ea)�t�G�*ԡwV�w�V^�� rf%xB(qּ�4>��W�G�#��lWU<Ё����XJVѶ��l�����R���$k�DVr�I����7:�X<�s>%X�1��N��Ez��w���;y��9�z�9�O�%~��~��u��ɗ*�=�����I�x�c�y}��Y(���o��u
±N$�^�j���e\��iX�񝜬]��;Y-�r�� ��Ѳ�&��>�!�zlY�aVHVN԰�9=��]�=������mR��M���d��OUC�JUiT}r�W��W'�ڹu��)ʢ����F"YU�#��P�׾����&ܑ�Ѕ���R�O����wyz��m$���O����s?  +^�FT�����I�E�q�%��&�����~�>�M���}]��Ԗ��w�A��?
[���Nteexn�(�措���B�d��MT��pʥ�nq�q�S�?���bW����XmW6��x*{V_����!V�jΧ�s�VL^j���Xk�Qj�U����6���sk��̩n~�[�q�Ǹ�-��`�O���:G�����7��l��"k�������sR�e�2��v�Q�=�QƼJ�U�X`�g�Qy~	ď�K��Ȱ��E�]��#��P��:�t���d�\T�/u���������;�س�:�J�c-%'��e��q���
?j�"/yh���4��8�Zi������1�|JU���u�>��_���N���;hxw�NU�J�QU7\�j�̮bT�:�����B�?6����o�J��1Ί%��I
UY-I���i4{�=�rǤ7��@)H�K�J+�f�4�X�8C�d�?'j��1��� ��N���<�3�9����E<�w߬��V���z�E}��^_e檴p��t붾3��9�,��?���g�l�Y�O���<���x�x�|���a؎��Ue����F����	�1�;��{EF�0`����D�R���+�U�YiD����4�?�Y`|B���s2��yip�I�q�>W�o��V���T��G��zg#�
%���D0#ܠ3���[t�i�آ�(U�,�]�125��|��N�̭fw�7w� �����u+���]�D�b]��K� ��xbW�՛7|�В��X㕛���{U����c��G����X�k¬�|�(� h)IU�a)�lp 3��l���uPU�]D��)�/7~4W��t5�J}��V���
X�0���z� �VM���;>�Gԙ�^���|��gF:��jaZ��^)74C#j�wr,еS�l��G�u�;1���v�m><�)�}��<���VZue۠D�+j�y����J6V{j���K��>��Z���QՖ���&�mZ:���1�U�MB�~���
�a���:�/᜗:K�W�WOҠ&�����Y���2f����7cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘��g��*3f�F5�Lb���N��2#Tf=C�`!��ZG�Ue꣇�e��2V���<�1mkS����4iϗ*.�{N�8X�aj~���ڀ�nA�x,���%fE:�|�Y�DV�j
��¢�lg6�(:�k~���M�M��5?�4	]WO�>��诋W����Z�iG�|�Q�G�Je��K[Ycյ�pmjE\f/�ǎ8&�OQ�3� ����.3t��t2'�-V�8���p�X�S�r�Y#J!���Q�� �"�,ub�@F���K�:�u�^��iy��[]<.Cw�� ���+W\�)��b���
k�r-���.M�t�ڀ�M��q�ʄ������۰����#$�^X$��"�������V`�T�4�m��~�w%P�p1��|�+&Ux�Y��8��*�r�8:��� ��k7QЃҀT���������$��Ў��ƙ�
�S>~�S�����j�s�:5�q.w�&_Z.�X=����:ވbw�`��� _�kd�{'��0�:�d��s��#�q���i!224����nq�\�9�-��KUT�sSU��uVo�@;�U��z�>^��=��N��������p��>o��P��O���
��@I���@���'G��j5�o�*U�>��^�*�e�w��>ͫʧ��᫠Q���5̈́���<$�#�5�J�ٻ�j��6e�)��_����d]��2���B:���^�(*�:8J���Y�S鬆����Kݗ��]U4_�rj�{���5�ׇ�aǑ/�y�V��?��G�t��G�����b@xPU��7O3�|�鍪	�I��Q5��Q��Gw�	*(;�w�f�0*�P�UU�<Y�Ɣ���v����b��t��5{2!�,}����Ҧ���:)��j2Ok�Ϊ�'֊0I.q\(�%ojQ���ĖՇ�a<��ԍ��ex�Agt��'�[d;׸�����`r�cd�����j��P�FU�$�UeJ�I6�T���&Z}���z���(�z�vfu�z� ��{}ۿߝ��ݞlx��U�Z�謊�.�Y岟b���%�����nw��@��ǩ��S9��|źs%�>�_�o#���9�\�EU~�/�ځ�t(r�[�Q��Zu��Oo;�����!MrU�]��0T��cpDő�? .���c��Pu����F������;����L_�������S��b}�R/�J_��+��h�2$�a��i��U�ǩ��S9>��Є}7�6r���zu�����~国4��oĨ
1J����
��^�̘��~��i C޸�5��5<P�ھ�r�/�G��Y�k૵��5�mK
��2姪�Ϊ5,���?�1'�jÓQ���pT뾺���
*��~�I?H�ם�):����\������J��:3�ѴUGo)X��.�Ë���*j�\��?}�㉎�G~A{Y#�W/3��鬶�!ʼ��=��C�g�u	*���u_��ޮ+�Qe�5�w�:���U���K��?U�W�1j\��S5/<�z7P^����<,S��j�UU8�����v,�2�_��_��i�뻊��^����R5^v��Nl>G׹]�g���w��s�nzTuO=�?/����zƲc>��Οb�#7ֻcg��k��ޛT�U�j���*-T=]����uu}��>ݨ�NЭ���[]�:%/_���S�z]6D.�m������D7Uƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘1c����>�J4h�PP��+��A�;'�G_�XK��mL���5I.},wFFu��m$S�-�E�-;Õ
C3I-`�B��R��x1��ғT�Jݕ;hΊ�8�D�Y��J��o;������Y5�M����K��ɰM��;���%P��� d9K�h���n�D[z��gVh�,��'C�
p!^M�(�WK2�X�>UQ��%���^��p8	˽�^#�Ζ؄+.@���g�C�z%ɔ-Pr
���K�X��
����n���>���=�Ք�Ѩ��eSvR����L�z���5%9UQS ��\�W�ի��K��'�h�p)ô
J��r�h����M0�F��(f_�R5�/�//�G��+������x	1"����eS��5��
��:T��f��=+�7�Qɧ�\�����TE����s�༬�r���Y�s8��&�k��������#pSՊ5�M�T�b��D܊[Ng�5Q�\s�� 5PB@[� 8ɨ�V1������&��4Wsy[�Ǿ
�w�U���2�V�����7��7��j������މd^~Yf��C��_��h;a.���&�M�
i� ��U�����Wpzs`>�/�"��'O�I����۲�y�����:�Bzd������T���q£�=й��b:���"����m�/��-/P��W�DQ�Ǵ͐��5���7������m�`�H��%A���V��!�H�ԛ׿���@"Q�� z��ދ|�ߒT���-�*OU�^��Ҧ6�����!��Cw�k�|h�&Hd5�LEY�y��'�ƣ7��%�*�<C'@�l ���b!w L�WW(%���C��4��������3\��������x������*������QF�Ҩ�<��m�������߃g?߉������^�)D�}�{�U��֘|�Q����=C'@�| �uw L�ׂQ�E�=�?�x+�x�"����g���S��O�Ҩj��׈.��fqj[��Y��Gͤ�C���焓m>{��=)���Z�%ٝ��P	���*G���]���� /� �8L�� w��$?8��M�)\į���/#�7U�fd7'6�\h1�
vI�f�EIr���=��1�w��\�WK��VZ�HK��g�Z��͡�$m��x���� %���`j}�TuT���QJZ��*H>*Q�xkLFT����y��U���-�)�ôb��iA���|q`��F�'���+	���4^Q�y�x��H)��#�t^��?@]^`A�R�S��q�jg�B:�r<h̆�Rn���z���PΦ�)��[+�n��M�X�H!����0��I����r����sKϡէU�R2��T	X�g ƴڳE�cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘1c�n��j����ǴyƌI��xQ�q�7fM���4E�F��.���34<.�i��;��eВi���1c%9����K	���͠2��J�C��n��w���E¤�c�F������`��5v6�%˿]�3��T��y��`�~���a���[[�J�>K�۷l<2�-4�Y��K�hgQ���L��x�V�w�P��~��M�Φ�����0l 3�ƅ�aŊIT�ȀhwJ�m��������xIM�չ��|��U7xˆS��~2�ߕ?�kW1k���C3]��;Y��nS���ґA�e�X�Yz�8,'�x�<k7Kx�����]��$��x�$�v�g�T#w��;o�����@�z�_V���m�n|�Hֵ��h��Zg-^TAn��-�)����@4�[*�9xK��Ƌ�����j>�!,�Vt�:e�����qn8%oh���S�(2�\Q��^�aig�����F��3��v�TUDV�l�Q�ꅧ�W�c��%�U��e�q�4�ҝº/��U��$�_�Q!��>�����t�|� �,țG<t�C���[�xTXmf|��<��Oڡ�MT�|(w:���_X����j7w���t�� �
AX�ͦ�p�$�^xZ�R��������j�x����`�3=�^��ll�+˗e�Q��8g8V��+�9M���/����� �o�14sn�b���tX�܍�s�����vE�l+@\��e�,�,�cѮ�<�(��i�HVY�r��Q�O7�a��I��>Q%d�#jUՆ�|;H��[b����ά�#������,W�s7NT1~���m&ǻ�{' \��㟾��b�BKJ�o8�%�!���$��Q����j:��/�RX)$Sy�޳䍧�R��DUg_D��軦�J�\�����j�N��֖SU;~�?��O��h�ss�d��ƣ}�6�(T<��_�4���b5���� �^N���N�%8QejF�7to��My�ө�`)g�[��/�������|����?��өJ���u�G����L�坕��/=�CTܠhd�ifH���cǞ�����G4��,��������`�D՞�{'x���G_p/5��@m +�$jV�H���3�a"��*ũ,�,��H�Jҵ�ȸ�T^Qy��o&IÉ�JUVwW���L�eM��~���3t�������A��6����r��wɤ�6���տ�� ��\0H�L%L�X5�c����@�HHÃZ��|NV��+7WM��{����cig���*���ȸU���7iÉ��бz���d� *�?�gt��X���8��̝O��X��:��]2�ɍ]�p^��++��>���A���VڛE�{�����DB.�&�/������56���A�rxY#ܕ�y�)��cKQtȪ��~� ���������! �;�C}ʃ��tf{�6��$N��Vsj��wup�Z)zŁ�|�-�w�g+n�MVj�/d+U������~ͯ�����i���:_ix��w��hq��r>�駃-�x�뼬)��ݷ�y��R=! ���ì:��J/l��Ik���V@�n��7�475��8�Z��K�J�(��Ux�z�1w�)^�\�ԣ��zȪ󲦨c���2f�؍�v�+�6f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘�2��N��o�C��\�����F1�ִ��UZ�JV̚\�4����M����gq1z{&��Y��T
�,�HX~D�u�\��g��}x�>�+Y���dN�̮��o�l�Z�X+F��[�/j��+S~2/jV��8�J�r^�����ԉ]J}J��*ۏ<��2԰&�Jݣ�jO��M@�ѯ#0��O�[��S�������X�B^uz�e��\�����]���d���d.���/���xXE
�f'v��O�_����H�${�%;�k�t�7ށ�m��ő|���d{a�ފ�^���Ǜ�ڎE��5ʋ��Br]W���=�_����SA���f(�0  ��oU�5�q,�_\�l�uz�˪uz���㻲���o�=Yi���~|��
0+�=V������ �J�ت��/��ލ��zM��\�zC�L����[U�:|k*^8"��\Wٚ\.��XTjX�5�Sk�F�u\�1� ���q'��m�ģ/�Q���Uؕ�*�AɽDNZ׮?_�[#�ˍ4�:�^j|�5�L�G���|| ���ø�BW{6[uQF����.1��$qF��9���IHg)\������5��>C�#��u�X�Z��$�#*<�ߐ�sR�v�1Tj>J��m>*����#��(��
��[F�h�sש�5��*jQʼ�&���&��&P��犛L��[�Q��1*���� ��;����X}�I�ΰ�[Q�?�q�Q�ZH�����ݙ���֞V��EsB��C�Z9��JTK������tu���p��˷��/�O���,.k�Ud�s�OHMg4=-)�+ؿ���h2��N��w�/r|W�Qn=�GIU�;��'���j,��v��f�ǳ���p�e����$���VGTY�sBZ�O�1p�j:����r���"n�TUSCg��r�ve���A�ۘ��F�C+Ֆ#�[J���Te�'v9-�3	D�m�ӻ�u�uz������?��0�� �o���	����h�x�u�Y��&�����_�54�=f���07��kלU��0��]D:�����j�dw�/+��P��GUV��S��<��\2�u��at�c�^zY�R�ąmC�+��7����#��,|��:��i�N��w��*|^s��m�|�X>Ъ�^��1�\�#���͹�	&���%�{,2��U��>�ݎ.c0�5�z�#�
o�g��N��O+��Q�쓭�� ������,��˗�-%K\����[S_`�y��+��b���_9��4����"�U��+��Ύap�}�I����[�M,B��.�Nt���w�H��j�漬���E������L��߀0DX(�k�ڵ�����NoU��{�gquz
R�wkէRx'�uZ�[����3'��z�yy��ד%�<U��hN[����tz�x1� c�c���]Fݯ�B�"]a[J����Dս[cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3V�es{L+3VH]YP�A	�>�s�����ƕ���3jYF\��s���=m1�&���V���Aɼ?k\+]�6y�ﾓ��1���gt�OIW7�a�l|1��� ����>$]e�7��؝�W�I�e?ަ�L#���>|����
�ҭ��]��
p�M5M�U�dI���61��Ԡ�eǼY�G����h�O�n��3�խR:^�k_'Yuuq#���p�#
�J�����2�x����l>��Oj������cY��馃��!�ڡ+�sZ/�����D�}��2��A�Ym��� �p�c#�<'x��SKx��`�*W[,e|��6�B�H)㶤kj���p��D�U(2qzx��9����*tqa�/,�
Z[��	0�>��Ө�֜����xN)f�ă��@qը����FU՝��w(��a;ˋ�>�|T�c|�w2����eiT]*�!_\�WG{
���]��^���݅��Z5����t|��6�oYH�����a������O@�=�����my^ak����E�.����u��z�]#٥��hWv�(��:�,��6��A��߉J��Fa���\�w��W���ex>v�<��?|����&i_�q�z����]e�R_�7�|& c*�kր4f���,J �U���_�h��\1A�������������u\��-�L\Ϝ^��~�P�hr��*tqa0��fT��:�MU��;q�>�et�u�M��Y��A>�����).,��;ɦ�C�bw�jE)��W������Fӫ@�s4��e�6^�Q9oI}4�x<���.�B?��B��߫�#��$��Hx�.x9,��a!�RT�pgd5������xB��e�����.L7@�*�
Asdutt�S��VUa��RU|��I	xG�߃$T�����񭟬���#_��IF�M�_X�@f�o���Q�ID��I��I?|�%����$�r�	���{�����E��Nĸ�wޕ�qq�?����D�ؽ}�}o�/`ӣ�CT�i	���<Q�R{\yY�����F���QJkh������^?Us:�E��|]���V� )Z|H�jsW����|�H'|��o����=d|�߼j �#��T���%�O��	W��!�N#�w�1[i�H(��SV����s�����[=�Ɉ����7���1�ȳ���T]A G�換��3����CT׻�lR�ݕCV9Q�\V#ܛ��N�ӏj�ˇ1�/�s�l �R���%^s1���nU�j����,�x}��f��W�|JuK�w���p�����S��m,�<��7<����
��Ȼ�����[�R<&���p��?���'��,�Й��\�;����5�bH$�3�#�Q�4\���_���>�/�yw�O��rD9���YUD]���	Ή����@s���]���+'UaL}��h��r�U����'7�:��sU|k)H��@����h�N�q�#�ϵ�8��y�˭�X���ű#��w��
�1!�흉�R'7��f�u�ד��0�����p�!W��ÖW+Nm�p�\����-�ioD$� ���g�٠˅%�%�Ð�m��V�]�̱��r�w*��Z�}��y�+L�
N��o�u�j�}�xt������)lS��tuq���x����m�NyK�U��OnDb�hf}�k�>�6��u�fT�%�����{��� <񐮸���mj��F�c�mU�ï����c��;�w��8��@dG�FUA��&��� �����=n�q�5]iP���}�z�:�k⼶��-��ʓ�	Κl*'U��z�ax�W���F�dZ��zT��NR�s+��#��� w��zgi:��MB��q���t��M��l#��^�'G�ߣ�*^�t�{����=�rE���R��n�Q�$adJl�02%��Tڊ^����<�~g�?�O�f*U�^��?��:��N�����+�o�[�P�U�s�|�Q��R']�V�-L)H�K�䐞mY��n�\��4}Y��V�D��h���R��;g��-��'�3aס�M�D�h�}�1cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ3f̘1cƌ��k�*Ț4�`L�$�b�	���U���4\dt���'���>�HȄ|�.��+Y+/�G��y���2�OCWv���3v,�'kia��������W����O6߯E�=Hv
$L�l�xI��躍/��}�^]�����x��\3���ɮ5�� ���Q�T&G�9A�y^������i�}O��[5ޱ�wq�4,sJJ��I.myE�^�%���'V�B~�d�ׯ��}�*�j��*�	~��u��T�k���\f�KЬ�*��Y]����_v'I��˨����鑩6�X��o��'�j&u��ɧn�g��T]�o��ڌ��9����\*�wVHӖ�|�	�>��:�5EF�'J����ɝ`���!��A������ �
���e~��_;���5�ױϊ����镋m_&�O����Vi����<}"�靍��hW9�X�6��KPƣG�"�ƭ�?��/����O�^���hC�H���L��c���i��P��j���)}���Q�Qզ��#tM��g��9���xGw�����~d;_�J+�RỲ�<���;�e�����5/Qs�/5��N[��!�a+�N�P�b+�Ѻ��I�}����-��t_�q�U=�MK�ʞ�Y���5no��*����v��v�b�ʊ{]��|�~	Z��{-�������끇^����FVviϵ3��Ya�������=6n���dS;�-�ʹ^;�uꪪ^�|�=��_�w+��"�����i�&4��l�#�w��i��r|W��3U�$��"J�~���O@]~t��RJV��MH�w:̦����@?��>�O���?�vdr��tS�*$�&~1>��������Z}^�n�L(��]�f*�&�*�Q��a�I����Ꝅ|��3�*����O���?�������r�?�*�4�Gyz[�k/t�k��Q��ϖ���WC�C�K�k/��x��5�|��S�*`��Ϲγ�Q����E�w���y�
o��K�YqT�b����$����-/Pt�sZN�K��Q��*>��ݢ���U�@�Џ"JQ;���¹&�
�Lx�;+T�/+���O�赟��>�(T���?ķD^N*�'�p�����$I���W֐��W~�=��J|��_��UTe��7ְP`�;CYjk�=�s�U[��mߙ-���;�};�2|���w��o�1�p�0��~>��0���m��
@J�rǟ�cٷ4�͜��?q��\�UU�IV?2��L��/�+Шꄾ<�܇^T����?t�j\�Jr���Ҁ���B*�����=k�m����X�,n}a����Ւ�Ia��d�p׷��l�l{\��6v8��R��ꅟ����Ҳ��f�1��F|Տ�;�e�=\D��,D�:ψ��r�xQ�T◎�*|{n��S
9~�=�}ӕ��G~%j�:D��j�<�ឫ:��jO%���
�$T8!j����vm��|'O��З��¹➱z\vsIv`�Ȕ�ʨj��-�^�$-��^���G�Q��{�m���`��T��#�c�֞�㸝�|n�.ߪN�$��O�������JUV���ʼ�t,�����j�g�-����mסּ�NV�����z��:����(�Ι*|1U�x�=�Y��k*����t��M����N��N�DU�hK��� ؞X(刄Rv�!�#B_��c�xR����Ź���o��E5Dg>�?�f���XQ��Q�˔|@�"�աM�����veC�>��m�O$H��#]Y���I=���)_���`���k���*
�:a�>!X���!��W�^���wҒ��l'�<;�vwgI��t�_�?Jh��`��#E:fdx=��6Wu<������ �Ӌ�d2�di���˂�c#h¬c4��� �?<���H���FYo��Vp�N�;�ݷJ\�� �����>�`(���t�3{�>⦊��;;q��F���x�4�Yc����S�$w�.�����d��a*k���|��Q�,��+x��s^��K߫���P^���n�O֮L5m�I�wl?-.ʲ����J8�F�����B.-:2��Ȕ�!����/A�#b��_m%�I�(���$|�PZ[����1�G�{^�#�����o>�3��m�w?'�cx���[�^�:W�k/�`'=���~֥���W�(�gQ���bf�v7U�z��M�3����+؍�K�:��4|G�Ct��A�+K��ʨ�{@���Ɩ�[0�5�����E�|yn4M    IEND�B`�/*!
* Bootstrap.js by @fat & @mdo
* Copyright 2012 Twitter, Inc.
* http://www.apache.org/licenses/LICENSE-2.0.txt
*/
!function(e){"use strict";e(function(){e.support.transition=function(){var e=function(){var e=document.createElement("bootstrap"),t={WebkitTransition:"webkitTransitionEnd",MozTransition:"transitionend",OTransition:"oTransitionEnd otransitionend",transition:"transitionend"},n;for(n in t)if(e.style[n]!==undefined)return t[n]}();return e&&{end:e}}()})}(window.jQuery),!function(e){"use strict";var t='[data-dismiss="alert"]',n=function(n){e(n).on("click",t,this.close)};n.prototype.close=function(t){function s(){i.trigger("closed").remove()}var n=e(this),r=n.attr("data-target"),i;r||(r=n.attr("href"),r=r&&r.replace(/.*(?=#[^\s]*$)/,"")),i=e(r),t&&t.preventDefault(),i.length||(i=n.hasClass("alert")?n:n.parent()),i.trigger(t=e.Event("close"));if(t.isDefaultPrevented())return;i.removeClass("in"),e.support.transition&&i.hasClass("fade")?i.on(e.support.transition.end,s):s()},e.fn.alert=function(t){return this.each(function(){var r=e(this),i=r.data("alert");i||r.data("alert",i=new n(this)),typeof t=="string"&&i[t].call(r)})},e.fn.alert.Constructor=n,e(document).on("click.alert.data-api",t,n.prototype.close)}(window.jQuery),!function(e){"use strict";var t=function(t,n){this.$element=e(t),this.options=e.extend({},e.fn.button.defaults,n)};t.prototype.setState=function(e){var t="disabled",n=this.$element,r=n.data(),i=n.is("input")?"val":"html";e+="Text",r.resetText||n.data("resetText",n[i]()),n[i](r[e]||this.options[e]),setTimeout(function(){e=="loadingText"?n.addClass(t).attr(t,t):n.removeClass(t).removeAttr(t)},0)},t.prototype.toggle=function(){var e=this.$element.closest('[data-toggle="buttons-radio"]');e&&e.find(".active").removeClass("active"),this.$element.toggleClass("active")},e.fn.button=function(n){return this.each(function(){var r=e(this),i=r.data("button"),s=typeof n=="object"&&n;i||r.data("button",i=new t(this,s)),n=="toggle"?i.toggle():n&&i.setState(n)})},e.fn.button.defaults={loadingText:"loading..."},e.fn.button.Constructor=t,e(document).on("click.button.data-api","[data-toggle^=button]",function(t){var n=e(t.target);n.hasClass("btn")||(n=n.closest(".btn")),n.button("toggle")})}(window.jQuery),!function(e){"use strict";var t=function(t,n){this.$element=e(t),this.options=n,this.options.slide&&this.slide(this.options.slide),this.options.pause=="hover"&&this.$element.on("mouseenter",e.proxy(this.pause,this)).on("mouseleave",e.proxy(this.cycle,this))};t.prototype={cycle:function(t){return t||(this.paused=!1),this.options.interval&&!this.paused&&(this.interval=setInterval(e.proxy(this.next,this),this.options.interval)),this},to:function(t){var n=this.$element.find(".item.active"),r=n.parent().children(),i=r.index(n),s=this;if(t>r.length-1||t<0)return;return this.sliding?this.$element.one("slid",function(){s.to(t)}):i==t?this.pause().cycle():this.slide(t>i?"next":"prev",e(r[t]))},pause:function(t){return t||(this.paused=!0),this.$element.find(".next, .prev").length&&e.support.transition.end&&(this.$element.trigger(e.support.transition.end),this.cycle()),clearInterval(this.interval),this.interval=null,this},next:function(){if(this.sliding)return;return this.slide("next")},prev:function(){if(this.sliding)return;return this.slide("prev")},slide:function(t,n){var r=this.$element.find(".item.active"),i=n||r[t](),s=this.interval,o=t=="next"?"left":"right",u=t=="next"?"first":"last",a=this,f;this.sliding=!0,s&&this.pause(),i=i.length?i:this.$element.find(".item")[u](),f=e.Event("slide",{relatedTarget:i[0]});if(i.hasClass("active"))return;if(e.support.transition&&this.$element.hasClass("slide")){this.$element.trigger(f);if(f.isDefaultPrevented())return;i.addClass(t),i[0].offsetWidth,r.addClass(o),i.addClass(o),this.$element.one(e.support.transition.end,function(){i.removeClass([t,o].join(" ")).addClass("active"),r.removeClass(["active",o].join(" ")),a.sliding=!1,setTimeout(function(){a.$element.trigger("slid")},0)})}else{this.$element.trigger(f);if(f.isDefaultPrevented())return;r.removeClass("active"),i.addClass("active"),this.sliding=!1,this.$element.trigger("slid")}return s&&this.cycle(),this}},e.fn.carousel=function(n){return this.each(function(){var r=e(this),i=r.data("carousel"),s=e.extend({},e.fn.carousel.defaults,typeof n=="object"&&n),o=typeof n=="string"?n:s.slide;i||r.data("carousel",i=new t(this,s)),typeof n=="number"?i.to(n):o?i[o]():s.interval&&i.cycle()})},e.fn.carousel.defaults={interval:5e3,pause:"hover"},e.fn.carousel.Constructor=t,e(document).on("click.carousel.data-api","[data-slide]",function(t){var n=e(this),r,i=e(n.attr("data-target")||(r=n.attr("href"))&&r.replace(/.*(?=#[^\s]+$)/,"")),s=!i.data("carousel")&&e.extend({},i.data(),n.data());i.carousel(s),t.preventDefault()})}(window.jQuery),!function(e){"use strict";var t=function(t,n){this.$element=e(t),this.options=e.extend({},e.fn.collapse.defaults,n),this.options.parent&&(this.$parent=e(this.options.parent)),this.options.toggle&&this.toggle()};t.prototype={constructor:t,dimension:function(){var e=this.$element.hasClass("width");return e?"width":"height"},show:function(){var t,n,r,i;if(this.transitioning)return;t=this.dimension(),n=e.camelCase(["scroll",t].join("-")),r=this.$parent&&this.$parent.find("> .accordion-group > .in");if(r&&r.length){i=r.data("collapse");if(i&&i.transitioning)return;r.collapse("hide"),i||r.data("collapse",null)}this.$element[t](0),this.transition("addClass",e.Event("show"),"shown"),e.support.transition&&this.$element[t](this.$element[0][n])},hide:function(){var t;if(this.transitioning)return;t=this.dimension(),this.reset(this.$element[t]()),this.transition("removeClass",e.Event("hide"),"hidden"),this.$element[t](0)},reset:function(e){var t=this.dimension();return this.$element.removeClass("collapse")[t](e||"auto")[0].offsetWidth,this.$element[e!==null?"addClass":"removeClass"]("collapse"),this},transition:function(t,n,r){var i=this,s=function(){n.type=="show"&&i.reset(),i.transitioning=0,i.$element.trigger(r)};this.$element.trigger(n);if(n.isDefaultPrevented())return;this.transitioning=1,this.$element[t]("in"),e.support.transition&&this.$element.hasClass("collapse")?this.$element.one(e.support.transition.end,s):s()},toggle:function(){this[this.$element.hasClass("in")?"hide":"show"]()}},e.fn.collapse=function(n){return this.each(function(){var r=e(this),i=r.data("collapse"),s=typeof n=="object"&&n;i||r.data("collapse",i=new t(this,s)),typeof n=="string"&&i[n]()})},e.fn.collapse.defaults={toggle:!0},e.fn.collapse.Constructor=t,e(document).on("click.collapse.data-api","[data-toggle=collapse]",function(t){var n=e(this),r,i=n.attr("data-target")||t.preventDefault()||(r=n.attr("href"))&&r.replace(/.*(?=#[^\s]+$)/,""),s=e(i).data("collapse")?"toggle":n.data();n[e(i).hasClass("in")?"addClass":"removeClass"]("collapsed"),e(i).collapse(s)})}(window.jQuery),!function(e){"use strict";function r(){e(t).each(function(){i(e(this)).removeClass("open")})}function i(t){var n=t.attr("data-target"),r;return n||(n=t.attr("href"),n=n&&/#/.test(n)&&n.replace(/.*(?=#[^\s]*$)/,"")),r=e(n),r.length||(r=t.parent()),r}var t="[data-toggle=dropdown]",n=function(t){var n=e(t).on("click.dropdown.data-api",this.toggle);e("html").on("click.dropdown.data-api",function(){n.parent().removeClass("open")})};n.prototype={constructor:n,toggle:function(t){var n=e(this),s,o;if(n.is(".disabled, :disabled"))return;return s=i(n),o=s.hasClass("open"),r(),o||(s.toggleClass("open"),n.focus()),!1},keydown:function(t){var n,r,s,o,u,a;if(!/(38|40|27)/.test(t.keyCode))return;n=e(this),t.preventDefault(),t.stopPropagation();if(n.is(".disabled, :disabled"))return;o=i(n),u=o.hasClass("open");if(!u||u&&t.keyCode==27)return n.click();r=e("[role=menu] li:not(.divider) a",o);if(!r.length)return;a=r.index(r.filter(":focus")),t.keyCode==38&&a>0&&a--,t.keyCode==40&&a<r.length-1&&a++,~a||(a=0),r.eq(a).focus()}},e.fn.dropdown=function(t){return this.each(function(){var r=e(this),i=r.data("dropdown");i||r.data("dropdown",i=new n(this)),typeof t=="string"&&i[t].call(r)})},e.fn.dropdown.Constructor=n,e(document).on("click.dropdown.data-api touchstart.dropdown.data-api",r).on("click.dropdown touchstart.dropdown.data-api",".dropdown form",function(e){e.stopPropagation()}).on("click.dropdown.data-api touchstart.dropdown.data-api",t,n.prototype.toggle).on("keydown.dropdown.data-api touchstart.dropdown.data-api",t+", [role=menu]",n.prototype.keydown)}(window.jQuery),!function(e){"use strict";var t=function(t,n){this.options=n,this.$element=e(t).delegate('[data-dismiss="modal"]',"click.dismiss.modal",e.proxy(this.hide,this)),this.options.remote&&this.$element.find(".modal-body").load(this.options.remote)};t.prototype={constructor:t,toggle:function(){return this[this.isShown?"hide":"show"]()},show:function(){var t=this,n=e.Event("show");this.$element.trigger(n);if(this.isShown||n.isDefaultPrevented())return;this.isShown=!0,this.escape(),this.backdrop(function(){var n=e.support.transition&&t.$element.hasClass("fade");t.$element.parent().length||t.$element.appendTo(document.body),t.$element.show(),n&&t.$element[0].offsetWidth,t.$element.addClass("in").attr("aria-hidden",!1),t.enforceFocus(),n?t.$element.one(e.support.transition.end,function(){t.$element.focus().trigger("shown")}):t.$element.focus().trigger("shown")})},hide:function(t){t&&t.preventDefault();var n=this;t=e.Event("hide"),this.$element.trigger(t);if(!this.isShown||t.isDefaultPrevented())return;this.isShown=!1,this.escape(),e(document).off("focusin.modal"),this.$element.removeClass("in").attr("aria-hidden",!0),e.support.transition&&this.$element.hasClass("fade")?this.hideWithTransition():this.hideModal()},enforceFocus:function(){var t=this;e(document).on("focusin.modal",function(e){t.$element[0]!==e.target&&!t.$element.has(e.target).length&&t.$element.focus()})},escape:function(){var e=this;this.isShown&&this.options.keyboard?this.$element.on("keyup.dismiss.modal",function(t){t.which==27&&e.hide()}):this.isShown||this.$element.off("keyup.dismiss.modal")},hideWithTransition:function(){var t=this,n=setTimeout(function(){t.$element.off(e.support.transition.end),t.hideModal()},500);this.$element.one(e.support.transition.end,function(){clearTimeout(n),t.hideModal()})},hideModal:function(e){this.$element.hide().trigger("hidden"),this.backdrop()},removeBackdrop:function(){this.$backdrop.remove(),this.$backdrop=null},backdrop:function(t){var n=this,r=this.$element.hasClass("fade")?"fade":"";if(this.isShown&&this.options.backdrop){var i=e.support.transition&&r;this.$backdrop=e('<div class="modal-backdrop '+r+'" />').appendTo(document.body),this.$backdrop.click(this.options.backdrop=="static"?e.proxy(this.$element[0].focus,this.$element[0]):e.proxy(this.hide,this)),i&&this.$backdrop[0].offsetWidth,this.$backdrop.addClass("in"),i?this.$backdrop.one(e.support.transition.end,t):t()}else!this.isShown&&this.$backdrop?(this.$backdrop.removeClass("in"),e.support.transition&&this.$element.hasClass("fade")?this.$backdrop.one(e.support.transition.end,e.proxy(this.removeBackdrop,this)):this.removeBackdrop()):t&&t()}},e.fn.modal=function(n){return this.each(function(){var r=e(this),i=r.data("modal"),s=e.extend({},e.fn.modal.defaults,r.data(),typeof n=="object"&&n);i||r.data("modal",i=new t(this,s)),typeof n=="string"?i[n]():s.show&&i.show()})},e.fn.modal.defaults={backdrop:!0,keyboard:!0,show:!0},e.fn.modal.Constructor=t,e(document).on("click.modal.data-api",'[data-toggle="modal"]',function(t){var n=e(this),r=n.attr("href"),i=e(n.attr("data-target")||r&&r.replace(/.*(?=#[^\s]+$)/,"")),s=i.data("modal")?"toggle":e.extend({remote:!/#/.test(r)&&r},i.data(),n.data());t.preventDefault(),i.modal(s).one("hide",function(){n.focus()})})}(window.jQuery),!function(e){"use strict";var t=function(e,t){this.init("tooltip",e,t)};t.prototype={constructor:t,init:function(t,n,r){var i,s;this.type=t,this.$element=e(n),this.options=this.getOptions(r),this.enabled=!0,this.options.trigger=="click"?this.$element.on("click."+this.type,this.options.selector,e.proxy(this.toggle,this)):this.options.trigger!="manual"&&(i=this.options.trigger=="hover"?"mouseenter":"focus",s=this.options.trigger=="hover"?"mouseleave":"blur",this.$element.on(i+"."+this.type,this.options.selector,e.proxy(this.enter,this)),this.$element.on(s+"."+this.type,this.options.selector,e.proxy(this.leave,this))),this.options.selector?this._options=e.extend({},this.options,{trigger:"manual",selector:""}):this.fixTitle()},getOptions:function(t){return t=e.extend({},e.fn[this.type].defaults,t,this.$element.data()),t.delay&&typeof t.delay=="number"&&(t.delay={show:t.delay,hide:t.delay}),t},enter:function(t){var n=e(t.currentTarget)[this.type](this._options).data(this.type);if(!n.options.delay||!n.options.delay.show)return n.show();clearTimeout(this.timeout),n.hoverState="in",this.timeout=setTimeout(function(){n.hoverState=="in"&&n.show()},n.options.delay.show)},leave:function(t){var n=e(t.currentTarget)[this.type](this._options).data(this.type);this.timeout&&clearTimeout(this.timeout);if(!n.options.delay||!n.options.delay.hide)return n.hide();n.hoverState="out",this.timeout=setTimeout(function(){n.hoverState=="out"&&n.hide()},n.options.delay.hide)},show:function(){var e,t,n,r,i,s,o;if(this.hasContent()&&this.enabled){e=this.tip(),this.setContent(),this.options.animation&&e.addClass("fade"),s=typeof this.options.placement=="function"?this.options.placement.call(this,e[0],this.$element[0]):this.options.placement,t=/in/.test(s),e.detach().css({top:0,left:0,display:"block"}).insertAfter(this.$element),n=this.getPosition(t),r=e[0].offsetWidth,i=e[0].offsetHeight;switch(t?s.split(" ")[1]:s){case"bottom":o={top:n.top+n.height,left:n.left+n.width/2-r/2};break;case"top":o={top:n.top-i,left:n.left+n.width/2-r/2};break;case"left":o={top:n.top+n.height/2-i/2,left:n.left-r};break;case"right":o={top:n.top+n.height/2-i/2,left:n.left+n.width}}e.offset(o).addClass(s).addClass("in")}},setContent:function(){var e=this.tip(),t=this.getTitle();e.find(".tooltip-inner")[this.options.html?"html":"text"](t),e.removeClass("fade in top bottom left right")},hide:function(){function r(){var t=setTimeout(function(){n.off(e.support.transition.end).detach()},500);n.one(e.support.transition.end,function(){clearTimeout(t),n.detach()})}var t=this,n=this.tip();return n.removeClass("in"),e.support.transition&&this.$tip.hasClass("fade")?r():n.detach(),this},fixTitle:function(){var e=this.$element;(e.attr("title")||typeof e.attr("data-original-title")!="string")&&e.attr("data-original-title",e.attr("title")||"").removeAttr("title")},hasContent:function(){return this.getTitle()},getPosition:function(t){return e.extend({},t?{top:0,left:0}:this.$element.offset(),{width:this.$element[0].offsetWidth,height:this.$element[0].offsetHeight})},getTitle:function(){var e,t=this.$element,n=this.options;return e=t.attr("data-original-title")||(typeof n.title=="function"?n.title.call(t[0]):n.title),e},tip:function(){return this.$tip=this.$tip||e(this.options.template)},validate:function(){this.$element[0].parentNode||(this.hide(),this.$element=null,this.options=null)},enable:function(){this.enabled=!0},disable:function(){this.enabled=!1},toggleEnabled:function(){this.enabled=!this.enabled},toggle:function(t){var n=e(t.currentTarget)[this.type](this._options).data(this.type);n[n.tip().hasClass("in")?"hide":"show"]()},destroy:function(){this.hide().$element.off("."+this.type).removeData(this.type)}},e.fn.tooltip=function(n){return this.each(function(){var r=e(this),i=r.data("tooltip"),s=typeof n=="object"&&n;i||r.data("tooltip",i=new t(this,s)),typeof n=="string"&&i[n]()})},e.fn.tooltip.Constructor=t,e.fn.tooltip.defaults={animation:!0,placement:"top",selector:!1,template:'<div class="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>',trigger:"hover",title:"",delay:0,html:!1}}(window.jQuery),!function(e){"use strict";var t=function(e,t){this.init("popover",e,t)};t.prototype=e.extend({},e.fn.tooltip.Constructor.prototype,{constructor:t,setContent:function(){var e=this.tip(),t=this.getTitle(),n=this.getContent();e.find(".popover-title")[this.options.html?"html":"text"](t),e.find(".popover-content > *")[this.options.html?"html":"text"](n),e.removeClass("fade top bottom left right in")},hasContent:function(){return this.getTitle()||this.getContent()},getContent:function(){var e,t=this.$element,n=this.options;return e=t.attr("data-content")||(typeof n.content=="function"?n.content.call(t[0]):n.content),e},tip:function(){return this.$tip||(this.$tip=e(this.options.template)),this.$tip},destroy:function(){this.hide().$element.off("."+this.type).removeData(this.type)}}),e.fn.popover=function(n){return this.each(function(){var r=e(this),i=r.data("popover"),s=typeof n=="object"&&n;i||r.data("popover",i=new t(this,s)),typeof n=="string"&&i[n]()})},e.fn.popover.Constructor=t,e.fn.popover.defaults=e.extend({},e.fn.tooltip.defaults,{placement:"right",trigger:"click",content:"",template:'<div class="popover"><div class="arrow"></div><div class="popover-inner"><h3 class="popover-title"></h3><div class="popover-content"><p></p></div></div></div>'})}(window.jQuery),!function(e){"use strict";function t(t,n){var r=e.proxy(this.process,this),i=e(t).is("body")?e(window):e(t),s;this.options=e.extend({},e.fn.scrollspy.defaults,n),this.$scrollElement=i.on("scroll.scroll-spy.data-api",r),this.selector=(this.options.target||(s=e(t).attr("href"))&&s.replace(/.*(?=#[^\s]+$)/,"")||"")+" .nav li > a",this.$body=e("body"),this.refresh(),this.process()}t.prototype={constructor:t,refresh:function(){var t=this,n;this.offsets=e([]),this.targets=e([]),n=this.$body.find(this.selector).map(function(){var t=e(this),n=t.data("target")||t.attr("href"),r=/^#\w/.test(n)&&e(n);return r&&r.length&&[[r.position().top,n]]||null}).sort(function(e,t){return e[0]-t[0]}).each(function(){t.offsets.push(this[0]),t.targets.push(this[1])})},process:function(){var e=this.$scrollElement.scrollTop()+this.options.offset,t=this.$scrollElement[0].scrollHeight||this.$body[0].scrollHeight,n=t-this.$scrollElement.height(),r=this.offsets,i=this.targets,s=this.activeTarget,o;if(e>=n)return s!=(o=i.last()[0])&&this.activate(o);for(o=r.length;o--;)s!=i[o]&&e>=r[o]&&(!r[o+1]||e<=r[o+1])&&this.activate(i[o])},activate:function(t){var n,r;this.activeTarget=t,e(this.selector).parent(".active").removeClass("active"),r=this.selector+'[data-target="'+t+'"],'+this.selector+'[href="'+t+'"]',n=e(r).parent("li").addClass("active"),n.parent(".dropdown-menu").length&&(n=n.closest("li.dropdown").addClass("active")),n.trigger("activate")}},e.fn.scrollspy=function(n){return this.each(function(){var r=e(this),i=r.data("scrollspy"),s=typeof n=="object"&&n;i||r.data("scrollspy",i=new t(this,s)),typeof n=="string"&&i[n]()})},e.fn.scrollspy.Constructor=t,e.fn.scrollspy.defaults={offset:10},e(window).on("load",function(){e('[data-spy="scroll"]').each(function(){var t=e(this);t.scrollspy(t.data())})})}(window.jQuery),!function(e){"use strict";var t=function(t){this.element=e(t)};t.prototype={constructor:t,show:function(){var t=this.element,n=t.closest("ul:not(.dropdown-menu)"),r=t.attr("data-target"),i,s,o;r||(r=t.attr("href"),r=r&&r.replace(/.*(?=#[^\s]*$)/,""));if(t.parent("li").hasClass("active"))return;i=n.find(".active:last a")[0],o=e.Event("show",{relatedTarget:i}),t.trigger(o);if(o.isDefaultPrevented())return;s=e(r),this.activate(t.parent("li"),n),this.activate(s,s.parent(),function(){t.trigger({type:"shown",relatedTarget:i})})},activate:function(t,n,r){function o(){i.removeClass("active").find("> .dropdown-menu > .active").removeClass("active"),t.addClass("active"),s?(t[0].offsetWidth,t.addClass("in")):t.removeClass("fade"),t.parent(".dropdown-menu")&&t.closest("li.dropdown").addClass("active"),r&&r()}var i=n.find("> .active"),s=r&&e.support.transition&&i.hasClass("fade");s?i.one(e.support.transition.end,o):o(),i.removeClass("in")}},e.fn.tab=function(n){return this.each(function(){var r=e(this),i=r.data("tab");i||r.data("tab",i=new t(this)),typeof n=="string"&&i[n]()})},e.fn.tab.Constructor=t,e(document).on("click.tab.data-api",'[data-toggle="tab"], [data-toggle="pill"]',function(t){t.preventDefault(),e(this).tab("show")})}(window.jQuery),!function(e){"use strict";var t=function(t,n){this.$element=e(t),this.options=e.extend({},e.fn.typeahead.defaults,n),this.matcher=this.options.matcher||this.matcher,this.sorter=this.options.sorter||this.sorter,this.highlighter=this.options.highlighter||this.highlighter,this.updater=this.options.updater||this.updater,this.$menu=e(this.options.menu).appendTo("body"),this.source=this.options.source,this.shown=!1,this.listen()};t.prototype={constructor:t,select:function(){var e=this.$menu.find(".active").attr("data-value");return this.$element.val(this.updater(e)).change(),this.hide()},updater:function(e){return e},show:function(){var t=e.extend({},this.$element.offset(),{height:this.$element[0].offsetHeight});return this.$menu.css({top:t.top+t.height,left:t.left}),this.$menu.show(),this.shown=!0,this},hide:function(){return this.$menu.hide(),this.shown=!1,this},lookup:function(t){var n;return this.query=this.$element.val(),!this.query||this.query.length<this.options.minLength?this.shown?this.hide():this:(n=e.isFunction(this.source)?this.source(this.query,e.proxy(this.process,this)):this.source,n?this.process(n):this)},process:function(t){var n=this;return t=e.grep(t,function(e){return n.matcher(e)}),t=this.sorter(t),t.length?this.render(t.slice(0,this.options.items)).show():this.shown?this.hide():this},matcher:function(e){return~e.toLowerCase().indexOf(this.query.toLowerCase())},sorter:function(e){var t=[],n=[],r=[],i;while(i=e.shift())i.toLowerCase().indexOf(this.query.toLowerCase())?~i.indexOf(this.query)?n.push(i):r.push(i):t.push(i);return t.concat(n,r)},highlighter:function(e){var t=this.query.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g,"\\$&");return e.replace(new RegExp("("+t+")","ig"),function(e,t){return"<strong>"+t+"</strong>"})},render:function(t){var n=this;return t=e(t).map(function(t,r){return t=e(n.options.item).attr("data-value",r),t.find("a").html(n.highlighter(r)),t[0]}),t.first().addClass("active"),this.$menu.html(t),this},next:function(t){var n=this.$menu.find(".active").removeClass("active"),r=n.next();r.length||(r=e(this.$menu.find("li")[0])),r.addClass("active")},prev:function(e){var t=this.$menu.find(".active").removeClass("active"),n=t.prev();n.length||(n=this.$menu.find("li").last()),n.addClass("active")},listen:function(){this.$element.on("blur",e.proxy(this.blur,this)).on("keypress",e.proxy(this.keypress,this)).on("keyup",e.proxy(this.keyup,this)),this.eventSupported("keydown")&&this.$element.on("keydown",e.proxy(this.keydown,this)),this.$menu.on("click",e.proxy(this.click,this)).on("mouseenter","li",e.proxy(this.mouseenter,this))},eventSupported:function(e){var t=e in this.$element;return t||(this.$element.setAttribute(e,"return;"),t=typeof this.$element[e]=="function"),t},move:function(e){if(!this.shown)return;switch(e.keyCode){case 9:case 13:case 27:e.preventDefault();break;case 38:e.preventDefault(),this.prev();break;case 40:e.preventDefault(),this.next()}e.stopPropagation()},keydown:function(t){this.suppressKeyPressRepeat=!~e.inArray(t.keyCode,[40,38,9,13,27]),this.move(t)},keypress:function(e){if(this.suppressKeyPressRepeat)return;this.move(e)},keyup:function(e){switch(e.keyCode){case 40:case 38:case 16:case 17:case 18:break;case 9:case 13:if(!this.shown)return;this.select();break;case 27:if(!this.shown)return;this.hide();break;default:this.lookup()}e.stopPropagation(),e.preventDefault()},blur:function(e){var t=this;setTimeout(function(){t.hide()},150)},click:function(e){e.stopPropagation(),e.preventDefault(),this.select()},mouseenter:function(t){this.$menu.find(".active").removeClass("active"),e(t.currentTarget).addClass("active")}},e.fn.typeahead=function(n){return this.each(function(){var r=e(this),i=r.data("typeahead"),s=typeof n=="object"&&n;i||r.data("typeahead",i=new t(this,s)),typeof n=="string"&&i[n]()})},e.fn.typeahead.defaults={source:[],items:8,menu:'<ul class="typeahead dropdown-menu"></ul>',item:'<li><a href="#"></a></li>',minLength:1},e.fn.typeahead.Constructor=t,e(document).on("focus.typeahead.data-api",'[data-provide="typeahead"]',function(t){var n=e(this);if(n.data("typeahead"))return;t.preventDefault(),n.typeahead(n.data())})}(window.jQuery),!function(e){"use strict";var t=function(t,n){this.options=e.extend({},e.fn.affix.defaults,n),this.$window=e(window).on("scroll.affix.data-api",e.proxy(this.checkPosition,this)).on("click.affix.data-api",e.proxy(function(){setTimeout(e.proxy(this.checkPosition,this),1)},this)),this.$element=e(t),this.checkPosition()};t.prototype.checkPosition=function(){if(!this.$element.is(":visible"))return;var t=e(document).height(),n=this.$window.scrollTop(),r=this.$element.offset(),i=this.options.offset,s=i.bottom,o=i.top,u="affix affix-top affix-bottom",a;typeof i!="object"&&(s=o=i),typeof o=="function"&&(o=i.top()),typeof s=="function"&&(s=i.bottom()),a=this.unpin!=null&&n+this.unpin<=r.top?!1:s!=null&&r.top+this.$element.height()>=t-s?"bottom":o!=null&&n<=o?"top":!1;if(this.affixed===a)return;this.affixed=a,this.unpin=a=="bottom"?r.top-n:null,this.$element.removeClass(u).addClass("affix"+(a?"-"+a:""))},e.fn.affix=function(n){return this.each(function(){var r=e(this),i=r.data("affix"),s=typeof n=="object"&&n;i||r.data("affix",i=new t(this,s)),typeof n=="string"&&i[n]()})},e.fn.affix.Constructor=t,e.fn.affix.defaults={offset:0},e(window).on("load",function(){e('[data-spy="affix"]').each(function(){var t=e(this),n=t.data();n.offset=n.offset||{},n.offsetBottom&&(n.offset.bottom=n.offsetBottom),n.offsetTop&&(n.offset.top=n.offsetTop),t.affix(n)})})}(window.jQuery);/*! jQuery v1.8.2 jquery.com | jquery.org/license */
(function(a,b){function G(a){var b=F[a]={};return p.each(a.split(s),function(a,c){b[c]=!0}),b}function J(a,c,d){if(d===b&&a.nodeType===1){var e="data-"+c.replace(I,"-$1").toLowerCase();d=a.getAttribute(e);if(typeof d=="string"){try{d=d==="true"?!0:d==="false"?!1:d==="null"?null:+d+""===d?+d:H.test(d)?p.parseJSON(d):d}catch(f){}p.data(a,c,d)}else d=b}return d}function K(a){var b;for(b in a){if(b==="data"&&p.isEmptyObject(a[b]))continue;if(b!=="toJSON")return!1}return!0}function ba(){return!1}function bb(){return!0}function bh(a){return!a||!a.parentNode||a.parentNode.nodeType===11}function bi(a,b){do a=a[b];while(a&&a.nodeType!==1);return a}function bj(a,b,c){b=b||0;if(p.isFunction(b))return p.grep(a,function(a,d){var e=!!b.call(a,d,a);return e===c});if(b.nodeType)return p.grep(a,function(a,d){return a===b===c});if(typeof b=="string"){var d=p.grep(a,function(a){return a.nodeType===1});if(be.test(b))return p.filter(b,d,!c);b=p.filter(b,d)}return p.grep(a,function(a,d){return p.inArray(a,b)>=0===c})}function bk(a){var b=bl.split("|"),c=a.createDocumentFragment();if(c.createElement)while(b.length)c.createElement(b.pop());return c}function bC(a,b){return a.getElementsByTagName(b)[0]||a.appendChild(a.ownerDocument.createElement(b))}function bD(a,b){if(b.nodeType!==1||!p.hasData(a))return;var c,d,e,f=p._data(a),g=p._data(b,f),h=f.events;if(h){delete g.handle,g.events={};for(c in h)for(d=0,e=h[c].length;d<e;d++)p.event.add(b,c,h[c][d])}g.data&&(g.data=p.extend({},g.data))}function bE(a,b){var c;if(b.nodeType!==1)return;b.clearAttributes&&b.clearAttributes(),b.mergeAttributes&&b.mergeAttributes(a),c=b.nodeName.toLowerCase(),c==="object"?(b.parentNode&&(b.outerHTML=a.outerHTML),p.support.html5Clone&&a.innerHTML&&!p.trim(b.innerHTML)&&(b.innerHTML=a.innerHTML)):c==="input"&&bv.test(a.type)?(b.defaultChecked=b.checked=a.checked,b.value!==a.value&&(b.value=a.value)):c==="option"?b.selected=a.defaultSelected:c==="input"||c==="textarea"?b.defaultValue=a.defaultValue:c==="script"&&b.text!==a.text&&(b.text=a.text),b.removeAttribute(p.expando)}function bF(a){return typeof a.getElementsByTagName!="undefined"?a.getElementsByTagName("*"):typeof a.querySelectorAll!="undefined"?a.querySelectorAll("*"):[]}function bG(a){bv.test(a.type)&&(a.defaultChecked=a.checked)}function bY(a,b){if(b in a)return b;var c=b.charAt(0).toUpperCase()+b.slice(1),d=b,e=bW.length;while(e--){b=bW[e]+c;if(b in a)return b}return d}function bZ(a,b){return a=b||a,p.css(a,"display")==="none"||!p.contains(a.ownerDocument,a)}function b$(a,b){var c,d,e=[],f=0,g=a.length;for(;f<g;f++){c=a[f];if(!c.style)continue;e[f]=p._data(c,"olddisplay"),b?(!e[f]&&c.style.display==="none"&&(c.style.display=""),c.style.display===""&&bZ(c)&&(e[f]=p._data(c,"olddisplay",cc(c.nodeName)))):(d=bH(c,"display"),!e[f]&&d!=="none"&&p._data(c,"olddisplay",d))}for(f=0;f<g;f++){c=a[f];if(!c.style)continue;if(!b||c.style.display==="none"||c.style.display==="")c.style.display=b?e[f]||"":"none"}return a}function b_(a,b,c){var d=bP.exec(b);return d?Math.max(0,d[1]-(c||0))+(d[2]||"px"):b}function ca(a,b,c,d){var e=c===(d?"border":"content")?4:b==="width"?1:0,f=0;for(;e<4;e+=2)c==="margin"&&(f+=p.css(a,c+bV[e],!0)),d?(c==="content"&&(f-=parseFloat(bH(a,"padding"+bV[e]))||0),c!=="margin"&&(f-=parseFloat(bH(a,"border"+bV[e]+"Width"))||0)):(f+=parseFloat(bH(a,"padding"+bV[e]))||0,c!=="padding"&&(f+=parseFloat(bH(a,"border"+bV[e]+"Width"))||0));return f}function cb(a,b,c){var d=b==="width"?a.offsetWidth:a.offsetHeight,e=!0,f=p.support.boxSizing&&p.css(a,"boxSizing")==="border-box";if(d<=0||d==null){d=bH(a,b);if(d<0||d==null)d=a.style[b];if(bQ.test(d))return d;e=f&&(p.support.boxSizingReliable||d===a.style[b]),d=parseFloat(d)||0}return d+ca(a,b,c||(f?"border":"content"),e)+"px"}function cc(a){if(bS[a])return bS[a];var b=p("<"+a+">").appendTo(e.body),c=b.css("display");b.remove();if(c==="none"||c===""){bI=e.body.appendChild(bI||p.extend(e.createElement("iframe"),{frameBorder:0,width:0,height:0}));if(!bJ||!bI.createElement)bJ=(bI.contentWindow||bI.contentDocument).document,bJ.write("<!doctype html><html><body>"),bJ.close();b=bJ.body.appendChild(bJ.createElement(a)),c=bH(b,"display"),e.body.removeChild(bI)}return bS[a]=c,c}function ci(a,b,c,d){var e;if(p.isArray(b))p.each(b,function(b,e){c||ce.test(a)?d(a,e):ci(a+"["+(typeof e=="object"?b:"")+"]",e,c,d)});else if(!c&&p.type(b)==="object")for(e in b)ci(a+"["+e+"]",b[e],c,d);else d(a,b)}function cz(a){return function(b,c){typeof b!="string"&&(c=b,b="*");var d,e,f,g=b.toLowerCase().split(s),h=0,i=g.length;if(p.isFunction(c))for(;h<i;h++)d=g[h],f=/^\+/.test(d),f&&(d=d.substr(1)||"*"),e=a[d]=a[d]||[],e[f?"unshift":"push"](c)}}function cA(a,c,d,e,f,g){f=f||c.dataTypes[0],g=g||{},g[f]=!0;var h,i=a[f],j=0,k=i?i.length:0,l=a===cv;for(;j<k&&(l||!h);j++)h=i[j](c,d,e),typeof h=="string"&&(!l||g[h]?h=b:(c.dataTypes.unshift(h),h=cA(a,c,d,e,h,g)));return(l||!h)&&!g["*"]&&(h=cA(a,c,d,e,"*",g)),h}function cB(a,c){var d,e,f=p.ajaxSettings.flatOptions||{};for(d in c)c[d]!==b&&((f[d]?a:e||(e={}))[d]=c[d]);e&&p.extend(!0,a,e)}function cC(a,c,d){var e,f,g,h,i=a.contents,j=a.dataTypes,k=a.responseFields;for(f in k)f in d&&(c[k[f]]=d[f]);while(j[0]==="*")j.shift(),e===b&&(e=a.mimeType||c.getResponseHeader("content-type"));if(e)for(f in i)if(i[f]&&i[f].test(e)){j.unshift(f);break}if(j[0]in d)g=j[0];else{for(f in d){if(!j[0]||a.converters[f+" "+j[0]]){g=f;break}h||(h=f)}g=g||h}if(g)return g!==j[0]&&j.unshift(g),d[g]}function cD(a,b){var c,d,e,f,g=a.dataTypes.slice(),h=g[0],i={},j=0;a.dataFilter&&(b=a.dataFilter(b,a.dataType));if(g[1])for(c in a.converters)i[c.toLowerCase()]=a.converters[c];for(;e=g[++j];)if(e!=="*"){if(h!=="*"&&h!==e){c=i[h+" "+e]||i["* "+e];if(!c)for(d in i){f=d.split(" ");if(f[1]===e){c=i[h+" "+f[0]]||i["* "+f[0]];if(c){c===!0?c=i[d]:i[d]!==!0&&(e=f[0],g.splice(j--,0,e));break}}}if(c!==!0)if(c&&a["throws"])b=c(b);else try{b=c(b)}catch(k){return{state:"parsererror",error:c?k:"No conversion from "+h+" to "+e}}}h=e}return{state:"success",data:b}}function cL(){try{return new a.XMLHttpRequest}catch(b){}}function cM(){try{return new a.ActiveXObject("Microsoft.XMLHTTP")}catch(b){}}function cU(){return setTimeout(function(){cN=b},0),cN=p.now()}function cV(a,b){p.each(b,function(b,c){var d=(cT[b]||[]).concat(cT["*"]),e=0,f=d.length;for(;e<f;e++)if(d[e].call(a,b,c))return})}function cW(a,b,c){var d,e=0,f=0,g=cS.length,h=p.Deferred().always(function(){delete i.elem}),i=function(){var b=cN||cU(),c=Math.max(0,j.startTime+j.duration-b),d=1-(c/j.duration||0),e=0,f=j.tweens.length;for(;e<f;e++)j.tweens[e].run(d);return h.notifyWith(a,[j,d,c]),d<1&&f?c:(h.resolveWith(a,[j]),!1)},j=h.promise({elem:a,props:p.extend({},b),opts:p.extend(!0,{specialEasing:{}},c),originalProperties:b,originalOptions:c,startTime:cN||cU(),duration:c.duration,tweens:[],createTween:function(b,c,d){var e=p.Tween(a,j.opts,b,c,j.opts.specialEasing[b]||j.opts.easing);return j.tweens.push(e),e},stop:function(b){var c=0,d=b?j.tweens.length:0;for(;c<d;c++)j.tweens[c].run(1);return b?h.resolveWith(a,[j,b]):h.rejectWith(a,[j,b]),this}}),k=j.props;cX(k,j.opts.specialEasing);for(;e<g;e++){d=cS[e].call(j,a,k,j.opts);if(d)return d}return cV(j,k),p.isFunction(j.opts.start)&&j.opts.start.call(a,j),p.fx.timer(p.extend(i,{anim:j,queue:j.opts.queue,elem:a})),j.progress(j.opts.progress).done(j.opts.done,j.opts.complete).fail(j.opts.fail).always(j.opts.always)}function cX(a,b){var c,d,e,f,g;for(c in a){d=p.camelCase(c),e=b[d],f=a[c],p.isArray(f)&&(e=f[1],f=a[c]=f[0]),c!==d&&(a[d]=f,delete a[c]),g=p.cssHooks[d];if(g&&"expand"in g){f=g.expand(f),delete a[d];for(c in f)c in a||(a[c]=f[c],b[c]=e)}else b[d]=e}}function cY(a,b,c){var d,e,f,g,h,i,j,k,l=this,m=a.style,n={},o=[],q=a.nodeType&&bZ(a);c.queue||(j=p._queueHooks(a,"fx"),j.unqueued==null&&(j.unqueued=0,k=j.empty.fire,j.empty.fire=function(){j.unqueued||k()}),j.unqueued++,l.always(function(){l.always(function(){j.unqueued--,p.queue(a,"fx").length||j.empty.fire()})})),a.nodeType===1&&("height"in b||"width"in b)&&(c.overflow=[m.overflow,m.overflowX,m.overflowY],p.css(a,"display")==="inline"&&p.css(a,"float")==="none"&&(!p.support.inlineBlockNeedsLayout||cc(a.nodeName)==="inline"?m.display="inline-block":m.zoom=1)),c.overflow&&(m.overflow="hidden",p.support.shrinkWrapBlocks||l.done(function(){m.overflow=c.overflow[0],m.overflowX=c.overflow[1],m.overflowY=c.overflow[2]}));for(d in b){f=b[d];if(cP.exec(f)){delete b[d];if(f===(q?"hide":"show"))continue;o.push(d)}}g=o.length;if(g){h=p._data(a,"fxshow")||p._data(a,"fxshow",{}),q?p(a).show():l.done(function(){p(a).hide()}),l.done(function(){var b;p.removeData(a,"fxshow",!0);for(b in n)p.style(a,b,n[b])});for(d=0;d<g;d++)e=o[d],i=l.createTween(e,q?h[e]:0),n[e]=h[e]||p.style(a,e),e in h||(h[e]=i.start,q&&(i.end=i.start,i.start=e==="width"||e==="height"?1:0))}}function cZ(a,b,c,d,e){return new cZ.prototype.init(a,b,c,d,e)}function c$(a,b){var c,d={height:a},e=0;b=b?1:0;for(;e<4;e+=2-b)c=bV[e],d["margin"+c]=d["padding"+c]=a;return b&&(d.opacity=d.width=a),d}function da(a){return p.isWindow(a)?a:a.nodeType===9?a.defaultView||a.parentWindow:!1}var c,d,e=a.document,f=a.location,g=a.navigator,h=a.jQuery,i=a.$,j=Array.prototype.push,k=Array.prototype.slice,l=Array.prototype.indexOf,m=Object.prototype.toString,n=Object.prototype.hasOwnProperty,o=String.prototype.trim,p=function(a,b){return new p.fn.init(a,b,c)},q=/[\-+]?(?:\d*\.|)\d+(?:[eE][\-+]?\d+|)/.source,r=/\S/,s=/\s+/,t=/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g,u=/^(?:[^#<]*(<[\w\W]+>)[^>]*$|#([\w\-]*)$)/,v=/^<(\w+)\s*\/?>(?:<\/\1>|)$/,w=/^[\],:{}\s]*$/,x=/(?:^|:|,)(?:\s*\[)+/g,y=/\\(?:["\\\/bfnrt]|u[\da-fA-F]{4})/g,z=/"[^"\\\r\n]*"|true|false|null|-?(?:\d\d*\.|)\d+(?:[eE][\-+]?\d+|)/g,A=/^-ms-/,B=/-([\da-z])/gi,C=function(a,b){return(b+"").toUpperCase()},D=function(){e.addEventListener?(e.removeEventListener("DOMContentLoaded",D,!1),p.ready()):e.readyState==="complete"&&(e.detachEvent("onreadystatechange",D),p.ready())},E={};p.fn=p.prototype={constructor:p,init:function(a,c,d){var f,g,h,i;if(!a)return this;if(a.nodeType)return this.context=this[0]=a,this.length=1,this;if(typeof a=="string"){a.charAt(0)==="<"&&a.charAt(a.length-1)===">"&&a.length>=3?f=[null,a,null]:f=u.exec(a);if(f&&(f[1]||!c)){if(f[1])return c=c instanceof p?c[0]:c,i=c&&c.nodeType?c.ownerDocument||c:e,a=p.parseHTML(f[1],i,!0),v.test(f[1])&&p.isPlainObject(c)&&this.attr.call(a,c,!0),p.merge(this,a);g=e.getElementById(f[2]);if(g&&g.parentNode){if(g.id!==f[2])return d.find(a);this.length=1,this[0]=g}return this.context=e,this.selector=a,this}return!c||c.jquery?(c||d).find(a):this.constructor(c).find(a)}return p.isFunction(a)?d.ready(a):(a.selector!==b&&(this.selector=a.selector,this.context=a.context),p.makeArray(a,this))},selector:"",jquery:"1.8.2",length:0,size:function(){return this.length},toArray:function(){return k.call(this)},get:function(a){return a==null?this.toArray():a<0?this[this.length+a]:this[a]},pushStack:function(a,b,c){var d=p.merge(this.constructor(),a);return d.prevObject=this,d.context=this.context,b==="find"?d.selector=this.selector+(this.selector?" ":"")+c:b&&(d.selector=this.selector+"."+b+"("+c+")"),d},each:function(a,b){return p.each(this,a,b)},ready:function(a){return p.ready.promise().done(a),this},eq:function(a){return a=+a,a===-1?this.slice(a):this.slice(a,a+1)},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},slice:function(){return this.pushStack(k.apply(this,arguments),"slice",k.call(arguments).join(","))},map:function(a){return this.pushStack(p.map(this,function(b,c){return a.call(b,c,b)}))},end:function(){return this.prevObject||this.constructor(null)},push:j,sort:[].sort,splice:[].splice},p.fn.init.prototype=p.fn,p.extend=p.fn.extend=function(){var a,c,d,e,f,g,h=arguments[0]||{},i=1,j=arguments.length,k=!1;typeof h=="boolean"&&(k=h,h=arguments[1]||{},i=2),typeof h!="object"&&!p.isFunction(h)&&(h={}),j===i&&(h=this,--i);for(;i<j;i++)if((a=arguments[i])!=null)for(c in a){d=h[c],e=a[c];if(h===e)continue;k&&e&&(p.isPlainObject(e)||(f=p.isArray(e)))?(f?(f=!1,g=d&&p.isArray(d)?d:[]):g=d&&p.isPlainObject(d)?d:{},h[c]=p.extend(k,g,e)):e!==b&&(h[c]=e)}return h},p.extend({noConflict:function(b){return a.$===p&&(a.$=i),b&&a.jQuery===p&&(a.jQuery=h),p},isReady:!1,readyWait:1,holdReady:function(a){a?p.readyWait++:p.ready(!0)},ready:function(a){if(a===!0?--p.readyWait:p.isReady)return;if(!e.body)return setTimeout(p.ready,1);p.isReady=!0;if(a!==!0&&--p.readyWait>0)return;d.resolveWith(e,[p]),p.fn.trigger&&p(e).trigger("ready").off("ready")},isFunction:function(a){return p.type(a)==="function"},isArray:Array.isArray||function(a){return p.type(a)==="array"},isWindow:function(a){return a!=null&&a==a.window},isNumeric:function(a){return!isNaN(parseFloat(a))&&isFinite(a)},type:function(a){return a==null?String(a):E[m.call(a)]||"object"},isPlainObject:function(a){if(!a||p.type(a)!=="object"||a.nodeType||p.isWindow(a))return!1;try{if(a.constructor&&!n.call(a,"constructor")&&!n.call(a.constructor.prototype,"isPrototypeOf"))return!1}catch(c){return!1}var d;for(d in a);return d===b||n.call(a,d)},isEmptyObject:function(a){var b;for(b in a)return!1;return!0},error:function(a){throw new Error(a)},parseHTML:function(a,b,c){var d;return!a||typeof a!="string"?null:(typeof b=="boolean"&&(c=b,b=0),b=b||e,(d=v.exec(a))?[b.createElement(d[1])]:(d=p.buildFragment([a],b,c?null:[]),p.merge([],(d.cacheable?p.clone(d.fragment):d.fragment).childNodes)))},parseJSON:function(b){if(!b||typeof b!="string")return null;b=p.trim(b);if(a.JSON&&a.JSON.parse)return a.JSON.parse(b);if(w.test(b.replace(y,"@").replace(z,"]").replace(x,"")))return(new Function("return "+b))();p.error("Invalid JSON: "+b)},parseXML:function(c){var d,e;if(!c||typeof c!="string")return null;try{a.DOMParser?(e=new DOMParser,d=e.parseFromString(c,"text/xml")):(d=new ActiveXObject("Microsoft.XMLDOM"),d.async="false",d.loadXML(c))}catch(f){d=b}return(!d||!d.documentElement||d.getElementsByTagName("parsererror").length)&&p.error("Invalid XML: "+c),d},noop:function(){},globalEval:function(b){b&&r.test(b)&&(a.execScript||function(b){a.eval.call(a,b)})(b)},camelCase:function(a){return a.replace(A,"ms-").replace(B,C)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toLowerCase()===b.toLowerCase()},each:function(a,c,d){var e,f=0,g=a.length,h=g===b||p.isFunction(a);if(d){if(h){for(e in a)if(c.apply(a[e],d)===!1)break}else for(;f<g;)if(c.apply(a[f++],d)===!1)break}else if(h){for(e in a)if(c.call(a[e],e,a[e])===!1)break}else for(;f<g;)if(c.call(a[f],f,a[f++])===!1)break;return a},trim:o&&!o.call("﻿ ")?function(a){return a==null?"":o.call(a)}:function(a){return a==null?"":(a+"").replace(t,"")},makeArray:function(a,b){var c,d=b||[];return a!=null&&(c=p.type(a),a.length==null||c==="string"||c==="function"||c==="regexp"||p.isWindow(a)?j.call(d,a):p.merge(d,a)),d},inArray:function(a,b,c){var d;if(b){if(l)return l.call(b,a,c);d=b.length,c=c?c<0?Math.max(0,d+c):c:0;for(;c<d;c++)if(c in b&&b[c]===a)return c}return-1},merge:function(a,c){var d=c.length,e=a.length,f=0;if(typeof d=="number")for(;f<d;f++)a[e++]=c[f];else while(c[f]!==b)a[e++]=c[f++];return a.length=e,a},grep:function(a,b,c){var d,e=[],f=0,g=a.length;c=!!c;for(;f<g;f++)d=!!b(a[f],f),c!==d&&e.push(a[f]);return e},map:function(a,c,d){var e,f,g=[],h=0,i=a.length,j=a instanceof p||i!==b&&typeof i=="number"&&(i>0&&a[0]&&a[i-1]||i===0||p.isArray(a));if(j)for(;h<i;h++)e=c(a[h],h,d),e!=null&&(g[g.length]=e);else for(f in a)e=c(a[f],f,d),e!=null&&(g[g.length]=e);return g.concat.apply([],g)},guid:1,proxy:function(a,c){var d,e,f;return typeof c=="string"&&(d=a[c],c=a,a=d),p.isFunction(a)?(e=k.call(arguments,2),f=function(){return a.apply(c,e.concat(k.call(arguments)))},f.guid=a.guid=a.guid||p.guid++,f):b},access:function(a,c,d,e,f,g,h){var i,j=d==null,k=0,l=a.length;if(d&&typeof d=="object"){for(k in d)p.access(a,c,k,d[k],1,g,e);f=1}else if(e!==b){i=h===b&&p.isFunction(e),j&&(i?(i=c,c=function(a,b,c){return i.call(p(a),c)}):(c.call(a,e),c=null));if(c)for(;k<l;k++)c(a[k],d,i?e.call(a[k],k,c(a[k],d)):e,h);f=1}return f?a:j?c.call(a):l?c(a[0],d):g},now:function(){return(new Date).getTime()}}),p.ready.promise=function(b){if(!d){d=p.Deferred();if(e.readyState==="complete")setTimeout(p.ready,1);else if(e.addEventListener)e.addEventListener("DOMContentLoaded",D,!1),a.addEventListener("load",p.ready,!1);else{e.attachEvent("onreadystatechange",D),a.attachEvent("onload",p.ready);var c=!1;try{c=a.frameElement==null&&e.documentElement}catch(f){}c&&c.doScroll&&function g(){if(!p.isReady){try{c.doScroll("left")}catch(a){return setTimeout(g,50)}p.ready()}}()}}return d.promise(b)},p.each("Boolean Number String Function Array Date RegExp Object".split(" "),function(a,b){E["[object "+b+"]"]=b.toLowerCase()}),c=p(e);var F={};p.Callbacks=function(a){a=typeof a=="string"?F[a]||G(a):p.extend({},a);var c,d,e,f,g,h,i=[],j=!a.once&&[],k=function(b){c=a.memory&&b,d=!0,h=f||0,f=0,g=i.length,e=!0;for(;i&&h<g;h++)if(i[h].apply(b[0],b[1])===!1&&a.stopOnFalse){c=!1;break}e=!1,i&&(j?j.length&&k(j.shift()):c?i=[]:l.disable())},l={add:function(){if(i){var b=i.length;(function d(b){p.each(b,function(b,c){var e=p.type(c);e==="function"&&(!a.unique||!l.has(c))?i.push(c):c&&c.length&&e!=="string"&&d(c)})})(arguments),e?g=i.length:c&&(f=b,k(c))}return this},remove:function(){return i&&p.each(arguments,function(a,b){var c;while((c=p.inArray(b,i,c))>-1)i.splice(c,1),e&&(c<=g&&g--,c<=h&&h--)}),this},has:function(a){return p.inArray(a,i)>-1},empty:function(){return i=[],this},disable:function(){return i=j=c=b,this},disabled:function(){return!i},lock:function(){return j=b,c||l.disable(),this},locked:function(){return!j},fireWith:function(a,b){return b=b||[],b=[a,b.slice?b.slice():b],i&&(!d||j)&&(e?j.push(b):k(b)),this},fire:function(){return l.fireWith(this,arguments),this},fired:function(){return!!d}};return l},p.extend({Deferred:function(a){var b=[["resolve","done",p.Callbacks("once memory"),"resolved"],["reject","fail",p.Callbacks("once memory"),"rejected"],["notify","progress",p.Callbacks("memory")]],c="pending",d={state:function(){return c},always:function(){return e.done(arguments).fail(arguments),this},then:function(){var a=arguments;return p.Deferred(function(c){p.each(b,function(b,d){var f=d[0],g=a[b];e[d[1]](p.isFunction(g)?function(){var a=g.apply(this,arguments);a&&p.isFunction(a.promise)?a.promise().done(c.resolve).fail(c.reject).progress(c.notify):c[f+"With"](this===e?c:this,[a])}:c[f])}),a=null}).promise()},promise:function(a){return a!=null?p.extend(a,d):d}},e={};return d.pipe=d.then,p.each(b,function(a,f){var g=f[2],h=f[3];d[f[1]]=g.add,h&&g.add(function(){c=h},b[a^1][2].disable,b[2][2].lock),e[f[0]]=g.fire,e[f[0]+"With"]=g.fireWith}),d.promise(e),a&&a.call(e,e),e},when:function(a){var b=0,c=k.call(arguments),d=c.length,e=d!==1||a&&p.isFunction(a.promise)?d:0,f=e===1?a:p.Deferred(),g=function(a,b,c){return function(d){b[a]=this,c[a]=arguments.length>1?k.call(arguments):d,c===h?f.notifyWith(b,c):--e||f.resolveWith(b,c)}},h,i,j;if(d>1){h=new Array(d),i=new Array(d),j=new Array(d);for(;b<d;b++)c[b]&&p.isFunction(c[b].promise)?c[b].promise().done(g(b,j,c)).fail(f.reject).progress(g(b,i,h)):--e}return e||f.resolveWith(j,c),f.promise()}}),p.support=function(){var b,c,d,f,g,h,i,j,k,l,m,n=e.createElement("div");n.setAttribute("className","t"),n.innerHTML="  <link/><table></table><a href='/a'>a</a><input type='checkbox'/>",c=n.getElementsByTagName("*"),d=n.getElementsByTagName("a")[0],d.style.cssText="top:1px;float:left;opacity:.5";if(!c||!c.length)return{};f=e.createElement("select"),g=f.appendChild(e.createElement("option")),h=n.getElementsByTagName("input")[0],b={leadingWhitespace:n.firstChild.nodeType===3,tbody:!n.getElementsByTagName("tbody").length,htmlSerialize:!!n.getElementsByTagName("link").length,style:/top/.test(d.getAttribute("style")),hrefNormalized:d.getAttribute("href")==="/a",opacity:/^0.5/.test(d.style.opacity),cssFloat:!!d.style.cssFloat,checkOn:h.value==="on",optSelected:g.selected,getSetAttribute:n.className!=="t",enctype:!!e.createElement("form").enctype,html5Clone:e.createElement("nav").cloneNode(!0).outerHTML!=="<:nav></:nav>",boxModel:e.compatMode==="CSS1Compat",submitBubbles:!0,changeBubbles:!0,focusinBubbles:!1,deleteExpando:!0,noCloneEvent:!0,inlineBlockNeedsLayout:!1,shrinkWrapBlocks:!1,reliableMarginRight:!0,boxSizingReliable:!0,pixelPosition:!1},h.checked=!0,b.noCloneChecked=h.cloneNode(!0).checked,f.disabled=!0,b.optDisabled=!g.disabled;try{delete n.test}catch(o){b.deleteExpando=!1}!n.addEventListener&&n.attachEvent&&n.fireEvent&&(n.attachEvent("onclick",m=function(){b.noCloneEvent=!1}),n.cloneNode(!0).fireEvent("onclick"),n.detachEvent("onclick",m)),h=e.createElement("input"),h.value="t",h.setAttribute("type","radio"),b.radioValue=h.value==="t",h.setAttribute("checked","checked"),h.setAttribute("name","t"),n.appendChild(h),i=e.createDocumentFragment(),i.appendChild(n.lastChild),b.checkClone=i.cloneNode(!0).cloneNode(!0).lastChild.checked,b.appendChecked=h.checked,i.removeChild(h),i.appendChild(n);if(n.attachEvent)for(k in{submit:!0,change:!0,focusin:!0})j="on"+k,l=j in n,l||(n.setAttribute(j,"return;"),l=typeof n[j]=="function"),b[k+"Bubbles"]=l;return p(function(){var c,d,f,g,h="padding:0;margin:0;border:0;display:block;overflow:hidden;",i=e.getElementsByTagName("body")[0];if(!i)return;c=e.createElement("div"),c.style.cssText="visibility:hidden;border:0;width:0;height:0;position:static;top:0;margin-top:1px",i.insertBefore(c,i.firstChild),d=e.createElement("div"),c.appendChild(d),d.innerHTML="<table><tr><td></td><td>t</td></tr></table>",f=d.getElementsByTagName("td"),f[0].style.cssText="padding:0;margin:0;border:0;display:none",l=f[0].offsetHeight===0,f[0].style.display="",f[1].style.display="none",b.reliableHiddenOffsets=l&&f[0].offsetHeight===0,d.innerHTML="",d.style.cssText="box-sizing:border-box;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;padding:1px;border:1px;display:block;width:4px;margin-top:1%;position:absolute;top:1%;",b.boxSizing=d.offsetWidth===4,b.doesNotIncludeMarginInBodyOffset=i.offsetTop!==1,a.getComputedStyle&&(b.pixelPosition=(a.getComputedStyle(d,null)||{}).top!=="1%",b.boxSizingReliable=(a.getComputedStyle(d,null)||{width:"4px"}).width==="4px",g=e.createElement("div"),g.style.cssText=d.style.cssText=h,g.style.marginRight=g.style.width="0",d.style.width="1px",d.appendChild(g),b.reliableMarginRight=!parseFloat((a.getComputedStyle(g,null)||{}).marginRight)),typeof d.style.zoom!="undefined"&&(d.innerHTML="",d.style.cssText=h+"width:1px;padding:1px;display:inline;zoom:1",b.inlineBlockNeedsLayout=d.offsetWidth===3,d.style.display="block",d.style.overflow="visible",d.innerHTML="<div></div>",d.firstChild.style.width="5px",b.shrinkWrapBlocks=d.offsetWidth!==3,c.style.zoom=1),i.removeChild(c),c=d=f=g=null}),i.removeChild(n),c=d=f=g=h=i=n=null,b}();var H=/(?:\{[\s\S]*\}|\[[\s\S]*\])$/,I=/([A-Z])/g;p.extend({cache:{},deletedIds:[],uuid:0,expando:"jQuery"+(p.fn.jquery+Math.random()).replace(/\D/g,""),noData:{embed:!0,object:"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000",applet:!0},hasData:function(a){return a=a.nodeType?p.cache[a[p.expando]]:a[p.expando],!!a&&!K(a)},data:function(a,c,d,e){if(!p.acceptData(a))return;var f,g,h=p.expando,i=typeof c=="string",j=a.nodeType,k=j?p.cache:a,l=j?a[h]:a[h]&&h;if((!l||!k[l]||!e&&!k[l].data)&&i&&d===b)return;l||(j?a[h]=l=p.deletedIds.pop()||p.guid++:l=h),k[l]||(k[l]={},j||(k[l].toJSON=p.noop));if(typeof c=="object"||typeof c=="function")e?k[l]=p.extend(k[l],c):k[l].data=p.extend(k[l].data,c);return f=k[l],e||(f.data||(f.data={}),f=f.data),d!==b&&(f[p.camelCase(c)]=d),i?(g=f[c],g==null&&(g=f[p.camelCase(c)])):g=f,g},removeData:function(a,b,c){if(!p.acceptData(a))return;var d,e,f,g=a.nodeType,h=g?p.cache:a,i=g?a[p.expando]:p.expando;if(!h[i])return;if(b){d=c?h[i]:h[i].data;if(d){p.isArray(b)||(b in d?b=[b]:(b=p.camelCase(b),b in d?b=[b]:b=b.split(" ")));for(e=0,f=b.length;e<f;e++)delete d[b[e]];if(!(c?K:p.isEmptyObject)(d))return}}if(!c){delete h[i].data;if(!K(h[i]))return}g?p.cleanData([a],!0):p.support.deleteExpando||h!=h.window?delete h[i]:h[i]=null},_data:function(a,b,c){return p.data(a,b,c,!0)},acceptData:function(a){var b=a.nodeName&&p.noData[a.nodeName.toLowerCase()];return!b||b!==!0&&a.getAttribute("classid")===b}}),p.fn.extend({data:function(a,c){var d,e,f,g,h,i=this[0],j=0,k=null;if(a===b){if(this.length){k=p.data(i);if(i.nodeType===1&&!p._data(i,"parsedAttrs")){f=i.attributes;for(h=f.length;j<h;j++)g=f[j].name,g.indexOf("data-")||(g=p.camelCase(g.substring(5)),J(i,g,k[g]));p._data(i,"parsedAttrs",!0)}}return k}return typeof a=="object"?this.each(function(){p.data(this,a)}):(d=a.split(".",2),d[1]=d[1]?"."+d[1]:"",e=d[1]+"!",p.access(this,function(c){if(c===b)return k=this.triggerHandler("getData"+e,[d[0]]),k===b&&i&&(k=p.data(i,a),k=J(i,a,k)),k===b&&d[1]?this.data(d[0]):k;d[1]=c,this.each(function(){var b=p(this);b.triggerHandler("setData"+e,d),p.data(this,a,c),b.triggerHandler("changeData"+e,d)})},null,c,arguments.length>1,null,!1))},removeData:function(a){return this.each(function(){p.removeData(this,a)})}}),p.extend({queue:function(a,b,c){var d;if(a)return b=(b||"fx")+"queue",d=p._data(a,b),c&&(!d||p.isArray(c)?d=p._data(a,b,p.makeArray(c)):d.push(c)),d||[]},dequeue:function(a,b){b=b||"fx";var c=p.queue(a,b),d=c.length,e=c.shift(),f=p._queueHooks(a,b),g=function(){p.dequeue(a,b)};e==="inprogress"&&(e=c.shift(),d--),e&&(b==="fx"&&c.unshift("inprogress"),delete f.stop,e.call(a,g,f)),!d&&f&&f.empty.fire()},_queueHooks:function(a,b){var c=b+"queueHooks";return p._data(a,c)||p._data(a,c,{empty:p.Callbacks("once memory").add(function(){p.removeData(a,b+"queue",!0),p.removeData(a,c,!0)})})}}),p.fn.extend({queue:function(a,c){var d=2;return typeof a!="string"&&(c=a,a="fx",d--),arguments.length<d?p.queue(this[0],a):c===b?this:this.each(function(){var b=p.queue(this,a,c);p._queueHooks(this,a),a==="fx"&&b[0]!=="inprogress"&&p.dequeue(this,a)})},dequeue:function(a){return this.each(function(){p.dequeue(this,a)})},delay:function(a,b){return a=p.fx?p.fx.speeds[a]||a:a,b=b||"fx",this.queue(b,function(b,c){var d=setTimeout(b,a);c.stop=function(){clearTimeout(d)}})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,c){var d,e=1,f=p.Deferred(),g=this,h=this.length,i=function(){--e||f.resolveWith(g,[g])};typeof a!="string"&&(c=a,a=b),a=a||"fx";while(h--)d=p._data(g[h],a+"queueHooks"),d&&d.empty&&(e++,d.empty.add(i));return i(),f.promise(c)}});var L,M,N,O=/[\t\r\n]/g,P=/\r/g,Q=/^(?:button|input)$/i,R=/^(?:button|input|object|select|textarea)$/i,S=/^a(?:rea|)$/i,T=/^(?:autofocus|autoplay|async|checked|controls|defer|disabled|hidden|loop|multiple|open|readonly|required|scoped|selected)$/i,U=p.support.getSetAttribute;p.fn.extend({attr:function(a,b){return p.access(this,p.attr,a,b,arguments.length>1)},removeAttr:function(a){return this.each(function(){p.removeAttr(this,a)})},prop:function(a,b){return p.access(this,p.prop,a,b,arguments.length>1)},removeProp:function(a){return a=p.propFix[a]||a,this.each(function(){try{this[a]=b,delete this[a]}catch(c){}})},addClass:function(a){var b,c,d,e,f,g,h;if(p.isFunction(a))return this.each(function(b){p(this).addClass(a.call(this,b,this.className))});if(a&&typeof a=="string"){b=a.split(s);for(c=0,d=this.length;c<d;c++){e=this[c];if(e.nodeType===1)if(!e.className&&b.length===1)e.className=a;else{f=" "+e.className+" ";for(g=0,h=b.length;g<h;g++)f.indexOf(" "+b[g]+" ")<0&&(f+=b[g]+" ");e.className=p.trim(f)}}}return this},removeClass:function(a){var c,d,e,f,g,h,i;if(p.isFunction(a))return this.each(function(b){p(this).removeClass(a.call(this,b,this.className))});if(a&&typeof a=="string"||a===b){c=(a||"").split(s);for(h=0,i=this.length;h<i;h++){e=this[h];if(e.nodeType===1&&e.className){d=(" "+e.className+" ").replace(O," ");for(f=0,g=c.length;f<g;f++)while(d.indexOf(" "+c[f]+" ")>=0)d=d.replace(" "+c[f]+" "," ");e.className=a?p.trim(d):""}}}return this},toggleClass:function(a,b){var c=typeof a,d=typeof b=="boolean";return p.isFunction(a)?this.each(function(c){p(this).toggleClass(a.call(this,c,this.className,b),b)}):this.each(function(){if(c==="string"){var e,f=0,g=p(this),h=b,i=a.split(s);while(e=i[f++])h=d?h:!g.hasClass(e),g[h?"addClass":"removeClass"](e)}else if(c==="undefined"||c==="boolean")this.className&&p._data(this,"__className__",this.className),this.className=this.className||a===!1?"":p._data(this,"__className__")||""})},hasClass:function(a){var b=" "+a+" ",c=0,d=this.length;for(;c<d;c++)if(this[c].nodeType===1&&(" "+this[c].className+" ").replace(O," ").indexOf(b)>=0)return!0;return!1},val:function(a){var c,d,e,f=this[0];if(!arguments.length){if(f)return c=p.valHooks[f.type]||p.valHooks[f.nodeName.toLowerCase()],c&&"get"in c&&(d=c.get(f,"value"))!==b?d:(d=f.value,typeof d=="string"?d.replace(P,""):d==null?"":d);return}return e=p.isFunction(a),this.each(function(d){var f,g=p(this);if(this.nodeType!==1)return;e?f=a.call(this,d,g.val()):f=a,f==null?f="":typeof f=="number"?f+="":p.isArray(f)&&(f=p.map(f,function(a){return a==null?"":a+""})),c=p.valHooks[this.type]||p.valHooks[this.nodeName.toLowerCase()];if(!c||!("set"in c)||c.set(this,f,"value")===b)this.value=f})}}),p.extend({valHooks:{option:{get:function(a){var b=a.attributes.value;return!b||b.specified?a.value:a.text}},select:{get:function(a){var b,c,d,e,f=a.selectedIndex,g=[],h=a.options,i=a.type==="select-one";if(f<0)return null;c=i?f:0,d=i?f+1:h.length;for(;c<d;c++){e=h[c];if(e.selected&&(p.support.optDisabled?!e.disabled:e.getAttribute("disabled")===null)&&(!e.parentNode.disabled||!p.nodeName(e.parentNode,"optgroup"))){b=p(e).val();if(i)return b;g.push(b)}}return i&&!g.length&&h.length?p(h[f]).val():g},set:function(a,b){var c=p.makeArray(b);return p(a).find("option").each(function(){this.selected=p.inArray(p(this).val(),c)>=0}),c.length||(a.selectedIndex=-1),c}}},attrFn:{},attr:function(a,c,d,e){var f,g,h,i=a.nodeType;if(!a||i===3||i===8||i===2)return;if(e&&p.isFunction(p.fn[c]))return p(a)[c](d);if(typeof a.getAttribute=="undefined")return p.prop(a,c,d);h=i!==1||!p.isXMLDoc(a),h&&(c=c.toLowerCase(),g=p.attrHooks[c]||(T.test(c)?M:L));if(d!==b){if(d===null){p.removeAttr(a,c);return}return g&&"set"in g&&h&&(f=g.set(a,d,c))!==b?f:(a.setAttribute(c,d+""),d)}return g&&"get"in g&&h&&(f=g.get(a,c))!==null?f:(f=a.getAttribute(c),f===null?b:f)},removeAttr:function(a,b){var c,d,e,f,g=0;if(b&&a.nodeType===1){d=b.split(s);for(;g<d.length;g++)e=d[g],e&&(c=p.propFix[e]||e,f=T.test(e),f||p.attr(a,e,""),a.removeAttribute(U?e:c),f&&c in a&&(a[c]=!1))}},attrHooks:{type:{set:function(a,b){if(Q.test(a.nodeName)&&a.parentNode)p.error("type property can't be changed");else if(!p.support.radioValue&&b==="radio"&&p.nodeName(a,"input")){var c=a.value;return a.setAttribute("type",b),c&&(a.value=c),b}}},value:{get:function(a,b){return L&&p.nodeName(a,"button")?L.get(a,b):b in a?a.value:null},set:function(a,b,c){if(L&&p.nodeName(a,"button"))return L.set(a,b,c);a.value=b}}},propFix:{tabindex:"tabIndex",readonly:"readOnly","for":"htmlFor","class":"className",maxlength:"maxLength",cellspacing:"cellSpacing",cellpadding:"cellPadding",rowspan:"rowSpan",colspan:"colSpan",usemap:"useMap",frameborder:"frameBorder",contenteditable:"contentEditable"},prop:function(a,c,d){var e,f,g,h=a.nodeType;if(!a||h===3||h===8||h===2)return;return g=h!==1||!p.isXMLDoc(a),g&&(c=p.propFix[c]||c,f=p.propHooks[c]),d!==b?f&&"set"in f&&(e=f.set(a,d,c))!==b?e:a[c]=d:f&&"get"in f&&(e=f.get(a,c))!==null?e:a[c]},propHooks:{tabIndex:{get:function(a){var c=a.getAttributeNode("tabindex");return c&&c.specified?parseInt(c.value,10):R.test(a.nodeName)||S.test(a.nodeName)&&a.href?0:b}}}}),M={get:function(a,c){var d,e=p.prop(a,c);return e===!0||typeof e!="boolean"&&(d=a.getAttributeNode(c))&&d.nodeValue!==!1?c.toLowerCase():b},set:function(a,b,c){var d;return b===!1?p.removeAttr(a,c):(d=p.propFix[c]||c,d in a&&(a[d]=!0),a.setAttribute(c,c.toLowerCase())),c}},U||(N={name:!0,id:!0,coords:!0},L=p.valHooks.button={get:function(a,c){var d;return d=a.getAttributeNode(c),d&&(N[c]?d.value!=="":d.specified)?d.value:b},set:function(a,b,c){var d=a.getAttributeNode(c);return d||(d=e.createAttribute(c),a.setAttributeNode(d)),d.value=b+""}},p.each(["width","height"],function(a,b){p.attrHooks[b]=p.extend(p.attrHooks[b],{set:function(a,c){if(c==="")return a.setAttribute(b,"auto"),c}})}),p.attrHooks.contenteditable={get:L.get,set:function(a,b,c){b===""&&(b="false"),L.set(a,b,c)}}),p.support.hrefNormalized||p.each(["href","src","width","height"],function(a,c){p.attrHooks[c]=p.extend(p.attrHooks[c],{get:function(a){var d=a.getAttribute(c,2);return d===null?b:d}})}),p.support.style||(p.attrHooks.style={get:function(a){return a.style.cssText.toLowerCase()||b},set:function(a,b){return a.style.cssText=b+""}}),p.support.optSelected||(p.propHooks.selected=p.extend(p.propHooks.selected,{get:function(a){var b=a.parentNode;return b&&(b.selectedIndex,b.parentNode&&b.parentNode.selectedIndex),null}})),p.support.enctype||(p.propFix.enctype="encoding"),p.support.checkOn||p.each(["radio","checkbox"],function(){p.valHooks[this]={get:function(a){return a.getAttribute("value")===null?"on":a.value}}}),p.each(["radio","checkbox"],function(){p.valHooks[this]=p.extend(p.valHooks[this],{set:function(a,b){if(p.isArray(b))return a.checked=p.inArray(p(a).val(),b)>=0}})});var V=/^(?:textarea|input|select)$/i,W=/^([^\.]*|)(?:\.(.+)|)$/,X=/(?:^|\s)hover(\.\S+|)\b/,Y=/^key/,Z=/^(?:mouse|contextmenu)|click/,$=/^(?:focusinfocus|focusoutblur)$/,_=function(a){return p.event.special.hover?a:a.replace(X,"mouseenter$1 mouseleave$1")};p.event={add:function(a,c,d,e,f){var g,h,i,j,k,l,m,n,o,q,r;if(a.nodeType===3||a.nodeType===8||!c||!d||!(g=p._data(a)))return;d.handler&&(o=d,d=o.handler,f=o.selector),d.guid||(d.guid=p.guid++),i=g.events,i||(g.events=i={}),h=g.handle,h||(g.handle=h=function(a){return typeof p!="undefined"&&(!a||p.event.triggered!==a.type)?p.event.dispatch.apply(h.elem,arguments):b},h.elem=a),c=p.trim(_(c)).split(" ");for(j=0;j<c.length;j++){k=W.exec(c[j])||[],l=k[1],m=(k[2]||"").split(".").sort(),r=p.event.special[l]||{},l=(f?r.delegateType:r.bindType)||l,r=p.event.special[l]||{},n=p.extend({type:l,origType:k[1],data:e,handler:d,guid:d.guid,selector:f,needsContext:f&&p.expr.match.needsContext.test(f),namespace:m.join(".")},o),q=i[l];if(!q){q=i[l]=[],q.delegateCount=0;if(!r.setup||r.setup.call(a,e,m,h)===!1)a.addEventListener?a.addEventListener(l,h,!1):a.attachEvent&&a.attachEvent("on"+l,h)}r.add&&(r.add.call(a,n),n.handler.guid||(n.handler.guid=d.guid)),f?q.splice(q.delegateCount++,0,n):q.push(n),p.event.global[l]=!0}a=null},global:{},remove:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,n,o,q,r=p.hasData(a)&&p._data(a);if(!r||!(m=r.events))return;b=p.trim(_(b||"")).split(" ");for(f=0;f<b.length;f++){g=W.exec(b[f])||[],h=i=g[1],j=g[2];if(!h){for(h in m)p.event.remove(a,h+b[f],c,d,!0);continue}n=p.event.special[h]||{},h=(d?n.delegateType:n.bindType)||h,o=m[h]||[],k=o.length,j=j?new RegExp("(^|\\.)"+j.split(".").sort().join("\\.(?:.*\\.|)")+"(\\.|$)"):null;for(l=0;l<o.length;l++)q=o[l],(e||i===q.origType)&&(!c||c.guid===q.guid)&&(!j||j.test(q.namespace))&&(!d||d===q.selector||d==="**"&&q.selector)&&(o.splice(l--,1),q.selector&&o.delegateCount--,n.remove&&n.remove.call(a,q));o.length===0&&k!==o.length&&((!n.teardown||n.teardown.call(a,j,r.handle)===!1)&&p.removeEvent(a,h,r.handle),delete m[h])}p.isEmptyObject(m)&&(delete r.handle,p.removeData(a,"events",!0))},customEvent:{getData:!0,setData:!0,changeData:!0},trigger:function(c,d,f,g){if(!f||f.nodeType!==3&&f.nodeType!==8){var h,i,j,k,l,m,n,o,q,r,s=c.type||c,t=[];if($.test(s+p.event.triggered))return;s.indexOf("!")>=0&&(s=s.slice(0,-1),i=!0),s.indexOf(".")>=0&&(t=s.split("."),s=t.shift(),t.sort());if((!f||p.event.customEvent[s])&&!p.event.global[s])return;c=typeof c=="object"?c[p.expando]?c:new p.Event(s,c):new p.Event(s),c.type=s,c.isTrigger=!0,c.exclusive=i,c.namespace=t.join("."),c.namespace_re=c.namespace?new RegExp("(^|\\.)"+t.join("\\.(?:.*\\.|)")+"(\\.|$)"):null,m=s.indexOf(":")<0?"on"+s:"";if(!f){h=p.cache;for(j in h)h[j].events&&h[j].events[s]&&p.event.trigger(c,d,h[j].handle.elem,!0);return}c.result=b,c.target||(c.target=f),d=d!=null?p.makeArray(d):[],d.unshift(c),n=p.event.special[s]||{};if(n.trigger&&n.trigger.apply(f,d)===!1)return;q=[[f,n.bindType||s]];if(!g&&!n.noBubble&&!p.isWindow(f)){r=n.delegateType||s,k=$.test(r+s)?f:f.parentNode;for(l=f;k;k=k.parentNode)q.push([k,r]),l=k;l===(f.ownerDocument||e)&&q.push([l.defaultView||l.parentWindow||a,r])}for(j=0;j<q.length&&!c.isPropagationStopped();j++)k=q[j][0],c.type=q[j][1],o=(p._data(k,"events")||{})[c.type]&&p._data(k,"handle"),o&&o.apply(k,d),o=m&&k[m],o&&p.acceptData(k)&&o.apply&&o.apply(k,d)===!1&&c.preventDefault();return c.type=s,!g&&!c.isDefaultPrevented()&&(!n._default||n._default.apply(f.ownerDocument,d)===!1)&&(s!=="click"||!p.nodeName(f,"a"))&&p.acceptData(f)&&m&&f[s]&&(s!=="focus"&&s!=="blur"||c.target.offsetWidth!==0)&&!p.isWindow(f)&&(l=f[m],l&&(f[m]=null),p.event.triggered=s,f[s](),p.event.triggered=b,l&&(f[m]=l)),c.result}return},dispatch:function(c){c=p.event.fix(c||a.event);var d,e,f,g,h,i,j,l,m,n,o=(p._data(this,"events")||{})[c.type]||[],q=o.delegateCount,r=k.call(arguments),s=!c.exclusive&&!c.namespace,t=p.event.special[c.type]||{},u=[];r[0]=c,c.delegateTarget=this;if(t.preDispatch&&t.preDispatch.call(this,c)===!1)return;if(q&&(!c.button||c.type!=="click"))for(f=c.target;f!=this;f=f.parentNode||this)if(f.disabled!==!0||c.type!=="click"){h={},j=[];for(d=0;d<q;d++)l=o[d],m=l.selector,h[m]===b&&(h[m]=l.needsContext?p(m,this).index(f)>=0:p.find(m,this,null,[f]).length),h[m]&&j.push(l);j.length&&u.push({elem:f,matches:j})}o.length>q&&u.push({elem:this,matches:o.slice(q)});for(d=0;d<u.length&&!c.isPropagationStopped();d++){i=u[d],c.currentTarget=i.elem;for(e=0;e<i.matches.length&&!c.isImmediatePropagationStopped();e++){l=i.matches[e];if(s||!c.namespace&&!l.namespace||c.namespace_re&&c.namespace_re.test(l.namespace))c.data=l.data,c.handleObj=l,g=((p.event.special[l.origType]||{}).handle||l.handler).apply(i.elem,r),g!==b&&(c.result=g,g===!1&&(c.preventDefault(),c.stopPropagation()))}}return t.postDispatch&&t.postDispatch.call(this,c),c.result},props:"attrChange attrName relatedNode srcElement altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){return a.which==null&&(a.which=b.charCode!=null?b.charCode:b.keyCode),a}},mouseHooks:{props:"button buttons clientX clientY fromElement offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,c){var d,f,g,h=c.button,i=c.fromElement;return a.pageX==null&&c.clientX!=null&&(d=a.target.ownerDocument||e,f=d.documentElement,g=d.body,a.pageX=c.clientX+(f&&f.scrollLeft||g&&g.scrollLeft||0)-(f&&f.clientLeft||g&&g.clientLeft||0),a.pageY=c.clientY+(f&&f.scrollTop||g&&g.scrollTop||0)-(f&&f.clientTop||g&&g.clientTop||0)),!a.relatedTarget&&i&&(a.relatedTarget=i===a.target?c.toElement:i),!a.which&&h!==b&&(a.which=h&1?1:h&2?3:h&4?2:0),a}},fix:function(a){if(a[p.expando])return a;var b,c,d=a,f=p.event.fixHooks[a.type]||{},g=f.props?this.props.concat(f.props):this.props;a=p.Event(d);for(b=g.length;b;)c=g[--b],a[c]=d[c];return a.target||(a.target=d.srcElement||e),a.target.nodeType===3&&(a.target=a.target.parentNode),a.metaKey=!!a.metaKey,f.filter?f.filter(a,d):a},special:{load:{noBubble:!0},focus:{delegateType:"focusin"},blur:{delegateType:"focusout"},beforeunload:{setup:function(a,b,c){p.isWindow(this)&&(this.onbeforeunload=c)},teardown:function(a,b){this.onbeforeunload===b&&(this.onbeforeunload=null)}}},simulate:function(a,b,c,d){var e=p.extend(new p.Event,c,{type:a,isSimulated:!0,originalEvent:{}});d?p.event.trigger(e,null,b):p.event.dispatch.call(b,e),e.isDefaultPrevented()&&c.preventDefault()}},p.event.handle=p.event.dispatch,p.removeEvent=e.removeEventListener?function(a,b,c){a.removeEventListener&&a.removeEventListener(b,c,!1)}:function(a,b,c){var d="on"+b;a.detachEvent&&(typeof a[d]=="undefined"&&(a[d]=null),a.detachEvent(d,c))},p.Event=function(a,b){if(this instanceof p.Event)a&&a.type?(this.originalEvent=a,this.type=a.type,this.isDefaultPrevented=a.defaultPrevented||a.returnValue===!1||a.getPreventDefault&&a.getPreventDefault()?bb:ba):this.type=a,b&&p.extend(this,b),this.timeStamp=a&&a.timeStamp||p.now(),this[p.expando]=!0;else return new p.Event(a,b)},p.Event.prototype={preventDefault:function(){this.isDefaultPrevented=bb;var a=this.originalEvent;if(!a)return;a.preventDefault?a.preventDefault():a.returnValue=!1},stopPropagation:function(){this.isPropagationStopped=bb;var a=this.originalEvent;if(!a)return;a.stopPropagation&&a.stopPropagation(),a.cancelBubble=!0},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=bb,this.stopPropagation()},isDefaultPrevented:ba,isPropagationStopped:ba,isImmediatePropagationStopped:ba},p.each({mouseenter:"mouseover",mouseleave:"mouseout"},function(a,b){p.event.special[a]={delegateType:b,bindType:b,handle:function(a){var c,d=this,e=a.relatedTarget,f=a.handleObj,g=f.selector;if(!e||e!==d&&!p.contains(d,e))a.type=f.origType,c=f.handler.apply(this,arguments),a.type=b;return c}}}),p.support.submitBubbles||(p.event.special.submit={setup:function(){if(p.nodeName(this,"form"))return!1;p.event.add(this,"click._submit keypress._submit",function(a){var c=a.target,d=p.nodeName(c,"input")||p.nodeName(c,"button")?c.form:b;d&&!p._data(d,"_submit_attached")&&(p.event.add(d,"submit._submit",function(a){a._submit_bubble=!0}),p._data(d,"_submit_attached",!0))})},postDispatch:function(a){a._submit_bubble&&(delete a._submit_bubble,this.parentNode&&!a.isTrigger&&p.event.simulate("submit",this.parentNode,a,!0))},teardown:function(){if(p.nodeName(this,"form"))return!1;p.event.remove(this,"._submit")}}),p.support.changeBubbles||(p.event.special.change={setup:function(){if(V.test(this.nodeName)){if(this.type==="checkbox"||this.type==="radio")p.event.add(this,"propertychange._change",function(a){a.originalEvent.propertyName==="checked"&&(this._just_changed=!0)}),p.event.add(this,"click._change",function(a){this._just_changed&&!a.isTrigger&&(this._just_changed=!1),p.event.simulate("change",this,a,!0)});return!1}p.event.add(this,"beforeactivate._change",function(a){var b=a.target;V.test(b.nodeName)&&!p._data(b,"_change_attached")&&(p.event.add(b,"change._change",function(a){this.parentNode&&!a.isSimulated&&!a.isTrigger&&p.event.simulate("change",this.parentNode,a,!0)}),p._data(b,"_change_attached",!0))})},handle:function(a){var b=a.target;if(this!==b||a.isSimulated||a.isTrigger||b.type!=="radio"&&b.type!=="checkbox")return a.handleObj.handler.apply(this,arguments)},teardown:function(){return p.event.remove(this,"._change"),!V.test(this.nodeName)}}),p.support.focusinBubbles||p.each({focus:"focusin",blur:"focusout"},function(a,b){var c=0,d=function(a){p.event.simulate(b,a.target,p.event.fix(a),!0)};p.event.special[b]={setup:function(){c++===0&&e.addEventListener(a,d,!0)},teardown:function(){--c===0&&e.removeEventListener(a,d,!0)}}}),p.fn.extend({on:function(a,c,d,e,f){var g,h;if(typeof a=="object"){typeof c!="string"&&(d=d||c,c=b);for(h in a)this.on(h,c,d,a[h],f);return this}d==null&&e==null?(e=c,d=c=b):e==null&&(typeof c=="string"?(e=d,d=b):(e=d,d=c,c=b));if(e===!1)e=ba;else if(!e)return this;return f===1&&(g=e,e=function(a){return p().off(a),g.apply(this,arguments)},e.guid=g.guid||(g.guid=p.guid++)),this.each(function(){p.event.add(this,a,e,d,c)})},one:function(a,b,c,d){return this.on(a,b,c,d,1)},off:function(a,c,d){var e,f;if(a&&a.preventDefault&&a.handleObj)return e=a.handleObj,p(a.delegateTarget).off(e.namespace?e.origType+"."+e.namespace:e.origType,e.selector,e.handler),this;if(typeof a=="object"){for(f in a)this.off(f,c,a[f]);return this}if(c===!1||typeof c=="function")d=c,c=b;return d===!1&&(d=ba),this.each(function(){p.event.remove(this,a,d,c)})},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},live:function(a,b,c){return p(this.context).on(a,this.selector,b,c),this},die:function(a,b){return p(this.context).off(a,this.selector||"**",b),this},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return arguments.length===1?this.off(a,"**"):this.off(b,a||"**",c)},trigger:function(a,b){return this.each(function(){p.event.trigger(a,b,this)})},triggerHandler:function(a,b){if(this[0])return p.event.trigger(a,b,this[0],!0)},toggle:function(a){var b=arguments,c=a.guid||p.guid++,d=0,e=function(c){var e=(p._data(this,"lastToggle"+a.guid)||0)%d;return p._data(this,"lastToggle"+a.guid,e+1),c.preventDefault(),b[e].apply(this,arguments)||!1};e.guid=c;while(d<b.length)b[d++].guid=c;return this.click(e)},hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)}}),p.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "),function(a,b){p.fn[b]=function(a,c){return c==null&&(c=a,a=null),arguments.length>0?this.on(b,null,a,c):this.trigger(b)},Y.test(b)&&(p.event.fixHooks[b]=p.event.keyHooks),Z.test(b)&&(p.event.fixHooks[b]=p.event.mouseHooks)}),function(a,b){function bc(a,b,c,d){c=c||[],b=b||r;var e,f,i,j,k=b.nodeType;if(!a||typeof a!="string")return c;if(k!==1&&k!==9)return[];i=g(b);if(!i&&!d)if(e=P.exec(a))if(j=e[1]){if(k===9){f=b.getElementById(j);if(!f||!f.parentNode)return c;if(f.id===j)return c.push(f),c}else if(b.ownerDocument&&(f=b.ownerDocument.getElementById(j))&&h(b,f)&&f.id===j)return c.push(f),c}else{if(e[2])return w.apply(c,x.call(b.getElementsByTagName(a),0)),c;if((j=e[3])&&_&&b.getElementsByClassName)return w.apply(c,x.call(b.getElementsByClassName(j),0)),c}return bp(a.replace(L,"$1"),b,c,d,i)}function bd(a){return function(b){var c=b.nodeName.toLowerCase();return c==="input"&&b.type===a}}function be(a){return function(b){var c=b.nodeName.toLowerCase();return(c==="input"||c==="button")&&b.type===a}}function bf(a){return z(function(b){return b=+b,z(function(c,d){var e,f=a([],c.length,b),g=f.length;while(g--)c[e=f[g]]&&(c[e]=!(d[e]=c[e]))})})}function bg(a,b,c){if(a===b)return c;var d=a.nextSibling;while(d){if(d===b)return-1;d=d.nextSibling}return 1}function bh(a,b){var c,d,f,g,h,i,j,k=C[o][a];if(k)return b?0:k.slice(0);h=a,i=[],j=e.preFilter;while(h){if(!c||(d=M.exec(h)))d&&(h=h.slice(d[0].length)),i.push(f=[]);c=!1;if(d=N.exec(h))f.push(c=new q(d.shift())),h=h.slice(c.length),c.type=d[0].replace(L," ");for(g in e.filter)(d=W[g].exec(h))&&(!j[g]||(d=j[g](d,r,!0)))&&(f.push(c=new q(d.shift())),h=h.slice(c.length),c.type=g,c.matches=d);if(!c)break}return b?h.length:h?bc.error(a):C(a,i).slice(0)}function bi(a,b,d){var e=b.dir,f=d&&b.dir==="parentNode",g=u++;return b.first?function(b,c,d){while(b=b[e])if(f||b.nodeType===1)return a(b,c,d)}:function(b,d,h){if(!h){var i,j=t+" "+g+" ",k=j+c;while(b=b[e])if(f||b.nodeType===1){if((i=b[o])===k)return b.sizset;if(typeof i=="string"&&i.indexOf(j)===0){if(b.sizset)return b}else{b[o]=k;if(a(b,d,h))return b.sizset=!0,b;b.sizset=!1}}}else while(b=b[e])if(f||b.nodeType===1)if(a(b,d,h))return b}}function bj(a){return a.length>1?function(b,c,d){var e=a.length;while(e--)if(!a[e](b,c,d))return!1;return!0}:a[0]}function bk(a,b,c,d,e){var f,g=[],h=0,i=a.length,j=b!=null;for(;h<i;h++)if(f=a[h])if(!c||c(f,d,e))g.push(f),j&&b.push(h);return g}function bl(a,b,c,d,e,f){return d&&!d[o]&&(d=bl(d)),e&&!e[o]&&(e=bl(e,f)),z(function(f,g,h,i){if(f&&e)return;var j,k,l,m=[],n=[],o=g.length,p=f||bo(b||"*",h.nodeType?[h]:h,[],f),q=a&&(f||!b)?bk(p,m,a,h,i):p,r=c?e||(f?a:o||d)?[]:g:q;c&&c(q,r,h,i);if(d){l=bk(r,n),d(l,[],h,i),j=l.length;while(j--)if(k=l[j])r[n[j]]=!(q[n[j]]=k)}if(f){j=a&&r.length;while(j--)if(k=r[j])f[m[j]]=!(g[m[j]]=k)}else r=bk(r===g?r.splice(o,r.length):r),e?e(null,g,r,i):w.apply(g,r)})}function bm(a){var b,c,d,f=a.length,g=e.relative[a[0].type],h=g||e.relative[" "],i=g?1:0,j=bi(function(a){return a===b},h,!0),k=bi(function(a){return y.call(b,a)>-1},h,!0),m=[function(a,c,d){return!g&&(d||c!==l)||((b=c).nodeType?j(a,c,d):k(a,c,d))}];for(;i<f;i++)if(c=e.relative[a[i].type])m=[bi(bj(m),c)];else{c=e.filter[a[i].type].apply(null,a[i].matches);if(c[o]){d=++i;for(;d<f;d++)if(e.relative[a[d].type])break;return bl(i>1&&bj(m),i>1&&a.slice(0,i-1).join("").replace(L,"$1"),c,i<d&&bm(a.slice(i,d)),d<f&&bm(a=a.slice(d)),d<f&&a.join(""))}m.push(c)}return bj(m)}function bn(a,b){var d=b.length>0,f=a.length>0,g=function(h,i,j,k,m){var n,o,p,q=[],s=0,u="0",x=h&&[],y=m!=null,z=l,A=h||f&&e.find.TAG("*",m&&i.parentNode||i),B=t+=z==null?1:Math.E;y&&(l=i!==r&&i,c=g.el);for(;(n=A[u])!=null;u++){if(f&&n){for(o=0;p=a[o];o++)if(p(n,i,j)){k.push(n);break}y&&(t=B,c=++g.el)}d&&((n=!p&&n)&&s--,h&&x.push(n))}s+=u;if(d&&u!==s){for(o=0;p=b[o];o++)p(x,q,i,j);if(h){if(s>0)while(u--)!x[u]&&!q[u]&&(q[u]=v.call(k));q=bk(q)}w.apply(k,q),y&&!h&&q.length>0&&s+b.length>1&&bc.uniqueSort(k)}return y&&(t=B,l=z),x};return g.el=0,d?z(g):g}function bo(a,b,c,d){var e=0,f=b.length;for(;e<f;e++)bc(a,b[e],c,d);return c}function bp(a,b,c,d,f){var g,h,j,k,l,m=bh(a),n=m.length;if(!d&&m.length===1){h=m[0]=m[0].slice(0);if(h.length>2&&(j=h[0]).type==="ID"&&b.nodeType===9&&!f&&e.relative[h[1].type]){b=e.find.ID(j.matches[0].replace(V,""),b,f)[0];if(!b)return c;a=a.slice(h.shift().length)}for(g=W.POS.test(a)?-1:h.length-1;g>=0;g--){j=h[g];if(e.relative[k=j.type])break;if(l=e.find[k])if(d=l(j.matches[0].replace(V,""),R.test(h[0].type)&&b.parentNode||b,f)){h.splice(g,1),a=d.length&&h.join("");if(!a)return w.apply(c,x.call(d,0)),c;break}}}return i(a,m)(d,b,f,c,R.test(a)),c}function bq(){}var c,d,e,f,g,h,i,j,k,l,m=!0,n="undefined",o=("sizcache"+Math.random()).replace(".",""),q=String,r=a.document,s=r.documentElement,t=0,u=0,v=[].pop,w=[].push,x=[].slice,y=[].indexOf||function(a){var b=0,c=this.length;for(;b<c;b++)if(this[b]===a)return b;return-1},z=function(a,b){return a[o]=b==null||b,a},A=function(){var a={},b=[];return z(function(c,d){return b.push(c)>e.cacheLength&&delete a[b.shift()],a[c]=d},a)},B=A(),C=A(),D=A(),E="[\\x20\\t\\r\\n\\f]",F="(?:\\\\.|[-\\w]|[^\\x00-\\xa0])+",G=F.replace("w","w#"),H="([*^$|!~]?=)",I="\\["+E+"*("+F+")"+E+"*(?:"+H+E+"*(?:(['\"])((?:\\\\.|[^\\\\])*?)\\3|("+G+")|)|)"+E+"*\\]",J=":("+F+")(?:\\((?:(['\"])((?:\\\\.|[^\\\\])*?)\\2|([^()[\\]]*|(?:(?:"+I+")|[^:]|\\\\.)*|.*))\\)|)",K=":(even|odd|eq|gt|lt|nth|first|last)(?:\\("+E+"*((?:-\\d)?\\d*)"+E+"*\\)|)(?=[^-]|$)",L=new RegExp("^"+E+"+|((?:^|[^\\\\])(?:\\\\.)*)"+E+"+$","g"),M=new RegExp("^"+E+"*,"+E+"*"),N=new RegExp("^"+E+"*([\\x20\\t\\r\\n\\f>+~])"+E+"*"),O=new RegExp(J),P=/^(?:#([\w\-]+)|(\w+)|\.([\w\-]+))$/,Q=/^:not/,R=/[\x20\t\r\n\f]*[+~]/,S=/:not\($/,T=/h\d/i,U=/input|select|textarea|button/i,V=/\\(?!\\)/g,W={ID:new RegExp("^#("+F+")"),CLASS:new RegExp("^\\.("+F+")"),NAME:new RegExp("^\\[name=['\"]?("+F+")['\"]?\\]"),TAG:new RegExp("^("+F.replace("w","w*")+")"),ATTR:new RegExp("^"+I),PSEUDO:new RegExp("^"+J),POS:new RegExp(K,"i"),CHILD:new RegExp("^:(only|nth|first|last)-child(?:\\("+E+"*(even|odd|(([+-]|)(\\d*)n|)"+E+"*(?:([+-]|)"+E+"*(\\d+)|))"+E+"*\\)|)","i"),needsContext:new RegExp("^"+E+"*[>+~]|"+K,"i")},X=function(a){var b=r.createElement("div");try{return a(b)}catch(c){return!1}finally{b=null}},Y=X(function(a){return a.appendChild(r.createComment("")),!a.getElementsByTagName("*").length}),Z=X(function(a){return a.innerHTML="<a href='#'></a>",a.firstChild&&typeof a.firstChild.getAttribute!==n&&a.firstChild.getAttribute("href")==="#"}),$=X(function(a){a.innerHTML="<select></select>";var b=typeof a.lastChild.getAttribute("multiple");return b!=="boolean"&&b!=="string"}),_=X(function(a){return a.innerHTML="<div class='hidden e'></div><div class='hidden'></div>",!a.getElementsByClassName||!a.getElementsByClassName("e").length?!1:(a.lastChild.className="e",a.getElementsByClassName("e").length===2)}),ba=X(function(a){a.id=o+0,a.innerHTML="<a name='"+o+"'></a><div name='"+o+"'></div>",s.insertBefore(a,s.firstChild);var b=r.getElementsByName&&r.getElementsByName(o).length===2+r.getElementsByName(o+0).length;return d=!r.getElementById(o),s.removeChild(a),b});try{x.call(s.childNodes,0)[0].nodeType}catch(bb){x=function(a){var b,c=[];for(;b=this[a];a++)c.push(b);return c}}bc.matches=function(a,b){return bc(a,null,null,b)},bc.matchesSelector=function(a,b){return bc(b,null,null,[a]).length>0},f=bc.getText=function(a){var b,c="",d=0,e=a.nodeType;if(e){if(e===1||e===9||e===11){if(typeof a.textContent=="string")return a.textContent;for(a=a.firstChild;a;a=a.nextSibling)c+=f(a)}else if(e===3||e===4)return a.nodeValue}else for(;b=a[d];d++)c+=f(b);return c},g=bc.isXML=function(a){var b=a&&(a.ownerDocument||a).documentElement;return b?b.nodeName!=="HTML":!1},h=bc.contains=s.contains?function(a,b){var c=a.nodeType===9?a.documentElement:a,d=b&&b.parentNode;return a===d||!!(d&&d.nodeType===1&&c.contains&&c.contains(d))}:s.compareDocumentPosition?function(a,b){return b&&!!(a.compareDocumentPosition(b)&16)}:function(a,b){while(b=b.parentNode)if(b===a)return!0;return!1},bc.attr=function(a,b){var c,d=g(a);return d||(b=b.toLowerCase()),(c=e.attrHandle[b])?c(a):d||$?a.getAttribute(b):(c=a.getAttributeNode(b),c?typeof a[b]=="boolean"?a[b]?b:null:c.specified?c.value:null:null)},e=bc.selectors={cacheLength:50,createPseudo:z,match:W,attrHandle:Z?{}:{href:function(a){return a.getAttribute("href",2)},type:function(a){return a.getAttribute("type")}},find:{ID:d?function(a,b,c){if(typeof b.getElementById!==n&&!c){var d=b.getElementById(a);return d&&d.parentNode?[d]:[]}}:function(a,c,d){if(typeof c.getElementById!==n&&!d){var e=c.getElementById(a);return e?e.id===a||typeof e.getAttributeNode!==n&&e.getAttributeNode("id").value===a?[e]:b:[]}},TAG:Y?function(a,b){if(typeof b.getElementsByTagName!==n)return b.getElementsByTagName(a)}:function(a,b){var c=b.getElementsByTagName(a);if(a==="*"){var d,e=[],f=0;for(;d=c[f];f++)d.nodeType===1&&e.push(d);return e}return c},NAME:ba&&function(a,b){if(typeof b.getElementsByName!==n)return b.getElementsByName(name)},CLASS:_&&function(a,b,c){if(typeof b.getElementsByClassName!==n&&!c)return b.getElementsByClassName(a)}},relative:{">":{dir:"parentNode",first:!0}," ":{dir:"parentNode"},"+":{dir:"previousSibling",first:!0},"~":{dir:"previousSibling"}},preFilter:{ATTR:function(a){return a[1]=a[1].replace(V,""),a[3]=(a[4]||a[5]||"").replace(V,""),a[2]==="~="&&(a[3]=" "+a[3]+" "),a.slice(0,4)},CHILD:function(a){return a[1]=a[1].toLowerCase(),a[1]==="nth"?(a[2]||bc.error(a[0]),a[3]=+(a[3]?a[4]+(a[5]||1):2*(a[2]==="even"||a[2]==="odd")),a[4]=+(a[6]+a[7]||a[2]==="odd")):a[2]&&bc.error(a[0]),a},PSEUDO:function(a){var b,c;if(W.CHILD.test(a[0]))return null;if(a[3])a[2]=a[3];else if(b=a[4])O.test(b)&&(c=bh(b,!0))&&(c=b.indexOf(")",b.length-c)-b.length)&&(b=b.slice(0,c),a[0]=a[0].slice(0,c)),a[2]=b;return a.slice(0,3)}},filter:{ID:d?function(a){return a=a.replace(V,""),function(b){return b.getAttribute("id")===a}}:function(a){return a=a.replace(V,""),function(b){var c=typeof b.getAttributeNode!==n&&b.getAttributeNode("id");return c&&c.value===a}},TAG:function(a){return a==="*"?function(){return!0}:(a=a.replace(V,"").toLowerCase(),function(b){return b.nodeName&&b.nodeName.toLowerCase()===a})},CLASS:function(a){var b=B[o][a];return b||(b=B(a,new RegExp("(^|"+E+")"+a+"("+E+"|$)"))),function(a){return b.test(a.className||typeof a.getAttribute!==n&&a.getAttribute("class")||"")}},ATTR:function(a,b,c){return function(d,e){var f=bc.attr(d,a);return f==null?b==="!=":b?(f+="",b==="="?f===c:b==="!="?f!==c:b==="^="?c&&f.indexOf(c)===0:b==="*="?c&&f.indexOf(c)>-1:b==="$="?c&&f.substr(f.length-c.length)===c:b==="~="?(" "+f+" ").indexOf(c)>-1:b==="|="?f===c||f.substr(0,c.length+1)===c+"-":!1):!0}},CHILD:function(a,b,c,d){return a==="nth"?function(a){var b,e,f=a.parentNode;if(c===1&&d===0)return!0;if(f){e=0;for(b=f.firstChild;b;b=b.nextSibling)if(b.nodeType===1){e++;if(a===b)break}}return e-=d,e===c||e%c===0&&e/c>=0}:function(b){var c=b;switch(a){case"only":case"first":while(c=c.previousSibling)if(c.nodeType===1)return!1;if(a==="first")return!0;c=b;case"last":while(c=c.nextSibling)if(c.nodeType===1)return!1;return!0}}},PSEUDO:function(a,b){var c,d=e.pseudos[a]||e.setFilters[a.toLowerCase()]||bc.error("unsupported pseudo: "+a);return d[o]?d(b):d.length>1?(c=[a,a,"",b],e.setFilters.hasOwnProperty(a.toLowerCase())?z(function(a,c){var e,f=d(a,b),g=f.length;while(g--)e=y.call(a,f[g]),a[e]=!(c[e]=f[g])}):function(a){return d(a,0,c)}):d}},pseudos:{not:z(function(a){var b=[],c=[],d=i(a.replace(L,"$1"));return d[o]?z(function(a,b,c,e){var f,g=d(a,null,e,[]),h=a.length;while(h--)if(f=g[h])a[h]=!(b[h]=f)}):function(a,e,f){return b[0]=a,d(b,null,f,c),!c.pop()}}),has:z(function(a){return function(b){return bc(a,b).length>0}}),contains:z(function(a){return function(b){return(b.textContent||b.innerText||f(b)).indexOf(a)>-1}}),enabled:function(a){return a.disabled===!1},disabled:function(a){return a.disabled===!0},checked:function(a){var b=a.nodeName.toLowerCase();return b==="input"&&!!a.checked||b==="option"&&!!a.selected},selected:function(a){return a.parentNode&&a.parentNode.selectedIndex,a.selected===!0},parent:function(a){return!e.pseudos.empty(a)},empty:function(a){var b;a=a.firstChild;while(a){if(a.nodeName>"@"||(b=a.nodeType)===3||b===4)return!1;a=a.nextSibling}return!0},header:function(a){return T.test(a.nodeName)},text:function(a){var b,c;return a.nodeName.toLowerCase()==="input"&&(b=a.type)==="text"&&((c=a.getAttribute("type"))==null||c.toLowerCase()===b)},radio:bd("radio"),checkbox:bd("checkbox"),file:bd("file"),password:bd("password"),image:bd("image"),submit:be("submit"),reset:be("reset"),button:function(a){var b=a.nodeName.toLowerCase();return b==="input"&&a.type==="button"||b==="button"},input:function(a){return U.test(a.nodeName)},focus:function(a){var b=a.ownerDocument;return a===b.activeElement&&(!b.hasFocus||b.hasFocus())&&(!!a.type||!!a.href)},active:function(a){return a===a.ownerDocument.activeElement},first:bf(function(a,b,c){return[0]}),last:bf(function(a,b,c){return[b-1]}),eq:bf(function(a,b,c){return[c<0?c+b:c]}),even:bf(function(a,b,c){for(var d=0;d<b;d+=2)a.push(d);return a}),odd:bf(function(a,b,c){for(var d=1;d<b;d+=2)a.push(d);return a}),lt:bf(function(a,b,c){for(var d=c<0?c+b:c;--d>=0;)a.push(d);return a}),gt:bf(function(a,b,c){for(var d=c<0?c+b:c;++d<b;)a.push(d);return a})}},j=s.compareDocumentPosition?function(a,b){return a===b?(k=!0,0):(!a.compareDocumentPosition||!b.compareDocumentPosition?a.compareDocumentPosition:a.compareDocumentPosition(b)&4)?-1:1}:function(a,b){if(a===b)return k=!0,0;if(a.sourceIndex&&b.sourceIndex)return a.sourceIndex-b.sourceIndex;var c,d,e=[],f=[],g=a.parentNode,h=b.parentNode,i=g;if(g===h)return bg(a,b);if(!g)return-1;if(!h)return 1;while(i)e.unshift(i),i=i.parentNode;i=h;while(i)f.unshift(i),i=i.parentNode;c=e.length,d=f.length;for(var j=0;j<c&&j<d;j++)if(e[j]!==f[j])return bg(e[j],f[j]);return j===c?bg(a,f[j],-1):bg(e[j],b,1)},[0,0].sort(j),m=!k,bc.uniqueSort=function(a){var b,c=1;k=m,a.sort(j);if(k)for(;b=a[c];c++)b===a[c-1]&&a.splice(c--,1);return a},bc.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a)},i=bc.compile=function(a,b){var c,d=[],e=[],f=D[o][a];if(!f){b||(b=bh(a)),c=b.length;while(c--)f=bm(b[c]),f[o]?d.push(f):e.push(f);f=D(a,bn(e,d))}return f},r.querySelectorAll&&function(){var a,b=bp,c=/'|\\/g,d=/\=[\x20\t\r\n\f]*([^'"\]]*)[\x20\t\r\n\f]*\]/g,e=[":focus"],f=[":active",":focus"],h=s.matchesSelector||s.mozMatchesSelector||s.webkitMatchesSelector||s.oMatchesSelector||s.msMatchesSelector;X(function(a){a.innerHTML="<select><option selected=''></option></select>",a.querySelectorAll("[selected]").length||e.push("\\["+E+"*(?:checked|disabled|ismap|multiple|readonly|selected|value)"),a.querySelectorAll(":checked").length||e.push(":checked")}),X(function(a){a.innerHTML="<p test=''></p>",a.querySelectorAll("[test^='']").length&&e.push("[*^$]="+E+"*(?:\"\"|'')"),a.innerHTML="<input type='hidden'/>",a.querySelectorAll(":enabled").length||e.push(":enabled",":disabled")}),e=new RegExp(e.join("|")),bp=function(a,d,f,g,h){if(!g&&!h&&(!e||!e.test(a))){var i,j,k=!0,l=o,m=d,n=d.nodeType===9&&a;if(d.nodeType===1&&d.nodeName.toLowerCase()!=="object"){i=bh(a),(k=d.getAttribute("id"))?l=k.replace(c,"\\$&"):d.setAttribute("id",l),l="[id='"+l+"'] ",j=i.length;while(j--)i[j]=l+i[j].join("");m=R.test(a)&&d.parentNode||d,n=i.join(",")}if(n)try{return w.apply(f,x.call(m.querySelectorAll(n),0)),f}catch(p){}finally{k||d.removeAttribute("id")}}return b(a,d,f,g,h)},h&&(X(function(b){a=h.call(b,"div");try{h.call(b,"[test!='']:sizzle"),f.push("!=",J)}catch(c){}}),f=new RegExp(f.join("|")),bc.matchesSelector=function(b,c){c=c.replace(d,"='$1']");if(!g(b)&&!f.test(c)&&(!e||!e.test(c)))try{var i=h.call(b,c);if(i||a||b.document&&b.document.nodeType!==11)return i}catch(j){}return bc(c,null,null,[b]).length>0})}(),e.pseudos.nth=e.pseudos.eq,e.filters=bq.prototype=e.pseudos,e.setFilters=new bq,bc.attr=p.attr,p.find=bc,p.expr=bc.selectors,p.expr[":"]=p.expr.pseudos,p.unique=bc.uniqueSort,p.text=bc.getText,p.isXMLDoc=bc.isXML,p.contains=bc.contains}(a);var bc=/Until$/,bd=/^(?:parents|prev(?:Until|All))/,be=/^.[^:#\[\.,]*$/,bf=p.expr.match.needsContext,bg={children:!0,contents:!0,next:!0,prev:!0};p.fn.extend({find:function(a){var b,c,d,e,f,g,h=this;if(typeof a!="string")return p(a).filter(function(){for(b=0,c=h.length;b<c;b++)if(p.contains(h[b],this))return!0});g=this.pushStack("","find",a);for(b=0,c=this.length;b<c;b++){d=g.length,p.find(a,this[b],g);if(b>0)for(e=d;e<g.length;e++)for(f=0;f<d;f++)if(g[f]===g[e]){g.splice(e--,1);break}}return g},has:function(a){var b,c=p(a,this),d=c.length;return this.filter(function(){for(b=0;b<d;b++)if(p.contains(this,c[b]))return!0})},not:function(a){return this.pushStack(bj(this,a,!1),"not",a)},filter:function(a){return this.pushStack(bj(this,a,!0),"filter",a)},is:function(a){return!!a&&(typeof a=="string"?bf.test(a)?p(a,this.context).index(this[0])>=0:p.filter(a,this).length>0:this.filter(a).length>0)},closest:function(a,b){var c,d=0,e=this.length,f=[],g=bf.test(a)||typeof a!="string"?p(a,b||this.context):0;for(;d<e;d++){c=this[d];while(c&&c.ownerDocument&&c!==b&&c.nodeType!==11){if(g?g.index(c)>-1:p.find.matchesSelector(c,a)){f.push(c);break}c=c.parentNode}}return f=f.length>1?p.unique(f):f,this.pushStack(f,"closest",a)},index:function(a){return a?typeof a=="string"?p.inArray(this[0],p(a)):p.inArray(a.jquery?a[0]:a,this):this[0]&&this[0].parentNode?this.prevAll().length:-1},add:function(a,b){var c=typeof a=="string"?p(a,b):p.makeArray(a&&a.nodeType?[a]:a),d=p.merge(this.get(),c);return this.pushStack(bh(c[0])||bh(d[0])?d:p.unique(d))},addBack:function(a){return this.add(a==null?this.prevObject:this.prevObject.filter(a))}}),p.fn.andSelf=p.fn.addBack,p.each({parent:function(a){var b=a.parentNode;return b&&b.nodeType!==11?b:null},parents:function(a){return p.dir(a,"parentNode")},parentsUntil:function(a,b,c){return p.dir(a,"parentNode",c)},next:function(a){return bi(a,"nextSibling")},prev:function(a){return bi(a,"previousSibling")},nextAll:function(a){return p.dir(a,"nextSibling")},prevAll:function(a){return p.dir(a,"previousSibling")},nextUntil:function(a,b,c){return p.dir(a,"nextSibling",c)},prevUntil:function(a,b,c){return p.dir(a,"previousSibling",c)},siblings:function(a){return p.sibling((a.parentNode||{}).firstChild,a)},children:function(a){return p.sibling(a.firstChild)},contents:function(a){return p.nodeName(a,"iframe")?a.contentDocument||a.contentWindow.document:p.merge([],a.childNodes)}},function(a,b){p.fn[a]=function(c,d){var e=p.map(this,b,c);return bc.test(a)||(d=c),d&&typeof d=="string"&&(e=p.filter(d,e)),e=this.length>1&&!bg[a]?p.unique(e):e,this.length>1&&bd.test(a)&&(e=e.reverse()),this.pushStack(e,a,k.call(arguments).join(","))}}),p.extend({filter:function(a,b,c){return c&&(a=":not("+a+")"),b.length===1?p.find.matchesSelector(b[0],a)?[b[0]]:[]:p.find.matches(a,b)},dir:function(a,c,d){var e=[],f=a[c];while(f&&f.nodeType!==9&&(d===b||f.nodeType!==1||!p(f).is(d)))f.nodeType===1&&e.push(f),f=f[c];return e},sibling:function(a,b){var c=[];for(;a;a=a.nextSibling)a.nodeType===1&&a!==b&&c.push(a);return c}});var bl="abbr|article|aside|audio|bdi|canvas|data|datalist|details|figcaption|figure|footer|header|hgroup|mark|meter|nav|output|progress|section|summary|time|video",bm=/ jQuery\d+="(?:null|\d+)"/g,bn=/^\s+/,bo=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\w:]+)[^>]*)\/>/gi,bp=/<([\w:]+)/,bq=/<tbody/i,br=/<|&#?\w+;/,bs=/<(?:script|style|link)/i,bt=/<(?:script|object|embed|option|style)/i,bu=new RegExp("<(?:"+bl+")[\\s/>]","i"),bv=/^(?:checkbox|radio)$/,bw=/checked\s*(?:[^=]|=\s*.checked.)/i,bx=/\/(java|ecma)script/i,by=/^\s*<!(?:\[CDATA\[|\-\-)|[\]\-]{2}>\s*$/g,bz={option:[1,"<select multiple='multiple'>","</select>"],legend:[1,"<fieldset>","</fieldset>"],thead:[1,"<table>","</table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],col:[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"],area:[1,"<map>","</map>"],_default:[0,"",""]},bA=bk(e),bB=bA.appendChild(e.createElement("div"));bz.optgroup=bz.option,bz.tbody=bz.tfoot=bz.colgroup=bz.caption=bz.thead,bz.th=bz.td,p.support.htmlSerialize||(bz._default=[1,"X<div>","</div>"]),p.fn.extend({text:function(a){return p.access(this,function(a){return a===b?p.text(this):this.empty().append((this[0]&&this[0].ownerDocument||e).createTextNode(a))},null,a,arguments.length)},wrapAll:function(a){if(p.isFunction(a))return this.each(function(b){p(this).wrapAll(a.call(this,b))});if(this[0]){var b=p(a,this[0].ownerDocument).eq(0).clone(!0);this[0].parentNode&&b.insertBefore(this[0]),b.map(function(){var a=this;while(a.firstChild&&a.firstChild.nodeType===1)a=a.firstChild;return a}).append(this)}return this},wrapInner:function(a){return p.isFunction(a)?this.each(function(b){p(this).wrapInner(a.call(this,b))}):this.each(function(){var b=p(this),c=b.contents();c.length?c.wrapAll(a):b.append(a)})},wrap:function(a){var b=p.isFunction(a);return this.each(function(c){p(this).wrapAll(b?a.call(this,c):a)})},unwrap:function(){return this.parent().each(function(){p.nodeName(this,"body")||p(this).replaceWith(this.childNodes)}).end()},append:function(){return this.domManip(arguments,!0,function(a){(this.nodeType===1||this.nodeType===11)&&this.appendChild(a)})},prepend:function(){return this.domManip(arguments,!0,function(a){(this.nodeType===1||this.nodeType===11)&&this.insertBefore(a,this.firstChild)})},before:function(){if(!bh(this[0]))return this.domManip(arguments,!1,function(a){this.parentNode.insertBefore(a,this)});if(arguments.length){var a=p.clean(arguments);return this.pushStack(p.merge(a,this),"before",this.selector)}},after:function(){if(!bh(this[0]))return this.domManip(arguments,!1,function(a){this.parentNode.insertBefore(a,this.nextSibling)});if(arguments.length){var a=p.clean(arguments);return this.pushStack(p.merge(this,a),"after",this.selector)}},remove:function(a,b){var c,d=0;for(;(c=this[d])!=null;d++)if(!a||p.filter(a,[c]).length)!b&&c.nodeType===1&&(p.cleanData(c.getElementsByTagName("*")),p.cleanData([c])),c.parentNode&&c.parentNode.removeChild(c);return this},empty:function(){var a,b=0;for(;(a=this[b])!=null;b++){a.nodeType===1&&p.cleanData(a.getElementsByTagName("*"));while(a.firstChild)a.removeChild(a.firstChild)}return this},clone:function(a,b){return a=a==null?!1:a,b=b==null?a:b,this.map(function(){return p.clone(this,a,b)})},html:function(a){return p.access(this,function(a){var c=this[0]||{},d=0,e=this.length;if(a===b)return c.nodeType===1?c.innerHTML.replace(bm,""):b;if(typeof a=="string"&&!bs.test(a)&&(p.support.htmlSerialize||!bu.test(a))&&(p.support.leadingWhitespace||!bn.test(a))&&!bz[(bp.exec(a)||["",""])[1].toLowerCase()]){a=a.replace(bo,"<$1></$2>");try{for(;d<e;d++)c=this[d]||{},c.nodeType===1&&(p.cleanData(c.getElementsByTagName("*")),c.innerHTML=a);c=0}catch(f){}}c&&this.empty().append(a)},null,a,arguments.length)},replaceWith:function(a){return bh(this[0])?this.length?this.pushStack(p(p.isFunction(a)?a():a),"replaceWith",a):this:p.isFunction(a)?this.each(function(b){var c=p(this),d=c.html();c.replaceWith(a.call(this,b,d))}):(typeof a!="string"&&(a=p(a).detach()),this.each(function(){var b=this.nextSibling,c=this.parentNode;p(this).remove(),b?p(b).before(a):p(c).append(a)}))},detach:function(a){return this.remove(a,!0)},domManip:function(a,c,d){a=[].concat.apply([],a);var e,f,g,h,i=0,j=a[0],k=[],l=this.length;if(!p.support.checkClone&&l>1&&typeof j=="string"&&bw.test(j))return this.each(function(){p(this).domManip(a,c,d)});if(p.isFunction(j))return this.each(function(e){var f=p(this);a[0]=j.call(this,e,c?f.html():b),f.domManip(a,c,d)});if(this[0]){e=p.buildFragment(a,this,k),g=e.fragment,f=g.firstChild,g.childNodes.length===1&&(g=f);if(f){c=c&&p.nodeName(f,"tr");for(h=e.cacheable||l-1;i<l;i++)d.call(c&&p.nodeName(this[i],"table")?bC(this[i],"tbody"):this[i],i===h?g:p.clone(g,!0,!0))}g=f=null,k.length&&p.each(k,function(a,b){b.src?p.ajax?p.ajax({url:b.src,type:"GET",dataType:"script",async:!1,global:!1,"throws":!0}):p.error("no ajax"):p.globalEval((b.text||b.textContent||b.innerHTML||"").replace(by,"")),b.parentNode&&b.parentNode.removeChild(b)})}return this}}),p.buildFragment=function(a,c,d){var f,g,h,i=a[0];return c=c||e,c=!c.nodeType&&c[0]||c,c=c.ownerDocument||c,a.length===1&&typeof i=="string"&&i.length<512&&c===e&&i.charAt(0)==="<"&&!bt.test(i)&&(p.support.checkClone||!bw.test(i))&&(p.support.html5Clone||!bu.test(i))&&(g=!0,f=p.fragments[i],h=f!==b),f||(f=c.createDocumentFragment(),p.clean(a,c,f,d),g&&(p.fragments[i]=h&&f)),{fragment:f,cacheable:g}},p.fragments={},p.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){p.fn[a]=function(c){var d,e=0,f=[],g=p(c),h=g.length,i=this.length===1&&this[0].parentNode;if((i==null||i&&i.nodeType===11&&i.childNodes.length===1)&&h===1)return g[b](this[0]),this;for(;e<h;e++)d=(e>0?this.clone(!0):this).get(),p(g[e])[b](d),f=f.concat(d);return this.pushStack(f,a,g.selector)}}),p.extend({clone:function(a,b,c){var d,e,f,g;p.support.html5Clone||p.isXMLDoc(a)||!bu.test("<"+a.nodeName+">")?g=a.cloneNode(!0):(bB.innerHTML=a.outerHTML,bB.removeChild(g=bB.firstChild));if((!p.support.noCloneEvent||!p.support.noCloneChecked)&&(a.nodeType===1||a.nodeType===11)&&!p.isXMLDoc(a)){bE(a,g),d=bF(a),e=bF(g);for(f=0;d[f];++f)e[f]&&bE(d[f],e[f])}if(b){bD(a,g);if(c){d=bF(a),e=bF(g);for(f=0;d[f];++f)bD(d[f],e[f])}}return d=e=null,g},clean:function(a,b,c,d){var f,g,h,i,j,k,l,m,n,o,q,r,s=b===e&&bA,t=[];if(!b||typeof b.createDocumentFragment=="undefined")b=e;for(f=0;(h=a[f])!=null;f++){typeof h=="number"&&(h+="");if(!h)continue;if(typeof h=="string")if(!br.test(h))h=b.createTextNode(h);else{s=s||bk(b),l=b.createElement("div"),s.appendChild(l),h=h.replace(bo,"<$1></$2>"),i=(bp.exec(h)||["",""])[1].toLowerCase(),j=bz[i]||bz._default,k=j[0],l.innerHTML=j[1]+h+j[2];while(k--)l=l.lastChild;if(!p.support.tbody){m=bq.test(h),n=i==="table"&&!m?l.firstChild&&l.firstChild.childNodes:j[1]==="<table>"&&!m?l.childNodes:[];for(g=n.length-1;g>=0;--g)p.nodeName(n[g],"tbody")&&!n[g].childNodes.length&&n[g].parentNode.removeChild(n[g])}!p.support.leadingWhitespace&&bn.test(h)&&l.insertBefore(b.createTextNode(bn.exec(h)[0]),l.firstChild),h=l.childNodes,l.parentNode.removeChild(l)}h.nodeType?t.push(h):p.merge(t,h)}l&&(h=l=s=null);if(!p.support.appendChecked)for(f=0;(h=t[f])!=null;f++)p.nodeName(h,"input")?bG(h):typeof h.getElementsByTagName!="undefined"&&p.grep(h.getElementsByTagName("input"),bG);if(c){q=function(a){if(!a.type||bx.test(a.type))return d?d.push(a.parentNode?a.parentNode.removeChild(a):a):c.appendChild(a)};for(f=0;(h=t[f])!=null;f++)if(!p.nodeName(h,"script")||!q(h))c.appendChild(h),typeof h.getElementsByTagName!="undefined"&&(r=p.grep(p.merge([],h.getElementsByTagName("script")),q),t.splice.apply(t,[f+1,0].concat(r)),f+=r.length)}return t},cleanData:function(a,b){var c,d,e,f,g=0,h=p.expando,i=p.cache,j=p.support.deleteExpando,k=p.event.special;for(;(e=a[g])!=null;g++)if(b||p.acceptData(e)){d=e[h],c=d&&i[d];if(c){if(c.events)for(f in c.events)k[f]?p.event.remove(e,f):p.removeEvent(e,f,c.handle);i[d]&&(delete i[d],j?delete e[h]:e.removeAttribute?e.removeAttribute(h):e[h]=null,p.deletedIds.push(d))}}}}),function(){var a,b;p.uaMatch=function(a){a=a.toLowerCase();var b=/(chrome)[ \/]([\w.]+)/.exec(a)||/(webkit)[ \/]([\w.]+)/.exec(a)||/(opera)(?:.*version|)[ \/]([\w.]+)/.exec(a)||/(msie) ([\w.]+)/.exec(a)||a.indexOf("compatible")<0&&/(mozilla)(?:.*? rv:([\w.]+)|)/.exec(a)||[];return{browser:b[1]||"",version:b[2]||"0"}},a=p.uaMatch(g.userAgent),b={},a.browser&&(b[a.browser]=!0,b.version=a.version),b.chrome?b.webkit=!0:b.webkit&&(b.safari=!0),p.browser=b,p.sub=function(){function a(b,c){return new a.fn.init(b,c)}p.extend(!0,a,this),a.superclass=this,a.fn=a.prototype=this(),a.fn.constructor=a,a.sub=this.sub,a.fn.init=function c(c,d){return d&&d instanceof p&&!(d instanceof a)&&(d=a(d)),p.fn.init.call(this,c,d,b)},a.fn.init.prototype=a.fn;var b=a(e);return a}}();var bH,bI,bJ,bK=/alpha\([^)]*\)/i,bL=/opacity=([^)]*)/,bM=/^(top|right|bottom|left)$/,bN=/^(none|table(?!-c[ea]).+)/,bO=/^margin/,bP=new RegExp("^("+q+")(.*)$","i"),bQ=new RegExp("^("+q+")(?!px)[a-z%]+$","i"),bR=new RegExp("^([-+])=("+q+")","i"),bS={},bT={position:"absolute",visibility:"hidden",display:"block"},bU={letterSpacing:0,fontWeight:400},bV=["Top","Right","Bottom","Left"],bW=["Webkit","O","Moz","ms"],bX=p.fn.toggle;p.fn.extend({css:function(a,c){return p.access(this,function(a,c,d){return d!==b?p.style(a,c,d):p.css(a,c)},a,c,arguments.length>1)},show:function(){return b$(this,!0)},hide:function(){return b$(this)},toggle:function(a,b){var c=typeof a=="boolean";return p.isFunction(a)&&p.isFunction(b)?bX.apply(this,arguments):this.each(function(){(c?a:bZ(this))?p(this).show():p(this).hide()})}}),p.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=bH(a,"opacity");return c===""?"1":c}}}},cssNumber:{fillOpacity:!0,fontWeight:!0,lineHeight:!0,opacity:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},cssProps:{"float":p.support.cssFloat?"cssFloat":"styleFloat"},style:function(a,c,d,e){if(!a||a.nodeType===3||a.nodeType===8||!a.style)return;var f,g,h,i=p.camelCase(c),j=a.style;c=p.cssProps[i]||(p.cssProps[i]=bY(j,i)),h=p.cssHooks[c]||p.cssHooks[i];if(d===b)return h&&"get"in h&&(f=h.get(a,!1,e))!==b?f:j[c];g=typeof d,g==="string"&&(f=bR.exec(d))&&(d=(f[1]+1)*f[2]+parseFloat(p.css(a,c)),g="number");if(d==null||g==="number"&&isNaN(d))return;g==="number"&&!p.cssNumber[i]&&(d+="px");if(!h||!("set"in h)||(d=h.set(a,d,e))!==b)try{j[c]=d}catch(k){}},css:function(a,c,d,e){var f,g,h,i=p.camelCase(c);return c=p.cssProps[i]||(p.cssProps[i]=bY(a.style,i)),h=p.cssHooks[c]||p.cssHooks[i],h&&"get"in h&&(f=h.get(a,!0,e)),f===b&&(f=bH(a,c)),f==="normal"&&c in bU&&(f=bU[c]),d||e!==b?(g=parseFloat(f),d||p.isNumeric(g)?g||0:f):f},swap:function(a,b,c){var d,e,f={};for(e in b)f[e]=a.style[e],a.style[e]=b[e];d=c.call(a);for(e in b)a.style[e]=f[e];return d}}),a.getComputedStyle?bH=function(b,c){var d,e,f,g,h=a.getComputedStyle(b,null),i=b.style;return h&&(d=h[c],d===""&&!p.contains(b.ownerDocument,b)&&(d=p.style(b,c)),bQ.test(d)&&bO.test(c)&&(e=i.width,f=i.minWidth,g=i.maxWidth,i.minWidth=i.maxWidth=i.width=d,d=h.width,i.width=e,i.minWidth=f,i.maxWidth=g)),d}:e.documentElement.currentStyle&&(bH=function(a,b){var c,d,e=a.currentStyle&&a.currentStyle[b],f=a.style;return e==null&&f&&f[b]&&(e=f[b]),bQ.test(e)&&!bM.test(b)&&(c=f.left,d=a.runtimeStyle&&a.runtimeStyle.left,d&&(a.runtimeStyle.left=a.currentStyle.left),f.left=b==="fontSize"?"1em":e,e=f.pixelLeft+"px",f.left=c,d&&(a.runtimeStyle.left=d)),e===""?"auto":e}),p.each(["height","width"],function(a,b){p.cssHooks[b]={get:function(a,c,d){if(c)return a.offsetWidth===0&&bN.test(bH(a,"display"))?p.swap(a,bT,function(){return cb(a,b,d)}):cb(a,b,d)},set:function(a,c,d){return b_(a,c,d?ca(a,b,d,p.support.boxSizing&&p.css(a,"boxSizing")==="border-box"):0)}}}),p.support.opacity||(p.cssHooks.opacity={get:function(a,b){return bL.test((b&&a.currentStyle?a.currentStyle.filter:a.style.filter)||"")?.01*parseFloat(RegExp.$1)+"":b?"1":""},set:function(a,b){var c=a.style,d=a.currentStyle,e=p.isNumeric(b)?"alpha(opacity="+b*100+")":"",f=d&&d.filter||c.filter||"";c.zoom=1;if(b>=1&&p.trim(f.replace(bK,""))===""&&c.removeAttribute){c.removeAttribute("filter");if(d&&!d.filter)return}c.filter=bK.test(f)?f.replace(bK,e):f+" "+e}}),p(function(){p.support.reliableMarginRight||(p.cssHooks.marginRight={get:function(a,b){return p.swap(a,{display:"inline-block"},function(){if(b)return bH(a,"marginRight")})}}),!p.support.pixelPosition&&p.fn.position&&p.each(["top","left"],function(a,b){p.cssHooks[b]={get:function(a,c){if(c){var d=bH(a,b);return bQ.test(d)?p(a).position()[b]+"px":d}}}})}),p.expr&&p.expr.filters&&(p.expr.filters.hidden=function(a){return a.offsetWidth===0&&a.offsetHeight===0||!p.support.reliableHiddenOffsets&&(a.style&&a.style.display||bH(a,"display"))==="none"},p.expr.filters.visible=function(a){return!p.expr.filters.hidden(a)}),p.each({margin:"",padding:"",border:"Width"},function(a,b){p.cssHooks[a+b]={expand:function(c){var d,e=typeof c=="string"?c.split(" "):[c],f={};for(d=0;d<4;d++)f[a+bV[d]+b]=e[d]||e[d-2]||e[0];return f}},bO.test(a)||(p.cssHooks[a+b].set=b_)});var cd=/%20/g,ce=/\[\]$/,cf=/\r?\n/g,cg=/^(?:color|date|datetime|datetime-local|email|hidden|month|number|password|range|search|tel|text|time|url|week)$/i,ch=/^(?:select|textarea)/i;p.fn.extend({serialize:function(){return p.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return this.elements?p.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||ch.test(this.nodeName)||cg.test(this.type))}).map(function(a,b){var c=p(this).val();return c==null?null:p.isArray(c)?p.map(c,function(a,c){return{name:b.name,value:a.replace(cf,"\r\n")}}):{name:b.name,value:c.replace(cf,"\r\n")}}).get()}}),p.param=function(a,c){var d,e=[],f=function(a,b){b=p.isFunction(b)?b():b==null?"":b,e[e.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};c===b&&(c=p.ajaxSettings&&p.ajaxSettings.traditional);if(p.isArray(a)||a.jquery&&!p.isPlainObject(a))p.each(a,function(){f(this.name,this.value)});else for(d in a)ci(d,a[d],c,f);return e.join("&").replace(cd,"+")};var cj,ck,cl=/#.*$/,cm=/^(.*?):[ \t]*([^\r\n]*)\r?$/mg,cn=/^(?:about|app|app\-storage|.+\-extension|file|res|widget):$/,co=/^(?:GET|HEAD)$/,cp=/^\/\//,cq=/\?/,cr=/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,cs=/([?&])_=[^&]*/,ct=/^([\w\+\.\-]+:)(?:\/\/([^\/?#:]*)(?::(\d+)|)|)/,cu=p.fn.load,cv={},cw={},cx=["*/"]+["*"];try{ck=f.href}catch(cy){ck=e.createElement("a"),ck.href="",ck=ck.href}cj=ct.exec(ck.toLowerCase())||[],p.fn.load=function(a,c,d){if(typeof a!="string"&&cu)return cu.apply(this,arguments);if(!this.length)return this;var e,f,g,h=this,i=a.indexOf(" ");return i>=0&&(e=a.slice(i,a.length),a=a.slice(0,i)),p.isFunction(c)?(d=c,c=b):c&&typeof c=="object"&&(f="POST"),p.ajax({url:a,type:f,dataType:"html",data:c,complete:function(a,b){d&&h.each(d,g||[a.responseText,b,a])}}).done(function(a){g=arguments,h.html(e?p("<div>").append(a.replace(cr,"")).find(e):a)}),this},p.each("ajaxStart ajaxStop ajaxComplete ajaxError ajaxSuccess ajaxSend".split(" "),function(a,b){p.fn[b]=function(a){return this.on(b,a)}}),p.each(["get","post"],function(a,c){p[c]=function(a,d,e,f){return p.isFunction(d)&&(f=f||e,e=d,d=b),p.ajax({type:c,url:a,data:d,success:e,dataType:f})}}),p.extend({getScript:function(a,c){return p.get(a,b,c,"script")},getJSON:function(a,b,c){return p.get(a,b,c,"json")},ajaxSetup:function(a,b){return b?cB(a,p.ajaxSettings):(b=a,a=p.ajaxSettings),cB(a,b),a},ajaxSettings:{url:ck,isLocal:cn.test(cj[1]),global:!0,type:"GET",contentType:"application/x-www-form-urlencoded; charset=UTF-8",processData:!0,async:!0,accepts:{xml:"application/xml, text/xml",html:"text/html",text:"text/plain",json:"application/json, text/javascript","*":cx},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText"},converters:{"* text":a.String,"text html":!0,"text json":p.parseJSON,"text xml":p.parseXML},flatOptions:{context:!0,url:!0}},ajaxPrefilter:cz(cv),ajaxTransport:cz(cw),ajax:function(a,c){function y(a,c,f,i){var k,s,t,u,w,y=c;if(v===2)return;v=2,h&&clearTimeout(h),g=b,e=i||"",x.readyState=a>0?4:0,f&&(u=cC(l,x,f));if(a>=200&&a<300||a===304)l.ifModified&&(w=x.getResponseHeader("Last-Modified"),w&&(p.lastModified[d]=w),w=x.getResponseHeader("Etag"),w&&(p.etag[d]=w)),a===304?(y="notmodified",k=!0):(k=cD(l,u),y=k.state,s=k.data,t=k.error,k=!t);else{t=y;if(!y||a)y="error",a<0&&(a=0)}x.status=a,x.statusText=(c||y)+"",k?o.resolveWith(m,[s,y,x]):o.rejectWith(m,[x,y,t]),x.statusCode(r),r=b,j&&n.trigger("ajax"+(k?"Success":"Error"),[x,l,k?s:t]),q.fireWith(m,[x,y]),j&&(n.trigger("ajaxComplete",[x,l]),--p.active||p.event.trigger("ajaxStop"))}typeof a=="object"&&(c=a,a=b),c=c||{};var d,e,f,g,h,i,j,k,l=p.ajaxSetup({},c),m=l.context||l,n=m!==l&&(m.nodeType||m instanceof p)?p(m):p.event,o=p.Deferred(),q=p.Callbacks("once memory"),r=l.statusCode||{},t={},u={},v=0,w="canceled",x={readyState:0,setRequestHeader:function(a,b){if(!v){var c=a.toLowerCase();a=u[c]=u[c]||a,t[a]=b}return this},getAllResponseHeaders:function(){return v===2?e:null},getResponseHeader:function(a){var c;if(v===2){if(!f){f={};while(c=cm.exec(e))f[c[1].toLowerCase()]=c[2]}c=f[a.toLowerCase()]}return c===b?null:c},overrideMimeType:function(a){return v||(l.mimeType=a),this},abort:function(a){return a=a||w,g&&g.abort(a),y(0,a),this}};o.promise(x),x.success=x.done,x.error=x.fail,x.complete=q.add,x.statusCode=function(a){if(a){var b;if(v<2)for(b in a)r[b]=[r[b],a[b]];else b=a[x.status],x.always(b)}return this},l.url=((a||l.url)+"").replace(cl,"").replace(cp,cj[1]+"//"),l.dataTypes=p.trim(l.dataType||"*").toLowerCase().split(s),l.crossDomain==null&&(i=ct.exec(l.url.toLowerCase())||!1,l.crossDomain=i&&i.join(":")+(i[3]?"":i[1]==="http:"?80:443)!==cj.join(":")+(cj[3]?"":cj[1]==="http:"?80:443)),l.data&&l.processData&&typeof l.data!="string"&&(l.data=p.param(l.data,l.traditional)),cA(cv,l,c,x);if(v===2)return x;j=l.global,l.type=l.type.toUpperCase(),l.hasContent=!co.test(l.type),j&&p.active++===0&&p.event.trigger("ajaxStart");if(!l.hasContent){l.data&&(l.url+=(cq.test(l.url)?"&":"?")+l.data,delete l.data),d=l.url;if(l.cache===!1){var z=p.now(),A=l.url.replace(cs,"$1_="+z);l.url=A+(A===l.url?(cq.test(l.url)?"&":"?")+"_="+z:"")}}(l.data&&l.hasContent&&l.contentType!==!1||c.contentType)&&x.setRequestHeader("Content-Type",l.contentType),l.ifModified&&(d=d||l.url,p.lastModified[d]&&x.setRequestHeader("If-Modified-Since",p.lastModified[d]),p.etag[d]&&x.setRequestHeader("If-None-Match",p.etag[d])),x.setRequestHeader("Accept",l.dataTypes[0]&&l.accepts[l.dataTypes[0]]?l.accepts[l.dataTypes[0]]+(l.dataTypes[0]!=="*"?", "+cx+"; q=0.01":""):l.accepts["*"]);for(k in l.headers)x.setRequestHeader(k,l.headers[k]);if(!l.beforeSend||l.beforeSend.call(m,x,l)!==!1&&v!==2){w="abort";for(k in{success:1,error:1,complete:1})x[k](l[k]);g=cA(cw,l,c,x);if(!g)y(-1,"No Transport");else{x.readyState=1,j&&n.trigger("ajaxSend",[x,l]),l.async&&l.timeout>0&&(h=setTimeout(function(){x.abort("timeout")},l.timeout));try{v=1,g.send(t,y)}catch(B){if(v<2)y(-1,B);else throw B}}return x}return x.abort()},active:0,lastModified:{},etag:{}});var cE=[],cF=/\?/,cG=/(=)\?(?=&|$)|\?\?/,cH=p.now();p.ajaxSetup({jsonp:"callback",jsonpCallback:function(){var a=cE.pop()||p.expando+"_"+cH++;return this[a]=!0,a}}),p.ajaxPrefilter("json jsonp",function(c,d,e){var f,g,h,i=c.data,j=c.url,k=c.jsonp!==!1,l=k&&cG.test(j),m=k&&!l&&typeof i=="string"&&!(c.contentType||"").indexOf("application/x-www-form-urlencoded")&&cG.test(i);if(c.dataTypes[0]==="jsonp"||l||m)return f=c.jsonpCallback=p.isFunction(c.jsonpCallback)?c.jsonpCallback():c.jsonpCallback,g=a[f],l?c.url=j.replace(cG,"$1"+f):m?c.data=i.replace(cG,"$1"+f):k&&(c.url+=(cF.test(j)?"&":"?")+c.jsonp+"="+f),c.converters["script json"]=function(){return h||p.error(f+" was not called"),h[0]},c.dataTypes[0]="json",a[f]=function(){h=arguments},e.always(function(){a[f]=g,c[f]&&(c.jsonpCallback=d.jsonpCallback,cE.push(f)),h&&p.isFunction(g)&&g(h[0]),h=g=b}),"script"}),p.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/javascript|ecmascript/},converters:{"text script":function(a){return p.globalEval(a),a}}}),p.ajaxPrefilter("script",function(a){a.cache===b&&(a.cache=!1),a.crossDomain&&(a.type="GET",a.global=!1)}),p.ajaxTransport("script",function(a){if(a.crossDomain){var c,d=e.head||e.getElementsByTagName("head")[0]||e.documentElement;return{send:function(f,g){c=e.createElement("script"),c.async="async",a.scriptCharset&&(c.charset=a.scriptCharset),c.src=a.url,c.onload=c.onreadystatechange=function(a,e){if(e||!c.readyState||/loaded|complete/.test(c.readyState))c.onload=c.onreadystatechange=null,d&&c.parentNode&&d.removeChild(c),c=b,e||g(200,"success")},d.insertBefore(c,d.firstChild)},abort:function(){c&&c.onload(0,1)}}}});var cI,cJ=a.ActiveXObject?function(){for(var a in cI)cI[a](0,1)}:!1,cK=0;p.ajaxSettings.xhr=a.ActiveXObject?function(){return!this.isLocal&&cL()||cM()}:cL,function(a){p.extend(p.support,{ajax:!!a,cors:!!a&&"withCredentials"in a})}(p.ajaxSettings.xhr()),p.support.ajax&&p.ajaxTransport(function(c){if(!c.crossDomain||p.support.cors){var d;return{send:function(e,f){var g,h,i=c.xhr();c.username?i.open(c.type,c.url,c.async,c.username,c.password):i.open(c.type,c.url,c.async);if(c.xhrFields)for(h in c.xhrFields)i[h]=c.xhrFields[h];c.mimeType&&i.overrideMimeType&&i.overrideMimeType(c.mimeType),!c.crossDomain&&!e["X-Requested-With"]&&(e["X-Requested-With"]="XMLHttpRequest");try{for(h in e)i.setRequestHeader(h,e[h])}catch(j){}i.send(c.hasContent&&c.data||null),d=function(a,e){var h,j,k,l,m;try{if(d&&(e||i.readyState===4)){d=b,g&&(i.onreadystatechange=p.noop,cJ&&delete cI[g]);if(e)i.readyState!==4&&i.abort();else{h=i.status,k=i.getAllResponseHeaders(),l={},m=i.responseXML,m&&m.documentElement&&(l.xml=m);try{l.text=i.responseText}catch(a){}try{j=i.statusText}catch(n){j=""}!h&&c.isLocal&&!c.crossDomain?h=l.text?200:404:h===1223&&(h=204)}}}catch(o){e||f(-1,o)}l&&f(h,j,l,k)},c.async?i.readyState===4?setTimeout(d,0):(g=++cK,cJ&&(cI||(cI={},p(a).unload(cJ)),cI[g]=d),i.onreadystatechange=d):d()},abort:function(){d&&d(0,1)}}}});var cN,cO,cP=/^(?:toggle|show|hide)$/,cQ=new RegExp("^(?:([-+])=|)("+q+")([a-z%]*)$","i"),cR=/queueHooks$/,cS=[cY],cT={"*":[function(a,b){var c,d,e=this.createTween(a,b),f=cQ.exec(b),g=e.cur(),h=+g||0,i=1,j=20;if(f){c=+f[2],d=f[3]||(p.cssNumber[a]?"":"px");if(d!=="px"&&h){h=p.css(e.elem,a,!0)||c||1;do i=i||".5",h=h/i,p.style(e.elem,a,h+d);while(i!==(i=e.cur()/g)&&i!==1&&--j)}e.unit=d,e.start=h,e.end=f[1]?h+(f[1]+1)*c:c}return e}]};p.Animation=p.extend(cW,{tweener:function(a,b){p.isFunction(a)?(b=a,a=["*"]):a=a.split(" ");var c,d=0,e=a.length;for(;d<e;d++)c=a[d],cT[c]=cT[c]||[],cT[c].unshift(b)},prefilter:function(a,b){b?cS.unshift(a):cS.push(a)}}),p.Tween=cZ,cZ.prototype={constructor:cZ,init:function(a,b,c,d,e,f){this.elem=a,this.prop=c,this.easing=e||"swing",this.options=b,this.start=this.now=this.cur(),this.end=d,this.unit=f||(p.cssNumber[c]?"":"px")},cur:function(){var a=cZ.propHooks[this.prop];return a&&a.get?a.get(this):cZ.propHooks._default.get(this)},run:function(a){var b,c=cZ.propHooks[this.prop];return this.options.duration?this.pos=b=p.easing[this.easing](a,this.options.duration*a,0,1,this.options.duration):this.pos=b=a,this.now=(this.end-this.start)*b+this.start,this.options.step&&this.options.step.call(this.elem,this.now,this),c&&c.set?c.set(this):cZ.propHooks._default.set(this),this}},cZ.prototype.init.prototype=cZ.prototype,cZ.propHooks={_default:{get:function(a){var b;return a.elem[a.prop]==null||!!a.elem.style&&a.elem.style[a.prop]!=null?(b=p.css(a.elem,a.prop,!1,""),!b||b==="auto"?0:b):a.elem[a.prop]},set:function(a){p.fx.step[a.prop]?p.fx.step[a.prop](a):a.elem.style&&(a.elem.style[p.cssProps[a.prop]]!=null||p.cssHooks[a.prop])?p.style(a.elem,a.prop,a.now+a.unit):a.elem[a.prop]=a.now}}},cZ.propHooks.scrollTop=cZ.propHooks.scrollLeft={set:function(a){a.elem.nodeType&&a.elem.parentNode&&(a.elem[a.prop]=a.now)}},p.each(["toggle","show","hide"],function(a,b){var c=p.fn[b];p.fn[b]=function(d,e,f){return d==null||typeof d=="boolean"||!a&&p.isFunction(d)&&p.isFunction(e)?c.apply(this,arguments):this.animate(c$(b,!0),d,e,f)}}),p.fn.extend({fadeTo:function(a,b,c,d){return this.filter(bZ).css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(a,b,c,d){var e=p.isEmptyObject(a),f=p.speed(b,c,d),g=function(){var b=cW(this,p.extend({},a),f);e&&b.stop(!0)};return e||f.queue===!1?this.each(g):this.queue(f.queue,g)},stop:function(a,c,d){var e=function(a){var b=a.stop;delete a.stop,b(d)};return typeof a!="string"&&(d=c,c=a,a=b),c&&a!==!1&&this.queue(a||"fx",[]),this.each(function(){var b=!0,c=a!=null&&a+"queueHooks",f=p.timers,g=p._data(this);if(c)g[c]&&g[c].stop&&e(g[c]);else for(c in g)g[c]&&g[c].stop&&cR.test(c)&&e(g[c]);for(c=f.length;c--;)f[c].elem===this&&(a==null||f[c].queue===a)&&(f[c].anim.stop(d),b=!1,f.splice(c,1));(b||!d)&&p.dequeue(this,a)})}}),p.each({slideDown:c$("show"),slideUp:c$("hide"),slideToggle:c$("toggle"),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(a,b){p.fn[a]=function(a,c,d){return this.animate(b,a,c,d)}}),p.speed=function(a,b,c){var d=a&&typeof a=="object"?p.extend({},a):{complete:c||!c&&b||p.isFunction(a)&&a,duration:a,easing:c&&b||b&&!p.isFunction(b)&&b};d.duration=p.fx.off?0:typeof d.duration=="number"?d.duration:d.duration in p.fx.speeds?p.fx.speeds[d.duration]:p.fx.speeds._default;if(d.queue==null||d.queue===!0)d.queue="fx";return d.old=d.complete,d.complete=function(){p.isFunction(d.old)&&d.old.call(this),d.queue&&p.dequeue(this,d.queue)},d},p.easing={linear:function(a){return a},swing:function(a){return.5-Math.cos(a*Math.PI)/2}},p.timers=[],p.fx=cZ.prototype.init,p.fx.tick=function(){var a,b=p.timers,c=0;for(;c<b.length;c++)a=b[c],!a()&&b[c]===a&&b.splice(c--,1);b.length||p.fx.stop()},p.fx.timer=function(a){a()&&p.timers.push(a)&&!cO&&(cO=setInterval(p.fx.tick,p.fx.interval))},p.fx.interval=13,p.fx.stop=function(){clearInterval(cO),cO=null},p.fx.speeds={slow:600,fast:200,_default:400},p.fx.step={},p.expr&&p.expr.filters&&(p.expr.filters.animated=function(a){return p.grep(p.timers,function(b){return a===b.elem}).length});var c_=/^(?:body|html)$/i;p.fn.offset=function(a){if(arguments.length)return a===b?this:this.each(function(b){p.offset.setOffset(this,a,b)});var c,d,e,f,g,h,i,j={top:0,left:0},k=this[0],l=k&&k.ownerDocument;if(!l)return;return(d=l.body)===k?p.offset.bodyOffset(k):(c=l.documentElement,p.contains(c,k)?(typeof k.getBoundingClientRect!="undefined"&&(j=k.getBoundingClientRect()),e=da(l),f=c.clientTop||d.clientTop||0,g=c.clientLeft||d.clientLeft||0,h=e.pageYOffset||c.scrollTop,i=e.pageXOffset||c.scrollLeft,{top:j.top+h-f,left:j.left+i-g}):j)},p.offset={bodyOffset:function(a){var b=a.offsetTop,c=a.offsetLeft;return p.support.doesNotIncludeMarginInBodyOffset&&(b+=parseFloat(p.css(a,"marginTop"))||0,c+=parseFloat(p.css(a,"marginLeft"))||0),{top:b,left:c}},setOffset:function(a,b,c){var d=p.css(a,"position");d==="static"&&(a.style.position="relative");var e=p(a),f=e.offset(),g=p.css(a,"top"),h=p.css(a,"left"),i=(d==="absolute"||d==="fixed")&&p.inArray("auto",[g,h])>-1,j={},k={},l,m;i?(k=e.position(),l=k.top,m=k.left):(l=parseFloat(g)||0,m=parseFloat(h)||0),p.isFunction(b)&&(b=b.call(a,c,f)),b.top!=null&&(j.top=b.top-f.top+l),b.left!=null&&(j.left=b.left-f.left+m),"using"in b?b.using.call(a,j):e.css(j)}},p.fn.extend({position:function(){if(!this[0])return;var a=this[0],b=this.offsetParent(),c=this.offset(),d=c_.test(b[0].nodeName)?{top:0,left:0}:b.offset();return c.top-=parseFloat(p.css(a,"marginTop"))||0,c.left-=parseFloat(p.css(a,"marginLeft"))||0,d.top+=parseFloat(p.css(b[0],"borderTopWidth"))||0,d.left+=parseFloat(p.css(b[0],"borderLeftWidth"))||0,{top:c.top-d.top,left:c.left-d.left}},offsetParent:function(){return this.map(function(){var a=this.offsetParent||e.body;while(a&&!c_.test(a.nodeName)&&p.css(a,"position")==="static")a=a.offsetParent;return a||e.body})}}),p.each({scrollLeft:"pageXOffset",scrollTop:"pageYOffset"},function(a,c){var d=/Y/.test(c);p.fn[a]=function(e){return p.access(this,function(a,e,f){var g=da(a);if(f===b)return g?c in g?g[c]:g.document.documentElement[e]:a[e];g?g.scrollTo(d?p(g).scrollLeft():f,d?f:p(g).scrollTop()):a[e]=f},a,e,arguments.length,null)}}),p.each({Height:"height",Width:"width"},function(a,c){p.each({padding:"inner"+a,content:c,"":"outer"+a},function(d,e){p.fn[e]=function(e,f){var g=arguments.length&&(d||typeof e!="boolean"),h=d||(e===!0||f===!0?"margin":"border");return p.access(this,function(c,d,e){var f;return p.isWindow(c)?c.document.documentElement["client"+a]:c.nodeType===9?(f=c.documentElement,Math.max(c.body["scroll"+a],f["scroll"+a],c.body["offset"+a],f["offset"+a],f["client"+a])):e===b?p.css(c,d,e,h):p.style(c,d,e,h)},c,g?e:b,g,null)}})}),a.jQuery=a.$=p,typeof define=="function"&&define.amd&&define.amd.jQuery&&define("jquery",[],function(){return p})})(window);���� JFIF  H H  ���ICC_PROFILE   �appl   mntrRGB XYZ �     acspAPPL    appl                  ��     �-appl                                               desc     odscm  x  lcprt  �   8wtpt     rXYZ  0   gXYZ  D   bXYZ  X   rTRC  l   chad  |   ,bTRC  l   gTRC  l   desc       Generic RGB Profile           Generic RGB Profile                                                  mluc          skSK   (  xhrHR   (  �caES   $  �ptBR   &  �ukUA   *  frFU   (  <zhTW     ditIT   (  znbNO   &  �koKR     �csCZ   "  �heIL      deDE   ,  huHU   (  JsvSE   &  �zhCN     rjaJP     �roRO   $  �elGR   "  �ptPO   &  �nlNL   (  esES   &  �thTH   $  6trTR   "  ZfiFI   (  |plPL   ,  �ruRU   "  �arEG   &  �enUS   &  daDK   .  > Va e o b e c n �   R G B   p r o f i l G e n e r i k i   R G B   p r o f i l P e r f i l   R G B   g e n � r i c P e r f i l   R G B   G e n � r i c o030;L=89  ?@>D09;   R G B P r o f i l   g � n � r i q u e   R V B�u(   R G B  �r_icϏ� P r o f i l o   R G B   g e n e r i c o G e n e r i s k   R G B - p r o f i l�|�   R G B  ��\��| O b e c n �   R G B   p r o f i l������   R G B  ���� A l l g e m e i n e s   R G B - P r o f i l � l t a l � n o s   R G B   p r o f i lfn�   R G B  cϏ�e�N�N �,   R G B  0�0�0�0�0�0� P r o f i l   R G B   g e n e r i c������  ������   R G B P e r f i l   R G B   g e n � r i c o A l g e m e e n   R G B - p r o f i e lB#D%L   R G B  1H'D G e n e l   R G B   P r o f i l i Y l e i n e n   R G B - p r o f i i l i U n i w e r s a l n y   p r o f i l   R G B1I89  ?@>D8;L   R G BEDA  *91JA   R G B  'D9'E G e n e r i c   R G B   P r o f i l e G e n e r e l   R G B - b e s k r i v e l s etext    Copyright 2007 Apple Inc., all rights reserved. XYZ       �R    �XYZ       tM  =�  �XYZ       Zu  �s  4XYZ       (  �  �6curv       �  sf32     B  ����&  �  ����������  �  �l�� �Exif  MM *                  J       R(       �i       Z       H      H    �      ��      �    �� C 		
%,'/.,'+*17F<14B5*+=S>BIKOOO/;V\VL\FMOL�� C$$L2+2LLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLL�� ��" ��           	
�� �   } !1AQa"q2���#B��R��$3br�	
%&'()*456789:CDEFGHIJSTUVWXYZcdefghijstuvwxyz���������������������������������������������������������������������������        	
�� �  w !1AQaq"2�B����	#3R�br�
$4�%�&'()*56789:CDEFGHIJSTUVWXYZcdefghijstuvwxyz��������������������������������������������������������������������������   ? ��I� =�Γ�z7�L��>��!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~t�(�!�t��ѿ:<�?�~u^��+Hi�$k��`�����v/���ZF��"S�]���I� =�Γ�z7�X���Ko�����W`��r[k��a�� עP�w�ط�I� =�Γ�z7�L���.��'��oΏ:O��ߝ2�.���'��oΏ:O��ߝ2�.���'��oΊe]��QU���� �Mm��� �&���o�f?Z����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����,QU���� �Mm��� �&����VZ����*�(�m��cH��q�jͤqIn���Cs�j��[k�Z)`�
r�������rO������a�8[��}f�����Ɇ3� }8�&�G^ >�E�[L�VopT�S������k5��/��'�����q-��(i"����?��W��%@�enA���ʥX���Y-x�m�h�4lyLp� OF�}�kO�՚�ZfoJ/�Mz�RJ�xđ�*�<{S���� �Mc�Z� ��~�C���X����S� |�>��� �MU���>�C���X����S� |�(��o�a��ξ�:�(��>P(�� (�� (�� (�� (�� (�� (�� (�� (��Z�P8�CM;p�D715���O_�X�d�K��O�� �@�������0>������[���BW�����q��<�4�yb{�� -.��e�]R"ơQB�� ���xGX���%=�@H�h��9�\Ϩyg� ��]���ö�6}�J���22é��2��w��W�Q@u7�5X�幵��#(?���X��n�����
��Z�<�� �y��z��O��G#�V�QRW�_h�}����L��ۆ�#����	:\9��V��D�~d�$u�ր9:*֡������?u��}U��(��(��(��,i*�C)��@r�\Z��X���
�!s<�lEd@9�.O_�j=�(pҨ>��G,r� �uo���{׎C��:�}�� W�H�"�a�����1��ʿq�=��A��q�����=��i4��NZ6������V�:Ȋ����A�Gu �����O���*f�V���I+���?� kQE QE?ئ�_�ꏱM���iQ^��n����C��G�3~�7�� �T}�oE� ��J�?��v_��?�(�foئ�_�ꏱM���iQG��n�����e���ދ� }Q�)����*(�ҭ�_0�̣ݙ�b���>�7�� �U�E�U�/��ٔ{�7�Sz/��Gئ�_�괨��J�e�|��2�vf��oE� ���ދ� }V�iV쿯�fQ��߱M���b���ҤfTR�B�$���*ݗ����=ٙ%��#<�d�l )��y�y��!���ɛ�ZH?�������.��rh�>1��wo�G��^�ig�^]�j��޽�����x؅IJԶ2to��*�Z�0�<�r��?Ҷ�U*��: 0)�V�8QE QE QE QE QE�\��O�2��5���6ۧ�r��hݾ���}k�� ��4�F�ppB����ދ� }Wu�x~=�qj� d#����2�b�0Â+��b�4%f��� L�0�\5x�-߷�ϱM���b���Ң�� ��v_����̣ݙ�b���>�7�� �U�E�U�/��ٔ{�7�Sz/��Un���)�݈5�Y���jʒ���s��7�j�y�Y;;<��WW*&�s*��"N��i��Wy�<��m�������)\l�>�g�� �U����N�/��R�h5t���9u�S��1�����YX]�v�cڀ�fo�$��j(�ӫ��2�vf��oE� ��+TӦ�u��܌�z�� R1�$����r�H%��KF����4)"�èa�S��7�� �U>�sb�ŀ�5n���MY_0�[E��3~�7�� �TV����v_�̯��=�QE枈QE QE QE QE QE QE TZv�/�/��{E�@ظ�N�?�O���I�܍>	2��^��8�c��Oj�#N�J����1q�P��~=���������_�x��*ߺ�����m������%��*"5W�x�E��>2���^l�0������8��s�v��[���;�+��Ǟ�eu7�q���
�7�})G�-o�>��� Ј��-؜��gcEp|Jb��is�ΝT~��R�C�$Ecc�gy?��ui��n�%���W�I�?���m�����I�2����u��=�� VoMu3x�K��٨f���5�1��� �����{����˟�P;`s�FO\���j.��C�g�k�3Ҭ�럲jO{��1B�ؾ8��g����;�J���Wos$W���N8�5�*��
�~��|+� �][�����Z�U���b��]j�����(���+��~��_�L`B�Y��&����ӧoM�X�V{�QR�jE�[R�*RS�癩R# ���N��ok'O��Ӯ��Ǣ� �3���+�+Е	�����^�
���K�oj��3�w�P:�� =��P����]vǰ��ϵE;sji;�@cvWted�}��q�=$Z���;	�ď������H1.��j�헜|�HY��=z�(�i"d���Md	Y"r��u�$Oq�R#K'���u�d7�>��iz�.b�0�`���W�}+eH#p ���)GG�BJZ��w)$z
�^�8�q�V�9a�Q���5��Џ��9?�Q�f���'����5k�=A�y��H5<��0�ϩ�Z)B:���b���z(�IE�w��`��)���������� u?#U������G�}n��2�ۥ��~F��K����V�����T[��̳��������� u?#U���?���� 3,��_��h�t��O��j(���A��� ��?n����>�/�S�5Z�>�C�P}n��2�ۥ��~F��K����V�����T[��̳�������5F���p�Pd�j*e��7���t��-��.��N��{Q�Zʃ�u� ����k�&��	y���H�v.?�{gֽ&������;xc�%��@�*Z�$����n�(��b
�/� �ݩ}"� �+�.n!��渕"�Y݀ {����v���o尗�1�ͭ���ƺn�H��EʓI訾�b��08*���=j�6�w4��s�m���"��No��*U�c(�|;�N@M�A�#���{��\�[\�#�	n�� p_Ɯ�N*�TjAsIhQZ���x���)�'�Kt���.[v��})S��; �I՗*2ix�ף'íg��lKܲ��w�~/��'Ԛ�!���W�Y�=ؒzzƺ��gb�>�4��,�VT,:�s�����z�R7c�f���2�H$�G���*�5�>�,��'���AW̒V��㠔Axp}F��*n�4pʔ��=r�����X�m�I�w�M]PQE���5��Ȩ2���q�?��(�����N�!��$t��t5�������R�\[^. �7�����J>5sJugO�v.��_��i���"���V�E�5�U�A��VU�����u� ����5��uv�e�OqW�P��*# ���S��/<0�j��Kj���ϧC���_�@�u��c�+׹�B��S�G���
ֵ�����E�$k'M���r��c���~�M��5g	X���KR�ۥ��~F��K����V���h*+�u� ��~�/�S�4}�_��j�}V�����e��K����n�����EU���>�_��g��� u?#EV�����T[���(�/��� zJ>�o��+�
f� �����+K�6� ޒ���� zJ?�(y��u#6��������6� ޒ��
a��_�͢���o��(������B��gW�3h�/��� zJ>�o��(�С������+K�6� ޒ���� zJ?�(y��u#*iV^W8TRƽ�ޖ�K��W���s��W��Z���ai�?k��&��5숡*�(Һ�U�X�Gc��)R�,�EV�AL�E�6�F
�3����~(�c������,�z����x���$�_c���AA��0�O�+�I$� ���1�&��=��!���DZ(��鈼�_ձʷQߌ��,.��,Ẁ��r0G��|Ӥ_I��6��	A��<�����>��k^b��e�n?��s���x�����U�{�?C��^�^pۯ��� ��� "���ƿ�|��Y��Y�G���듮��ƻ�[X��<�ͅ�!ǃ�)��FԟL����e���7�f�|c�'�j����Ǉm�i�]�� u��x�zg�{D�$��Kk؉�'�<��f�ӣ�~��PѾe��P�C���\B=Q����ۮg�d�I	���6���z� ��( �sǺ���g�5s�� xv�FG�]�^������-��Cڵ�=N�='���H.[z`� xn��Z�a�����[N��fuQ�T�h��g�i�����Q��IX� hP�6�ί�f�Z_a����}����Q��C�?����V��m� �%a����hP����Fm���IG�m� �%�<��:���Ei}����Q��IG��0�ί�f�Z_a����Q��C�?����E��Q@Q@Q@Q@Q@����irD�`��8�����_b���M�%ֽj��Z1^G�b��^O��
(��`�<�Ҭt�/��zr� t�Լ#x�.�`��1�v���4���0�RP��~��Wо�Q��t�Uo��^��9�m^��|�U=9c� k��4�]�*?ww/�?��G��a�� mW��-$���V�ݫ�$;Q��5����ߙ&�18q��^���|4��W��k�9� ���p��D���3�8� ��� ��� 7�����ߖ��ܬ�{)���x1�<��##����q^�\�v�G�6m9�0��(?�Ǳ�F+�=���zM����oi&I�X�����U�}�1^���÷7R}� Q�J�|��xf��d 8\�{��E�k��� R�x:�۟��}m�6��Y�Emn�8b@����SPEPEP�|_Ci�]>�z�1ꏟ�Z � ��f���j��i�X��W�?�.��M:Q=���r5?�x��~z��U/�>��(��c�
(��
(��
(��
(��
(��
+/�3�C�
>�7��?��S�2��#��ԥ���� 3R���L����B���� =�(�̩��?�)+�?�Ԣ���7��?���3�C�
?�*2�J_�� �5(����� =�(�L����B��ʟ̃�R�����J+/�3�C�
>�7��?���2�� �ԥ���� 3R���L����B���� =�(�̩��?�)+�?���Wڵ�_P����� �^�^s���:�]����ȮT���\�o�QE�
FP�U� �{��@���h��]XB�i��Qra� d����u�'#�z�����"��f�<���ne��,R��:��g�=R0y�c��8��Z�5�5�5
�����:� ���Lޑ��+�t���+�uw����?���t׈Y��p�F�ʸ�
81�
&�� ��/Q�e'����[� 8�����+��Tra?������d�%B�*�
��4�+�=� x[B��G��3��B���Uc@��Tp �E QE QE QE q������LSF� ��?�r��Ѭ��ʟһo� �y���xW����l�B � 0+���x��ۃ��&�7h����� =�(�L����B���ʟ̏G�R�����J+/�3�C�
>�7��?���2�� �ԥ���� 3R���L����B���� =�(�̩��?�)+�?�Ԣ���7��?���3�C�
?�*2�J_�� �5(����� =�(�L����B��ʟ̃�R�����J+/�3�C�
(�̩��?�)+�?̊�(�t�B�(��(��(��(��(�� .~��R̃��w���� ��z�-�i;Ӯ���t���s���m��(��(��(��(��\-���9]��m&2z���z!�$��]��X7�isʥ���(q��\-pb媉�c���V߁X/�l�q�	����bT�m����K#AS����.Y�rP�%E#�誚DF*�"I)I���V��=ࢊ( ��( ��( ��(���/���W1����y�[�`�c��>3jt�26>d�����?R?*�]���� P��� (�� (�� (�� (�� (�� ��=���}���x�}�ޯ�?��߫��U��=���}���x�}{z������/�_pϳ�� �G��� 爧�G��������U��=���}���x�}{z������/�_pϳ�� �G��� 爧�G��������U��=���}���x�}{z������/�_q����1e�)������ \
��
j��x~��]�@;8��\�f�������p��:�Q�//�;���e�O���k���:�q��G���T�d�S��+�<Т�( ��( ��(���	�80���q�К�N����5��O2yh���gi=�޹(����7D�Ƌ���j�]F�q�p�Ӊ��s���"?ֺG����^�ÝG��s����qW�<��IH�،��������V�m�S�8�6�7QB�Q�S���OD(�� (�� (�� (���W�7A��I巖B��	�cܞ� y��2���ww��vDF��o�['���{��*����7NH�s|ҟ��o´k�q8�Ϋ�vG�a���$���}���x�>�o� <E>�������O��*��}���x�>�o� <E>�=�_�x}^��g��� 爣��� ��S����g����*��}���x�>�o� <E>�=�_�x}^��g��� 爣��� ��S����g����*��}���x�)�Q��� 3������}�EV&�EPEPEPEPEPX���Ŧ������V���?��C[TV�jʌ��eZ�kA�Gq��~��Zj^Y7$������ �kV�i���:��Ό2��>���׹���_Z�k�^"���������m�ǽ}5ѭh�1^��ϖF�Q[�%-Q�4�}f��M*Ǹ6b����%ޣge���rL��u�q�CI+Ig��촅��zS�|��y�OY��k�i3���-�m�P%�£�h~��/��9�=��56S��U�M8x�X��� �utP �8�>��j0{����?�&���-	8�����������Ќ�M���;�m��;��� #Z���0=��e�xcD���K��� {� �b���V�6��il@�rS��4�Q\�����k���Y���V���ؔ����񮢀
(��
(���a�����U��s�� �M4��DQ�Oj�k�'�z��տ�]dwD��~�a��T>,�}Ǌ.����yr��<d_L��u&��t��s0y3���ooJ��WT��n��R����A\�z�>��(��>�(��@QE QE QE QE QE c�o�7�h���o��Q_ad|w3sy�3F���~f��,������ߙ�sy�3IEA�����o�ѹ��ߙ��� �b�o�7�h���o�Ј�8DR�z 95)��r^'E�8_�A��²M2F�۝����Չ�EYJI(��<��r��7���!�ݰ͞u���%�h���"�P��$vɢ�9�8�$w���"HS�8s��/hQ�?~̱:�*�Kc��$�+,�ia��$�?��)Lz��գ����p'�r{sE�s2̺<��)Ȉ��ҷ�=�t_�a\iڗ�.���܏���j�}�T�\�]Ely:��%��bz�çJ���1�^wa�v�H�Zv�;/����h ���s��Q]]xu��g�>�c�9p���ƺ]#����I��@�6]�2G߇�S�Qǵ=2���#��{Ʌ����A���\v��]M���"kI�3��p���	袊 (�� (�� (�� (�� (��{_�������h'��} �6��&j����@�{}���)�/�@��/��2_N<�	H��?�⼋_�&��K����#ec����A��!�p�{�	�va�X����]l%�����(��m��?� R��b���C��������~f�ɧ]$�1����m��ޘm&ܫ���H<�x4��vC���ߙ�sy�3S+�ۋy�G�֣�	fr�F�㪨�Qd�n���~f������L�7R���9辔�mn!R��"(8%��d̏sy�3F���~f��,������ߙ�sy�3IEA�����o�ѹ��ߙ��� �b�o�7�h���o��QE�s1w7���4RQE�s12=E���?����_ʼ��H� /�z��R�o��	���(������������TjG��ʗ�~�LL�QFG������FG�W��R?����T���� �bdz�2=ko#���T�J�O�)
���B�#������� ƂV��Y6��~�dv������!�X�m�+�{Ӹ���y.�QI^[Ս>Yᑮ_!�Ơn\��kO���H�͗�~��zN�Dk淘�Kpzg�#j��&����#'��9��Z�L������*0��E��v��:�J��8Z���˝����*>�xs@����0}s߭2]Jy�BmP�^����85~+��	�h��#� #��a#d;e@Sp����N�(e�� �fi�vo& ����`s������K�m�y˅2����ҖD6�����'� �hk�m��K�L~�iG��̗�~'�n�q�FV'%C �zt�Ң�QtxX[�̠P��.x� x���$�(R0��.8�J�E�X� 0x�_�{���B����7������H�YǙj� r\��%{tϽgǧ��ϥ_�A��⻾���j�b�1�H�ܼO;�Q��� ��K�N6�/��r����'(�+��+r�Е/n vʰ#�Ki�W^���Z\��qc� |��̬wgp�q�zR_Y��e~�m����"S���S�e�D��/��	�|Y��4������F�� ǅu�0ү:���뛘�����~�Ɏ�XI捘 s韭g��}6Gʹ�%p2���Eo��&//�z�Z��(�w����O����f�-u��W���tH�	�_�8�oè����-)����T%}s�Z]�ܟ�V�z%ǈ��e-6�n�u%���7�-r�X�D����h��X��6��==�cѼ?�4�w�1���N3��O��X����'���#7J��N�����XW)�v�	7a�y(�����lE�I�QG|�4 �������D�nP	��`�8����,�O�~%��4�Oq~����O��ݭ�X6�}���J�U �z�s���Z����G��cuH��m�1*~����� >����on_�_ْߛ�2n/�c��t��![p�}��r_y��(3������j�WY��ߕO�c$�¡����T�g��F��7�S�VyUQ��b�䍭�}�Q��1�*��������̏��FG�W�����G��/��?��� ��\{�oӎ�Yg�#+���@T�B�d��j�����Tdu*?�#���eK���$^���	�z���ɬ<�Z�D��VUb7*�g#���Q������ԏ��!��/��?�������$�tԙ���?����_ʏ�H� /��R�o��	���(������������TjG��ʗ�~�LL�QFG������FG�W��R?����T���� �bdz�2=Em�u*2?���ڑ�_�?���߇�#�Q[y�_ʊ?�#���eK���"QE��QE QE U�8`�ռ҈K�<�@=;�U
��I� ���= �kJ{�r*m��fB�*�����IS�y�R��l>i�py��i�2��Q�?��Sb�2��41!y<�ϥn���/�_�􅿂+wAn�9�=j��Z������E�n~�6�
b�1�� ������d/�2�Tds�� ��峿(�unb��}�͉[*@۽Ny��֪5�q
��9q�pS�ۊ|�1F%f��Exl�8��G�洊B��w� �R��q�B��\��, ��|�7=��O���Q��A��@N�r�ʪ����	9A$y�!�}z�T$L萱��I�8���[nPi����"�)
v�z��#�T���t_�h�A4�}�rs��� Z���y"R���s�(��t�!����dc�\���[#-��u����UT�i/DH3a��?�K�5[0o�n�8ϧZ�����&Mh�X6V��̫���u�v�[x"�`#.V<���N?��
i�K��(@r�=x8�W����X���
ӓ�הQ\������TV]�Yg��Oj���cV�B�<A1֣�V� }ͻ[h��]f�Nܹ���R����8���b�YZ�ԙ�
�\0�OOc�(q������ԓ��E%���4N�F㞙�x�ir��xHV��7��V�E�Y��;fdT$�����)��q�:��9~�s���T�ݤ,2�� ����Ճ�ȋ.f�,`3�����%��=4r$���e����Q�x���E=�ho���9鞣��)ͥΌ�J�yn�dTkj��2�������Z$��?���-t���"ط��:0�B���c�=���1�	�ws�.Us���ҡ]>F�G,���9=:w��G����E��C�	�W����[`$P�>C�g��R>�pk�a��Z�YΈ�6ާ���L�~�0ۥB P�N2jV� OE���$H�v� �����H��1��x�#�~��j��~�]�K,�@ �u'�\�����_ҡ�M������TT�6�l��T���J�t�F׌�9<ddg����k�#kܧE_:L�1���`�?J�,M�����#��ҕ9Gt8Ԍ�c(�����(��(��(��(������������=�� e����V_�ly����������=e����V_�ly����������=e����V_�ly�����ͬ�HcX]3nRXd��Ms�b������Ym8'��:��O�d��M|(؊�YC�IU�1�?��$7�d$n$�	c���hh&[pΊw��os��>��nR�����F���8��
����؞b��F��� ��#����A�V��2�!ۓ�epx>��^p֋ߛ�iX���������\�ៜ��=�g��a���Af��ev��N�A�I̓3I!]��お�g�KiC*̤������e�D��ۂIL0�m�?SG�rz9�� i5��7�j�]�h���f}i i#��ԗQ���nG_ʲ�i��! \m-�f����c��&R�q��������lou&�r��E��x��Р�`:�2�D�"!��=>�V]Y�%�ym����9�x�U�����"l���4d��9�K�2����/�F�K<r�F���G?QR�����c�zΓVv���%�I �N���ڛ�"Z �PF�dn@9�p}��Yj[M��m�i�ײ}֔�' ~&�ķ.	;��L�A��
�&�,�I�r'y������	z� ������=�玴� ���lg%��"��4�F�&wpO=j�ܴ�Yvd�=�V|���vIPe�	F<g�zt�5��}ܳ���/�Nhyt[����k8�o������|����z*�k˦�����p�5�%�5�1���w��O5vMjfR�^0H<���g� }������Ey~϶6E�,�<��9bI� �\����� ���W�VYT[��P�V�oJi�b��c�#�O_��J��.�h_��l���<�����Ǟku@���]I��9����B�{��̐�G��1�ҩ�r��L��Ƞ�sn�:
������O��&�HVa3�s�'��Li�f��8}��YB�QR1�3w��	�qۚ�Ma�3n�vʁD�02O�Z^���̟�"�S�Z	%b��~�,�]��	a��m��8�kgE��<���T�Cgc��ާ:��� �C����F�� ��Z?�����ȍ������I�h�9]��y�Yqj�M�dF��+�c�_ƞ�̍�D�c
K����� �����4Y:\�Hc�m�{TټH�.Уs����>��[�\�kM�����<緵>mO����a8���~}{qMe����c/�F���,I��)��Õ��S�\"�8!CH2q����b^*4D!>U��}��I����z�I��ۘ�'vX��==)g'���d��E�1?���b}:��*?����� j��M�1?���b}:Ǣ��� 0j��M�1?���b}:Ǣ��� 0j��M�1?���b}:Ǣ��� 0j��M�1?����E�q�`�՗�E?ʓ�y���h�� �o� |��9����%�e� *O��� �ɣʓ�y���h�p�a�S��?�� �&�*O��� �ɣ�=Ò]�QO�� �o� |�<�?�� �&�x�Iv-i��Ē}�0�H�e��q�}��W!��;l+�8 ���?�f�R�7� �MT���� �G<{�$������Ao������楳����ʡ�W<�pOC���]8L���@d�:��6��(�[��dq���s.��b�S���H�,�ҝ��r0qקOJW�,�~M�����	��$�b��G�UF��yV$�N
�m��n��te[36eW+�c��88�y��wYv"IDIm#,O�ou1��#�JzOd$�͕������s�#���f���儗b�����~���Dt��̱�Jh ˷*wc�1�h�]Ö]��=�IZ ���N�����?�%�圮�y:3Ip����5+h1��2\c��������:���1��>�s����=Ò]�	q���$3EBZ#���Ε�~�O�����SC����R�ۜ�G�<�*C�A�1,���W�s�1���]Ö]��T[Q#]�0J��#��\��p]i)Չ���G��Am��ު�Izo��q�ӿj��&)]6	U~}�$g���R�p�`{�9� 6Ѥ`H���`c�Ϩ<�[i-�m�!n��N:�<����
4�3!p�^	�V�J�줘��E��t�;w��wYv$[�an�ܹw����9$1��O-�$��*Ȣ �<���`09��ӽAc���#beFfe,v�\c�<�;��ǀ�<�]���7OJ9��9%؅n��c�e��H�v�|��s���O{�4F�a�Ɏ1���� ֨�4�V�ƒ��D�>�W�8�GJ�ik����E���'?�����%��Kve�iV;��{ 秹�����|�u�����,��_��� ֨��A%Ӊ`�TDf
7ӭ��>Iv(Q[Qi 5��Q��lI蠩9N��4E{ty^T�nY02O<z|˸�e�Ǣ�]�bs��O�W����Қ�� �/gkv�<ͨ�9�9�����.�:)�T���� �G�'��������a�S��?�� �&�*O��� �ɣ�=Ò]�QO�� �o� |�<�?�� �&�x�IvE?ʓ�y���h�� �o� |�9��9%�e� *O��� �ɣʓ�y���h�p�a�S��?�� �&�*O��� �ɣ�=Ò]�QO�� �o� |�<�?�� �&�x�IvE?ʓ�y���h��=Ò]������ѽ��:m�7g�����?������E�Y���?������E�Y���?������E�Y���?������E�Y!����4g(O ��H����a�(���
dW1�L���f����ls��v����%�f/��,-�<{�Yđ���pI�)���`dݜ���8�SnZ�T���y��L��ʴ��;�8<q��{S�����!{��[����m.7�YPc_�j��	�C�.$��S��y.w����֚�2-��=y��I�]n5�g��M����.�I���~��Er����P�w�z����`N[�P���"E��ge��8�x�T�/��|���>\�4�]~L�m�Q������ ��n$�
����C�`c�]��� ��jF��%K3����펜�*W%��O��XSi?����вg u?JQ���ndPH-�� 릉/U��]�t�C��R4�~Q%�]�p ��^�^�T���44@� >e\+��A&�{im�rL�c*\���p;�M=��6wN�#�ӭ]]`�����C�M��R�6����dIw�������9�S�~�3�w��3�0~�㷥j�� ��1����ޮ�ڢ�Xe�M8q�G���O�L�L�������*5��2�nN��=)�^^(�8q�p9��\�{�)���{X\�"������#�Ϊ�$�E�6�>c���K���Dw}�NF�:㊂99DbNA�����ە;{ָ`H'�h�ަ��I'���VW4�� 5���f�{z�m]�������ѽ��:m]�������ѽ��:m]�������ѽ��:m]�������ѽ��:m]�������ѽ��:m]�������ѽ��:m]�������ѽ��:m]��������M����
*��� =?CG��� ������[��c��?ξ�MEC��?��h�\���4}^��?�>�G��މ��~����k��z~�����G��(� :��5��� �����p�O���z�������_z&���\���4}����>�[��X����F���m�X�A��}�^���g+�����ozQ��1J#� ���6��ZH�t�������'�����-o������D����@
߿#����5bH,�#�0�7��'��P}��,O�!Yذ ���3M]F�E>z3�L#W#4F�t�o���7_ծ�� �;�FU*��6��9�aҢ�e������1o,�e�dȜ�P���v���,8�&	>[m��*eG����}b���d���D�G� ��t�-�P�k���ޑ�� C�L�ԭ&7'�(7��p=�1���]��a�8㎹�t1������ ��J5R<��O���H��?$���q�秭55(�_�C
�ڹ$c��=A�˪���J�� ��i�֏c^������K}��Xb��7��[����H��Zs�Xn����;�Z�Uw��,��'#��?*i�4��ǒ�~dc������z�? �����X�?s�_�Ooj�Y��d��c+���'=@��#&<����� ר����]-0� +�6���?�(�E����a̳���0�l�I��	'=��f�8m���ҠMB�(!���pd?/9��J5-1dC������q��?�T�_�'[w��5n�B�~VL����5�+�kϖ�'��TɯlNQ�HU0`� ?J��������. �w�����_�����4#���ܧE^�~�w$���s�g�E�� ��r2���#������^�,m'ԭEZ7�`Y<YppZ7�z�i��Li@���#��g#֏�U�]�ܧE\�QӍ�4��NÀ}�������e|��e���}J�o�>�K��S�����H���+�!���ϥ%��{��0d�P��?�j^����)b�;��U���)(e,m������5=�t�#9ge�l6�q�Ʃમ��+I�ʴSn'��f�g�r��Q�������/���e�����ɨ�~����k��z~�����G��(� :��5��� �����p�O���z�������_z&���\���4}����>�[��X����D�T?k��z~����� =?CG��#����}蚊��p�O��G��#����}�̢�+ꏓ
(��
(��
(��
T�T�$>���by�X����5�����/���j�x'-��i���>�[����A'�8�ɦ�"��.FY�d���|P���u���z�� ]n ��~@9��皡ej�(��B� 9�m���gp$�6e�۸�N���V^��h�BW$Q�:���itXɖi��@z�ǵCy��2��9P����=2;��g���#+0��f�:E�4j�8�����(��}��y�Ɍ�`?:ӱ�RO-�2r��ARA�^��T�M�\&b,c�1�|�(���;t���ͬ������ĺR���e���  `�s��yq��Ȧ?+k*�#�:���`KH�8���ڭ�D��Dze�$��>�J��͎�Ҁ5�h�aK~��2}q�:������'��&H�b{{q���7�:��S��������Z]G�3����� ��ր.�����y�6;O��ޣm.(��/r�*��c��y�sU���$ʛ�;YFNN9���Iu`m�TYVF�0ﷰ�8�f�/I�X���2q��5N��-��20#���Gj�l�̊r$H�����>��֞�lЇ�� w�g^��#��oj�-�iN3:Uz�4�ÌF9�ÏL�uMՑ�Xa��A�h(�� (�� (�� C�Z^��E QE QE QE QE QE QE QEc�7���t}���y�δsFk��ӫ�_3��̥�� _#;�7���t}���y�δsFh�ӫ�_0�̥�� _#;�7���t}���y�δsFh�ӫ�_0�̥�� _#;�7���t}���y�δsFh�ӫ�_0�̥�� _#;�7���u%���s�E��s�?Z����Q�!v��N3MfU[�!<���o�� "��ZXw:�;F�S�j�h�c�IbG��E�?�j%�(�Bɲ)7�#�J!��9Y��c�+_�	y� gC��2Y5y[n]���:c��wGT#��j�IS����RJ%�!�0Aۿ��n�Ne>k���)hO[�������mnY��A!t��*����ޥ#V�qIU� �S�i6��dT���<�{c�jk^Ǻc���W
O ����*�`��ğ����k&�R�Τѩ%OjY��L0E=��@��$��� ֭Ԥn�<��;����!��?(��$����Aߧ����n�z� #(EQ��3�
C��e�� SX�n�̹e#kl\�:㧵l>��`#� �U�y��W����/�:{Q���}����=vz� #I�(PB��Z�{j$�U�2�(��m�=+R��n��X8+��'=��#�[u�5������}�/�	_���?��[��W��5)ZQ8�CF\|�ϯqQH����_2d�� ^3���u@�+�m�C�g�ƫ�y�Bɵ��2ź��x�C�%ѯ�� �,�/{���������n�A�;B�:{�R�jL�Z5f�V�)��?Z׏TE�W0���9a�=��WVUR<�nI��9�c�O�������=vz� #)[Vdm�6(�.����,�"{��׾��I	*�1q���N�S�RR��dP�݇� �Ƿ�K�B]�������G>4��@�OOzq��B6���A����VS��Vs�����$j���(��cך?�u���:=�޿������==�:}��a<����uU
 ������`��$�)l �s�����}����>=�޿��~�s� <��J4��p!$��tL���M*F�$��Xq�Jx�B�U�� ���rx��%��V���`��tO�_�s�M�
�B�Fs�R��#[�|���K�ݝ�'��T��� ��1;2�8�?Ώ�	w_�� ��n�z� # ��@�a`GjAarzDk�{��n���8_ӥG��9 �sH 7郟J_������5�B������O�\� �#Nm2�6���p�"�A���&��r*�d�:T1�F�� �c�ϧ�T���������z� #������h����?�i���f � zSsX<ίe�|�� �)w���a�� �g��7���u��3G��^�����e.����a�� �g��7���u��3G��^�����e.����a�� �g��7���u��3G��^�����e.����a�� �g�sE�u{/��ٔ����QEy��QE QE QE =����ݙ�ɼ���9ǵAV,���#.���3�p�2��3�����,"0����<��+=I}�;]�$�����0G*+����*�����Q%�46�	fr	(p��z�&�%�#�f����e�:�2pǁ韥�<�������5,VfE����a�=O�Z��Ud�?��9�֡S��"�R	ٲ��� � = �V��,��*2� ��Kol�]j�����K.�Yp*��Ⰷ,�K��a�)Þ���%����Qd�0ɏ�����:�)�rw'Lu?�?*�[%_8��J�NAjC���m��+mR�׌���Z%?�L͸3_�!��	�E�Fwm�*u[���Hr܌��9�m?�G��nېO' tϽ2��DW�Ţpp2O�.Y)|+Q�E��z��|�l���s`��𪲤��e�ZV�8N9?�?�k��9b8q�ݪ+kuw��I1R #�9�JWzr��Í�������ݶ"�S~wӶkB�-�Hd�g!���A q�Tq��]�I��Us�'�K��&�0��ᗠ�N.��a9�J��B���$�PoC�,N�0�UC	`��Lo%J�s���*�F�kɐҢ:�#<�җ�=Y @���g��u��Od�J+w�n6d�5��#R1���~�8h�;�ěN�X�;v�{Ti�+J�a�*X�p�w<�=*�͡�h���~��8���������^�7��"�#i��������&�y��	����Ln�=iF�I��Js�y8����f��	f!@G�� Z��һ�y��-*���cϮEU�02!��8�����U���[�YƝ�����͸U���e���(��n�X(����Q@Q@Q@Q@Q@Q@Q@~������� q� J�E}�}߉��8�� ���� q� J>����Q��C���8�� ���� q� J>����Q��C���8�� ���� q� J>����Q��C���8�� ���� q� J�c?�ed��0B���Y5��E)�Q]�Jm+� ��
��Я��5�q�#Gu9�RA���4��b�C+�E?C����Oa�%� �QM�Q��Z�
�P|����/�zS��ߋ��~� ���J���n�~T,[��?�5��KA33s�r����Y��2F���������ӎ�r̒�(�v_,R}�� Z>�Gk~!��_{���� J�3f[w��O�皉�eq.���`�$�j���Ж�>@F3���]]e� �s'�̱�9���f��h��C�����.�-��GB|�?�_҈�$od�ȓ�I�zp?Ȭ�� 
�� " n�'��	�ʎ0��Kq�W�����(v�C�B���V�p2�#%N�X�3���ǭ$os,q2C)FB�}�N��D�\�.Z��cC��9=�)���B�'X���A�c��}F�o�?�+��\Oj�+�*���8�����=��$�)6��� �ߎ�BK���%�>Y94$}~���P���!R�!�0X�x8�&������_���B+�\i���=�=�C'�2ʮ0�-��3�b�-(����"㑌�g��΍�w���{v��;~!��_��Z7����|����:���8��2+(Vvwq�A�Zˉ'���ԃ���COK��vȭ�8�D0r1��G�h��XhW��L.���K�$�#��Cs;[2��X����@u��c0��#� ����{��E`�@;U��Q�
��Я��4�����(���q�N�}�+�^Ԡ�7��?֫6�q��x�1���t_)a�G����ܽ�<݅����{��
��Я��5�1��eR|�X.G�$�n��2��@�2���g�ڨ�����y4L<��@��8>��O�Q�S$S�[�v����s��ig�����G��.t��6�P�;�Мg�O�P��tVu�|c�z~uחWPJ�mUG�b� 9Q�j�+�>V��eY'�c���>�o�?�q� A��4��b�G"���H�6�U`���(H�'۞z��7a��
�&�;�8�Ӛ��+,�Fʅ�p8�_l���;~!������&(�iQёC ��ps���kw)��d�̓n0�����>��8N����ۯ��I���d��(��<>�����u��>�o�?�q� ķ>V�2)z�\�z�Ϸ����*��Jn
6�A�q��gޣ��>�o�?�q� C��� q� J>����Q��C���8�� ���� q� J>����Q��C���8�� ���� q� J>����Q��C���8�� ���� q� J>����Q��C���8�� ���� q� J*�g������#��U���� ��G�s� �D�~�C����_�JtU���� ��G�s� �D��t?�>�_�JtU���� ��G�s� �D��t?�>�_�JtU���� ��G�s� �D��t?�>�_�Ju-����m�S�ӃS� g?��JUӛp�"m�8���t?�>�_�K?��+��H�2k�k���dzqMmR1i�B��?&X�?.��K/�h���ޮA�C�3@ۦ�O�G��})�U��xZ�x��Z�Y�F�7������Q����𷚢C�|�� :`��ͧY��b��/ə�ܤ�q���4��(Ҭb�;���8,1����/��V�S2�_�E:���`�][�Ӿ�lYY�������1��]+N
��I d��N9�~���[=Ͳ��W!��8���G�)0��[�L�F�;��#���s�1��QOz���2 U�
�x��v{f�nLM�s��l~^�r�E�q��b�IX�=A�dP�4���𵣼Jj1%�q��S�R���+��ϧj�]f$��)�:l)������~�4�U��N$U�(w*�HF�z���̗�Ha$�!~����G�h����Z����Z3*�(��P�0r ����j0�$.A��N>��]>�+}��l��	�M���%~��G;������a�N�����=���}�2�$�H��N8�Gov"����\�W��8��h���I���و}���~�*�x
�$�ϰϯJY�����o�#k�V�I';�A��1��⬾�]Ap� �w��)�m�[��4pp��l秽[�ϳ�<N׏t�w79�SX�/i��Zr�m�T��x��U]SR��L&�p|�c ��u�s.<�"��i�!���:ƒ&��O6Tߌ�ٸ�=�;���_�V���u��P�����w`���T��S[j��mM3*�y�j��m�UH9�X�rW�1߽@�o���+L7mڡ��P�4W������`��&GY��(1��%ڴr/���]��JׇJ�ٳ$��o�����)���w��pTJN:���?�R�a}Z��z�
ef���1uV+�~�� �"*O��UYU���ߎǚu���7�,�/��S�����t�2>H��8������S��?�W�+� (�Ձi�L� 3�"��P+�=8�_Z��H�Hy^~_�?�R�Γ�z%��� =���0��_�E���A��`W %u�`{w��Q����vvmBWn�w�����g?��J>�C���u� ���mQ��O݀��� ,�{�B+�e�"d!�d��_N�j?��� ��G�s� �D��t?�>�_�JtU���� ��G�s� �D��t?�>�_�JtU���� ��G�s� �D��t?�>�_�JtU���� ��G�s� �D��t?�>�_�JtU���� ��G�s� �D��t?�>�_�JtU���� ��E[����:� �]��+�Ϩ
(��
(��
(��
(��.%�x|œ'f��N{��h����J���F���3�R�W$�  *Q�8iH݌o>�k�*-]D�����}������_,=��*Y4Ɗ3!�H�Xcpշ�q���v����u��L^Ha�?7�?�Ù��D�Z�ڙ��#����;���A����[��n����ɉg/ߧ?�F�,��$��x�~o�����-��-M�*ჺ��,��?AȦ�@ŦeT'���?:�Ƚ/���#g��h1_*��������w�d�m��"���������K����i�.N�=q���Uǜ��',c�vC���
H��.Lm#�C*o����ol��ц۴$��ڭ.�<�V�s�v��r?���b �"6=����ʸu��R�fp7��s�QQZ8݄���VB����i�ob��ۭF��.p���}Җ	e�Tny��T�� �I��2G�X�9�=�>V�*)s&���C�>���g+V���<т��B��8���T�vGeM�zc���'��f[��V�t��ӌl��2�֒$m0�)!˱��n[?���U�3���u�?Q�Wͻ��😚�C+y�/˝�>�*�/�	s?�O��������Q����G`c.�) ���F�x��3�Ɂ��aOHoc}�7%A�O#����!�u��Q�ʅ�kq��d`T����7��$|�$��c�@�D,��`D2)�EI��!H�2�[�\��JKI�rm�	6���	^PT�m�J�������]�c��i�9�����	9��K"�1.��f s�Cqo�Z~�%$��>Q4{��Nq��L���ę��*���t)�Xt�JO!�R	�<�/P�r�����C�!XdUP�v啺���)�2�t�7�H��R�Y$rߘ��=?:q�p�X �V2�rA�Gn���v�J�Z*{�I`gV���������m�)V�8"�qq�)'�ET�(�� (�� (�� (�� (�?o�濝o�濝wg�;~'������U����_Ώ����_Ώ��Go�?�0�� ^����� ��t}�� ��tg�;~!�����2�G��� <����� <���?���?����?o�濝o�濝���߈ha��Ӷ�ky7��8�:{��{1���cd����~r=���� <���Ȗ�$h��
Ww�� t?Ҷ����s�aۺ��'{�_5�#!��ya�`����UU0m d`zu�S]�/�H_<d�����ǮƢ=���~cQ���늯�W�G�p���� "o�3ʬ���#����Ȝa���5!� �XO�3� �d`���R� u�ܪ��;��Un� �S,w��GAy}�ȯ���ZEf	�0x�4�0��h�bm��z��P��m�.-�y�%��i���fh�ew��QpF�z��u����hoa-�iuU.΅zlsI|�)��=jH��ue&)2v������Z�o���A���Y}B��� hP��&�P��MX���|B8����Pu8�Mg�j�K��7��s���5h�v�Uq���U� �*q��N�>�V���9vz�,8=��g���=�p⢃S��N�B�]�~��h���3�R=����7�Q��[�Ӕ�,�3:�f+�_Jc_;l;"0����{S?��^!b��E�����r��������O=kO�Wh��a�BE�&H�lq�d��8�#�$�3N��%q�O�N���{�y5�����U�%d�ǽ;�n��fDr������w�������\��\W;�wz�=Nd�R.��=I���M���|�cr�7��a�Q���F�� �5����Gԫ&�a��Y����#U�)P#
H8 �G��P[Hc�Y
������i����[; ����F��r���X�>lr9�
�i�{�PI��p��IB��A���S�;��ϡ��Z�A*�O�iS�q����������(:� {�z�����aF�ɛQ���I;Oc����E�+�f?49���5נwR�8Tv*��>�J�U3L�(���q�1��`���v��5P��UC��A�'8�=�H4q0lu��ӽR����M&�$AԒ���8�Sk���������d��GԱ����:�!d�iĤc���������ʪ��Vw���y��G���y��Y<%�h��e��z����� �k����� �k��� g�;~%ha���EQ�{� �5���{� �5�����v�C�C��e�*����y��G���y��G�~#���~� �/QT~�� ��:>�� ��:?��������z����� �k��G�~#���~� �*QE�gͅQ@Q@Q@Q@Q@����,%�6�W�-���s�jT�Ӷ.��rq*�����k��4/�XA��J3+7��y��w����`��K��ɒ|�מ1�8��x�tlU����$�4��F,ާ� l%����a+(��.�8�q֙�����Ė���1��'�=�h��p�-@���݇�z���"+�<d`�6}�d�q,k�9]W�** ���P�����px�
ʵ���M&��)ޠǵf�Z��g'~~m�)��y��U���
(��U���,��Ԛ|��@H�6\�z���O*d����8�GJ���BD-�������c���͍�66G_��JѺ�+#_�6�+Q��Ѳ[$	q�~��k#t�a��^N�r:c�8�[+!����T�2,�n��"�c$��g�j (�� (���q�z �F� QE QE QE QE QE QE QE ^��F�(��F�*��?]�7�}?԰� ���>���ѿJ>���ѿJ�E]�7�R�� /�T��F�(��F�*�}w�ߐ}K���S�	� =���	� =��tQ��G�~A�,?��eO�'��oҏ�'��oҭ�G�q��԰� ���>���ѿJ>���ѿJ�E]�7�R�� /�T��F�(��F�*�}w�ߐ}K����k(��?�`���09�_ƥk{u��!2%��#;�f�������?P�ػ��}�9�4�������i�i�����r�p�c��J)� hV�/���f�Ob�#G�I.� l��y�)m��襍��$s����0?��T��B�g��-��!�ֹ�P�E#��{U���R0�#2Cr1��Uԓ�4�}~��/��簇�*F�����}�?�~�n����?����� ���� ���Q�� ���U�)}w�ߐ}K���S�	� =���	� =��tQ��G�~A�,?��b�X� � i%�#��w���-cg���O�RQU���j/�a��G�w��7�� ר��DҴ��v#��n��(��~��?򖅝�s��L��u �� �߁��YDl��t��ʠv#�QM��>�X
�X���������ء���n\�z��R��{|_���B� 	S�	� =���	� =��tT�w�ߐ����_̩�� ���Q�� ���U�(��#�� ���2���z7�G��z7�V�븏����X��ʟ`O��ߥ`O��ߥ[����?���a� ��*}�?�~�}�?�~�n�>���o�>���_̩�� ���Q�� ���U�(��#�� ���2���z7�E[����?���a� ��
(�����( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��( ��(�ن��~2N�kk����ޢ��#   GBMB