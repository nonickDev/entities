<?php namespace Cvsouth\Entities;

use Cvsouth\Entities\Facades\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EntityBuilder extends Builder
{
    public function setModel(Model $model)
    {
        $builder = parent::setModel($model);
        $this->query->setModel($model);
        return $builder;
    }

    public function get($columns = ['*'], $elevate = true)
    {
        $collection = parent::get($columns);

        if($elevate) $collection = Entities::elevateMultiple($collection);
        return $collection;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column))
            $column = $this->query->prefixColumn($column, true);
        return parent::where($column, $operator, $value, $boolean);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $column = $this->query->prefixColumn($column, true);
        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $column = $this->query->prefixColumn($column, true);
        return parent::decrement($column, $amount, $extra);
    }
}
