<?php
namespace kilyakus\nestedsets\widgets;

class NestableAsset extends \kartik\base\AssetBundle {

	public function init() {
		$this->setSourcePath(__DIR__ . '/../assets');
        $this->setupAssets('js', ['js/jquery.nestable']);
		$this->setupAssets('css', ['css/nestable']);
		parent::init();
	}

}