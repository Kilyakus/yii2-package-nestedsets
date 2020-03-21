<?php
namespace kilyakus\nestedsets\widgets;

class NestableAsset extends \kilyakus\widgets\AssetBundle {

	public function init() {
		$this->setSourcePath(__DIR__ . '/../assets');
        $this->setupAssets('js', ['js/jquery.nestable']);
		$this->setupAssets('css', ['css/nestable']);
		parent::init();
	}

}