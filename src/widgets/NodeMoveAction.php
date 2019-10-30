<?php

/**
 * @copyright Copyright &copy; Arno Slatius 2015
 * @package yii2-nestable
 * @version 1.0
 */

namespace kilyakus\nestedsets\widgets;

use Yii;
use yii\base\Action;
use yii\db\ActiveQuery;
use kilyakus\nestedsets\behaviors\NestedSetsBehavior;

class NodeMoveAction extends Action
{
    public $modelName;

    public $columnId = 'id';
    
    public $rootable = true;

    private $leftAttribute;
    private $rightAttribute;
    private $treeAttribute;
    private $depthAttribute;

    public function run($id=0, $lft=0, $rgt=0, $par=0)
    {
        if (null == $this->modelName) {
            throw new \yii\base\InvalidConfigException("No 'modelName' supplied on action initialization.");
        }

        /* response will be in JSON format */
        Yii::$app->response->format = 'json';

        /* Locate the supplied model, left, right and parent models */
        $model = Yii::createObject(ActiveQuery::className(), [$this->modelName])->where([$this->columnId => $id])->one();
        $lft   = Yii::createObject(ActiveQuery::className(), [$this->modelName])->where([$this->columnId => $lft])->one();
        $rgt   = Yii::createObject(ActiveQuery::className(), [$this->modelName])->where([$this->columnId => $rgt])->one();
        $par   = Yii::createObject(ActiveQuery::className(), [$this->modelName])->where([$this->columnId => $par])->one();

        /* Get attribute names from model behaviour config */
        foreach($model->behaviors as $behavior) {
            if ($behavior instanceof NestedSetsBehavior) {
                $this->leftAttribute  = $behavior->leftAttribute;
                $this->rightAttribute = $behavior->rightAttribute;
                $this->treeAttribute  = $behavior->treeAttribute;
                $this->depthAttribute = $behavior->depthAttribute;
                break;
            }
        }

        /* attach our bahaviour to be able to call the moveNode() function of the NestedSetsBehavior */
        $model->attachBehavior('nestable', [
            'class' => \klisl\nestable\NestableBehavior::className(),
            'leftAttribute' => $this->leftAttribute,
            'rightAttribute' => $this->rightAttribute,
            'treeAttribute' => $this->treeAttribute,
            'depthAttribute' => $this->depthAttribute,
        ]);

        /* Root/Append/Left/Right change */
        if($this->rootable && $this->treeAttribute && is_null($par) && !$model->isRoot()){
            $model->makeRoot();
        } else if(is_null($par)){
            if(!is_null($rgt))
                $model->insertBefore($rgt);
            else if(!is_null($lft))
                $model->insertAfter($lft);
        }else{
            if(!is_null($rgt))
                $model->insertBefore($rgt);
            else if(!is_null($lft))
                $model->insertAfter($lft);
            else
                $model->appendTo($par);
        }

        /* report new position */
        return [
            'updated' => [
                'id' => $model->{$this->columnId},
                'depth' => $model->{$this->depthAttribute},
                'lft' => $model->{$this->leftAttribute},
                'rgt' => $model->{$this->rightAttribute},
            ],
            'responseText' => [
                'type' => 'success',
                'message' => 'Record moved successfully',
            ]
        ];
    }

}