<?php
namespace klisl\nestable;

use yii\db\ActiveQuery;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\helpers\ArrayHelper;

use kilyakus\web\widgets as Widget;

class Nestable extends \kartik\base\Widget
{
	const TYPE_LIST = 'list';
	const TYPE_WITH_HANDLE = 'list-handle';

	const STATUS_OFF = 0;
    const STATUS_ON = 1;
    const STATUS_COPY = 2;

	/**
	 * @var string the type of the sortable widget
	 * Defaults to Nestable::TYPE_WITH_HANDLE
	 */
	public $type = self::TYPE_WITH_HANDLE;

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

	/**
	* @var ActiveQuery that holds the data for the tree to show.
	*/
	public $query;

	/**
	* @var array options to be used with the model on list preparation. Supporten properties:
	* - name: {string|function}, attribute name for the item title or unnamed function($model) that returns a
	*		 string for each item.
	*/
	public $modelOptions = [];

	public $columns = [];

	/** @var  string URL for update*/
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

		if (null != $this->query) {
			$this->items = $this->prepareItems($this->query);
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

	protected function renderItems($_items = NULL) {


		$_items = is_null($_items) ? $this->items : $_items;
		$items = '';
		$dataid = 0;
		foreach ($_items as $item) {

			$options = ArrayHelper::getValue($item, 'options', ['class' => 'dd-item dd3-item' . ($item['children'] ? ' dd-childrens' : '') ]);
			$options = ArrayHelper::merge($this->itemOptions, $options);
			$dataId  = ArrayHelper::getValue($item, 'id', $dataid++);
			$options = ArrayHelper::merge($options, ['data-id' => $dataId]);

			$contentOptions = ArrayHelper::getValue($item, 'contentOptions', ['class' => 'dd3-content']);
			$content = $this->handleLabel;

			$id = $item['id']; //id item

			//create links (GridView) for viewing and manipulating the items.
			if(($dropdown = $this->actions['dropdown']) && $this->actions['dropdown']['items']){

				foreach ($dropdown['items'] as $d => $ditem) {
					if(isset($ditem['url'])){
						$dropdown['items'][$d]['url'] = Url::toRoute([$ditem['url'],'id' => $id]);
					}
					if(isset($ditem['linkOptions']) && $ditem['linkOptions']['data-toggle'] == 'modal'){
						$dropdown['items'][$d]['linkOptions']['data-target'] = '#modal-nestable-' . $id;
						$dropdown['items'][$d]['linkOptions']['data-key'] = $id;
					}
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

			$icon = (is_callable($icon) ? call_user_func($icon, $model) : $model->{$icon});

			$icon = Html::img(\kilyakus\imageprocessor\Image::thumb($icon,22,22),['class' => 'img-rounded']);

			$content[] = Html::tag('td', $icon, ['style' => 'width:30px;min-width:30px;']);

			$name = ArrayHelper::getValue($this->modelOptions, 'name', 'name');

			$width = (320 - ($model->depth > 0 ? ( $model->depth * ($model->children()->count() ? 30 : 0) ) : 0));

			$content[] = Html::tag('td', (is_callable($name) ? call_user_func($name, $model) : $model->{$name}), ['style' => 'width:' . $width . 'px;min-width:' . $width . 'px;']);

			foreach ($this->columns as $m => $option) {
				if(!is_array($option)){
					$column = ArrayHelper::getValue($this->columns, $m, $option);
					$content[$column] = (is_callable($column) ? call_user_func($column, $model) : $model->{$column});
				}else{
					if($column = ArrayHelper::getValue($this->columns[$m], 'name', $option['name'])){
						$value = (is_callable($column) ? call_user_func($column, $model) : $model->{$column});
						$width = ['style' => 'width:' . $option['width'] . ';min-width:' . $option['width'] . ';'];
						$option['options'] = !$option['options'] ? ArrayHelper::merge([],$width) : ArrayHelper::merge($option['options'],$width);
						$content[$column] = Html::tag('td', $value, $option['options']);
					}
				}
			}
			$status = ArrayHelper::getValue($this->modelOptions, 'status', 'status');
			
			$background = 
				$model->{$status} == self::STATUS_OFF
				? ' kt-bg-light-warning' : (
					$model->{$status} == self::STATUS_COPY
					? ' kt-bg-light-info' :
					null
				);

			$html = '<table class="w-100"><tr class="'.$background.'">'; //style="margin-left:'.($model->depth > 0 ? '-' : '') . ($model->depth*30) . 'px;"
			foreach ($content as $item) {
				$html .= $item;
			}
			$html .= '</tr></table>';

			$items[] = [
				'id'	   => $model->getPrimaryKey(),
				// 'content'  => (is_callable($name) ? call_user_func($name, $model) : $model->{$name}),
				'content' => $html,
				'children' => $this->prepareItems($model->children(1)),
			];
		}
		return $items;
	}
}
