<?php
namespace phpman;
/**
 * TestRunner用のモジュール
 * @author tokushima
 *
 */
class Testman{
	private $flow_output_maps = array();

	public function urls(){
		if(empty($this->flow_output_maps)){
			$entry_path = getcwd();

			if(class_exists('\phpman\Flow')){
				foreach(new \RecursiveDirectoryIterator(
						$entry_path,
						\FilesystemIterator::CURRENT_AS_FILEINFO|\FilesystemIterator::SKIP_DOTS|\FilesystemIterator::UNIX_PATHS
				) as $e){
					if(substr($e->getFilename(),-4) == '.php' &&
							strpos($e->getPathname(),'/.') === false &&
							strpos($e->getPathname(),'/_') === false
					){
						$entry_name = substr($e->getFilename(),0,-4);
						$src = file_get_contents($e->getFilename());

						if(strpos($src,'Flow') !== false){
							$entry_name = substr($e->getFilename(),0,-4);
							foreach(\phpman\Flow::get_maps($e->getPathname()) as $k => $m){
								$this->flow_output_maps[$entry_name.'::'.$m['name']] = $m['format'];
							}
						}
					}
				}
			}
		}
		return $this->flow_output_maps;
	}
}