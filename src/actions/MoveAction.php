<?php
namespace kilyakus\nestedsets\actions;

use Yii;
use yii\base\Action;
use yii\db\ActiveQuery;

class MoveAction extends Action
{
    public $modelName;

    public $columnId = 'id';

    public function run($id=0, $direction, $count = 1)
    {
        if (null == $this->modelName) {
            throw new \yii\base\InvalidConfigException("No 'modelName' supplied on action initialization.");
        }

        /* response will be in JSON format */
        Yii::$app->response->format = 'json';

        if($model = Yii::createObject(ActiveQuery::className(), [$this->modelName])->where([$this->columnId => $id])->one())
        {
            $up = $direction == 'up';
            $orderDir = $up ? SORT_ASC : SORT_DESC;

            if($model->depth == 0){

                $swapCat = $model::find()->where([$up ? '>' : '<', 'order_num', $model->order_num])->andWhere(['category_id' => $model->category_id])->orderBy(['order_num' => $orderDir])->one();
                if($swapCat)
                {
                    $model::updateAll(['order_num' => '-1'], ['order_num' => $swapCat->order_num]);
                    $model::updateAll(['order_num' => $swapCat->order_num], ['order_num' => $model->order_num]);
                    $model::updateAll(['order_num' => $model->order_num], ['order_num' => '-1']);
                    $model->trigger(\yii\db\ActiveRecord::EVENT_AFTER_UPDATE);
                }
            } else {
                $where = [
                    'and',
                    ['tree' => $model->tree],
                    ['depth' => $model->depth],
                    [($up ? '<' : '>'), 'lft', $model->lft]
                ];

                $swapCat = $model::find()->where($where)->orderBy(['lft' => ($up ? SORT_DESC : SORT_ASC)])->one();
                if($swapCat)
                {
                    if($up) {
                        $model->insertBefore($swapCat);
                    } else {
                        $model->insertAfter($swapCat);
                    }

                    $swapCat->update();
                    $model->update();
                }
            }

            Yii::$app->getSession()->setFlash('success', 'Record moved successfully');
        }
        else {
            Yii::$app->getSession()->setFlash('danger', 'Not found');
        }

        return Yii::$app->response->redirect(Yii::$app->request->referrer);
    }
}