<?php
namespace kilyakus\nestedsets\behaviors;

use yii\base\Behavior;
use yii\db\Expression;

class NestedSetsQueryBehavior extends Behavior
{
    public function roots()
    {
        $model = new $this->owner->modelClass();

        $this->owner
            ->andWhere([$model->leftAttribute => 1])
            ->addOrderBy([$model->primaryKey()[0] => SORT_ASC]);

        return $this->owner;
    }

    public function leaves()
    {
        $model = new $this->owner->modelClass();
        $db = $model->getDb();

        $columns = [$model->leftAttribute => SORT_ASC];

        if ($model->treeAttribute !== false) {
            $columns = [$model->treeAttribute => SORT_ASC] + $columns;
        }

        $this->owner
            ->andWhere([$model->rightAttribute => new Expression($db->quoteColumnName($model->leftAttribute) . '+ 1')])
            ->addOrderBy($columns);

        return $this->owner;
    }
}
