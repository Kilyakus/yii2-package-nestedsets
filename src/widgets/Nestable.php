<?php
namespace kilyakus\nestedsets\widgets;

use Yii;
use yii\db\ActiveQuery;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

use kilyakus\helper\media\Image;
use kilyakus\web\widgets as Widget;

class Nestable extends \kilyakus\widgets\Widget
{
	const TYPE_LIST = 'list';
	const TYPE_WITH_HANDLE = 'list-handle';

	const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_COPY = 2;

	public $type = self::TYPE_WITH_HANDLE;

	public $dragAllowed = true;
	/**
	 * @var string, the handle label, this is not HTML encoded
	 */
	public $handleLabel = '<div class="dd-handle dd3-handle">&nbsp;</div>';

	/**
	 * @var array the HTML attributes to be applied to list.
	 * This will be overridden by the [[options]] property within [[$items]].
	 */
	public $listOptions = [];

	/**
	 * @var array the HTML attributes to be applied to all items.
	 * This will be overridden by the [[options]] property within [[$items]].
	 */
	public $itemOptions = [];

	/**
	 * @var array the sortable items configuration for rendering elements within the sortable
	 * list / grid. You can set the following properties:
	 * - id: integer, the id of the item. This will get returned on change
	 * - content: string, the list item content (this is not HTML encoded)
	 * - disabled: bool, whether the list item is disabled
	 * - options: array, the HTML attributes for the list item.
	 * - contentOptions: array, the HTML attributes for the content
	 * - children: array, with item children
	 */
	public $items = [];

	/**
	* @var string the URL to send the callback to. Defaults to current controller / actionNodeMove which
	* can be provided by \klisl\nestable\nestableNodeMoveAction by registering that as an action in the
	* controller rendering the Widget.
	* ```
	* public function actions() {
	*	return [
	*		'nodeMove' => [
	*			'class' => 'klisl\nestable\NestableNodeMoveAction',
	*		],
	*	];
	* }
	* ```
	* Defaults to [current controller/nodeMove] if not set.
	*/
	public $url;

	public $dataProvider;

	public $filter = [];

	/**
	* @var array options to be used with the model on list preparation. Supporten properties:
	* - name: {string|function}, attribute name for the item title or unnamed function($model) that returns a
	*		 string for each item.
	*/
	public $modelOptions = [];

	public $columns = [];

	public $actions = [];

	public function init() {
		if (null != $this->url) {
			$this->pluginOptions['url'] = $this->url;
		} else {
			$this->pluginOptions['url'] = Url::to([$this->view->context->id.'/nodeMove']);
		}
		
		parent::init();
		$this->registerAssets();

		Html::addCssClass($this->options, 'dd');
		echo Html::beginTag('div', $this->options);

		if (null != $this->dataProvider) {
			$this->items = $this->prepareItems($this->dataProvider);
		}
		if (count($this->items) === 0) {
			echo Html::tag('div', '', ['class' => 'dd-empty']);
		}
	}

	public function run() {
		if (count($this->items) > 0) {
			echo Html::beginTag('ol', ['class' => 'dd-list']);
			echo $this->renderItems();
			echo Html::endTag('ol');
		}
		echo Html::endTag('div');
	}

	protected function renderItems($_items = NULL)
	{
		$_items = is_null($_items) ? $this->items : $_items;
		$items = '';
		$dataid = 0;
		foreach ($_items as $item) {

			$model = (object)$item;

			$classList = ['dd-item', 'dd3-item'];

			if($item['children'])
			{
				$classList[] = 'dd-childrens';
			}
			if($this->dragAllowed != false)
			{
				$classList[] = 'dd-allowed';
			}

			$options = ArrayHelper::getValue($item, 'options', ['class' => implode(' ', $classList) ]);
			if(is_array($options)){
				$options = ArrayHelper::merge($this->itemOptions, $options);
			}else{
				$options = ['class' => implode(' ', $classList) ];
			}
			$dataId  = ArrayHelper::getValue($item, 'id', $dataid++);
			$options = ArrayHelper::merge($options, ['data-id' => $dataId]);

			$contentOptions = ArrayHelper::getValue($item, 'contentOptions', ['class' => 'dd3-content']);
			if($this->dragAllowed != false){
				$content = $this->handleLabel;
			}else{
				$content = '';
			}

			$id = $item['id']; //id item

			//create links (GridView) for viewing and manipulating the items.
			if(is_array($this->actions) && ($dropdown = $this->actions['dropdown']) && $this->actions['dropdown']['items']){

				foreach ($dropdown['items'] as $d => $ditem) {

					if(isset($ditem['label'])){
						if ($ditem['label'] instanceof \Closure) {
							$ditem['label'] = call_user_func($ditem['label'], $model);
							$dropdown['items'][$d]['label'] = $ditem['label'];
						}
					}

					if(isset($ditem['icon'])){
						if ($ditem['icon'] instanceof \Closure) {
							$ditem['icon'] = call_user_func($ditem['icon'], $model);
							$dropdown['items'][$d]['icon'] = $ditem['icon'];
						}
					}

					if(isset($ditem['url'])){

						if ($ditem['url'] instanceof \Closure) {

							$ditem['url'] = call_user_func($ditem['url'], $model);

						}elseif(is_array($ditem['url'])){

							$ditem['url'] = ArrayHelper::merge($ditem['url'],['id' => $id]);

						}elseif(strpos($ditem['url'], '?') !== false){

							$params = [];
							$query = parse_url($ditem['url'], PHP_URL_QUERY);
							$path = parse_url($ditem['url'], PHP_URL_PATH);
							if($query = explode('&',$query)){
								foreach ($query as $param) {
									$qitems = explode('=', $param);
									$params[$qitems[0]] = $qitems[1];
								}
							}
							$ditem['url'] = ArrayHelper::merge([$path], $params, ['id' => $id]);

						}else{

							$ditem['url'] = ArrayHelper::merge([$ditem['url']], ['id' => $id]);

						}
						$dropdown['items'][$d]['url'] = $ditem['url'];
					}

					if(isset($ditem['linkOptions'])){
						if ($ditem['linkOptions'] instanceof \Closure) {
							$ditem['linkOptions'] = call_user_func($ditem['linkOptions'], $model);
							$dropdown['items'][$d]['linkOptions'] = $ditem['linkOptions'];
						}
					}

					$dropdown['items'][$d]['linkOptions']['data-key'] = $id;
				}

				// $spanView = Html::tag('span', null, ['class' => "glyphicon glyphicon-eye-open"]);
				// $aView = Html::tag('a', $spanView . '&nbsp; ', ['title' => 'View', 'aria-label' => 'View', 'data-pjax' => '0', 'href'=> $this->viewItem .'?id=' . $id]);

				$dropdown = Widget\Dropdown::widget($dropdown);
				
			}

			$links = Html::tag('div', $dropdown, ['class' => "actionColumn"]);
			$item['content'] .= $links;

			$content .= Html::tag('div', ArrayHelper::getValue($item, 'content', ''), $contentOptions);

			$children = ArrayHelper::getValue($item, 'children', []);
			if (!empty($children)) {
					// recursive rendering children items
				$content .= Html::beginTag('ol', ['class' => 'dd-list']);
				$content .= $this->renderItems($children);
				$content .= Html::endTag('ol');
			}

			$items .= Html::tag('li', $content, $options) . PHP_EOL;
		}
		return $items;
	}

	public function registerAssets() {
		$view = $this->getView();
		NestableAsset::register($view);
		$this->registerPlugin('nestable');
	}

	public function renderContent($partial, $arguments) {
		return $this->render($partial, $arguments);
	}

	protected function prepareItems($activeQuery)
	{
		$items = [];
		foreach ($activeQuery->all() as $model) {

			$content = [];
			
			$icon = ArrayHelper::getValue($this->modelOptions, 'icon', 'icon');

			if(isset($model->{$icon}))
			{
				$icon = (is_callable($icon) ? call_user_func($icon, $model) : $model->{$icon});

				$icon = Html::img(Image::thumb($icon,22,22),['class' => 'img-rounded']);

				$content[] = Html::tag('td', $icon, ['style' => 'width:30px;min-width:30px;']);
			}

			$name = ArrayHelper::getValue($this->modelOptions, 'name', 'name');

			$width = 320 - (
				($model->depth > 0)
				? (
					$model->depth * 30 - (!$model->children(1)->andFilterWhere($this->filter)->count() ? 30 : 0)
				) : 0
			);

			$content[] = Html::tag('td', (is_callable($name) ? call_user_func($name, $model) : $model->{$name}), ['style' => 'min-width:' . $width . 'px;']);

			foreach ($this->columns as $m => $option) {
				if(!is_array($option)){
					$column = ArrayHelper::getValue($this->columns, $m, $option);
					$content[$column] = (is_callable($column) ? call_user_func($column, $model) : $model->{$column});
				}else{
					if($column = ArrayHelper::getValue($this->columns[$m], 'name', $option['name'])){
						$value = (is_callable($column) ? call_user_func($column, $model) : $model->{$column});
						$width = ['style' => 'width:' . $option['width'] . ';min-width:' . $option['width'] . ';'];
						$option['options'] = !$option['options'] ? ArrayHelper::merge([],$width) : ArrayHelper::merge($option['options'],$width);

						if ($column instanceof \Closure) {
							$content[$m] = Html::tag('td', call_user_func($column, $model), $option['options']);
						}else{
							$content[$column] = Html::tag('td', $value, $option['options']);
						}
					}
				}
			}
			$status = ArrayHelper::getValue($this->modelOptions, 'status', 'status');

			$background = (
				$model->{$status} == self::STATUS_OFF
				? ' kt-bg-light-warning' : (
					$model->{$status} == self::STATUS_COPY
					? ' kt-bg-light-info' :
					null
				)
			);

			//style="margin-left:'.($model->depth > 0 ? '-' : '') . ($model->depth*30) . 'px;"

			$html = Html::beginTag('tr', ['class' => $background]);
			foreach ($content as $item) {
				$html .= $item;
			}
			$html .= Html::endTag('tr');

			$html = Html::tag('table', $html, ['class' => 'w-100']);

			$columns = [
				'id'	   => $model->getPrimaryKey(),
				// 'content'  => (is_callable($name) ? call_user_func($name, $model) : $model->{$name}),
				'content' => $html,
				'children' => $this->prepareItems($model->children(1)->andFilterWhere($this->filter)),
				'type' => isset($model->{ArrayHelper::getValue($this->modelOptions, 'type', 'type')}) ? $model->{ArrayHelper::getValue($this->modelOptions, 'type', 'type')} : null,
			];

			foreach ($this->columns as $column => $values) {
				if(!$columns[$column]){
					$columns[$column] = $model->{$column};
				}
			}

			$items[] = $columns;
		}
		return $items;
	}
}
