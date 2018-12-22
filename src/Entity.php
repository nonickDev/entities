<?php namespace Cvsouth\Entities;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Entity extends Model
{
    public static $name = 'Entity';
    public static $name_plural = 'Entities';

    public $table = 'entities';

    protected $fillable = ['id', 'top_class', 'entity_id'];
    protected $parent_ = null;
    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public function newEloquentBuilder($query)
    {
        return new EntityBuilder($query);
    }

    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new EntityQueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    }

    public function __set($key, $value)
    {
        if($key == 'entity_id')
        {
            if(static::class == Entity::class)
                return $this->setAttribute('id', $value);
            else return $this->setAttribute($key, $value);
        }
        else if($this->hasAttribute($key)) return $this->setAttribute($key, $value);
        else if(static::class == Entity::class) return $this->setAttribute($key, $value);
        else return $this->parent_model()->$key = $value;
    }

    public function tableForAttribute($attr)
    {
        $entity_class = $this->entityClassForAttribute($attr);
        return $entity_class::TableName();
    }

    public function entityClassForAttribute($attr)
    {
        $entity = $this;

        if($attr === 'id') return $this;

        while(!$entity->hasAttribute($attr) && \get_class($entity) !== Entity::class)
            $entity = $entity->parent_model();

        if(($entity_class = \get_class($entity)) === Entity::class)
            return $this->hasAttribute($attr);
        else return $entity_class;
    }

    public function getEntityId()
    {
        return $this->entity_id;
    }

    public function __get($attr_)
    {
        switch($attr_)
        {
            case 'entity_id':
                if(static::class === Entity::class)
                    return $this->id;
                else return $this->getAttribute('entity_id');
                break;

            case 'fillable':
                return $this->getRecursiveFillable(); break;

            default:
                $attr = $this->getAttribute($attr_);
                if(static::class === Entity::class)
                    return $attr;
                else if($attr === null && !$this->hasAttribute($attr_))
                    return $this->parent_model()->$attr_;
                else return $attr;
        }
    }

    public function id_as($entity_class_)
    {
        if($entity_class_ === null)
            throw new Exception('Entity::id_as() called without entity_class being specified (NULL)');

        $entity = $this;

        while(($entity_class = \get_class($entity)) !== $entity_class_ && $entity_class !== Entity::class)
            $entity = $entity->parent_model();

        return $entity->id;
    }

    public function model_as($entity_class_)
    {
        return static::GetWithEntityID($this->entity_id, $entity_class_);
    }

    // given attributes are optional and just for when the object is created but not yet saved to database
    protected function parent_model($given_attributes = [])
    {
        $func = function() use($given_attributes)
        {
            if($this->parent_ === null)
            {
                $parent_class = get_parent_class($this);
                if($parent_class == Model::class)
                {
                    $this->parent_ = false;
                    return false;
                }

                if($parent_class != Entity::class)
                    $entity_id_column = 'entity_id';
                else $entity_id_column = 'id';

                if($this->hasAttribute('entity_id'))
                {
                    $entity_id = $this->entity_id;

                    if($entity_id)
                    {
                        $parent_table_name = $parent_class::TableName();
                        $data = (array) DB::table($parent_table_name)->where(function($query) use($entity_id, $entity_id_column) { $query->where($entity_id_column, '=', $entity_id); })->first();
                        if(\is_array($given_attributes) && \count($given_attributes) > 0)
                            $data = array_merge($data, $given_attributes);
                    }
                    else $data = $given_attributes;
                }
                else $data = $given_attributes;

                if($data === null) $data = [];

                $this->parent_ = Entity::CreateNew($parent_class, $data);
            }
            else if(\is_array($given_attributes) && \count($given_attributes) > 0) $this->parent_->fill($given_attributes);

            return $this->parent_;
        };

        return $func();
    }

    public static function GetWithID($entity_class, $id)
    {
        return $entity_class::where('id', $id)->first();
    }

    public static function GetWithEntityID($entity_id, $entity_class = null, $elevate = true)
    {
        if($entity_class === null)
            $entity_class = Entity::class;

        if($entity_class != Entity::class)
            $entity = $entity_class::where('entity_id', $entity_id)->first();
        else $entity = $entity_class::where('id', $entity_id)->first();

        return $entity;
    }

    public static function CreateNew($entity_class, array $attributes)
    {
        return static::unguarded(function () use ($entity_class, $attributes)
        {
            $entity = new $entity_class;
            $entity->fill($attributes);

            if(isset($attributes['id']))
                $entity->exists = true;

            return $entity;
        });
    }

    public static function ElevateMultiple($entities)
    {
        $is_collection = $entities instanceof Collection;

        $elevated_entities = [];

        foreach($entities as $i => $entity)
        {
            $current_class = \get_class($entity);
            $top_class = $entity->top_class;

            if($current_class !== $top_class)
                $elevated_entities[] = Entity::GetWithEntityID($entity->entity_id, $top_class);
            else $elevated_entities[] = $entity;
        }

        if($is_collection) $elevated_entities = collect($elevated_entities);

        return $elevated_entities;
    }

    protected function elevate()
    {
        $top_class = $this->top_class;
        $current_class = static::class;

        if($current_class !== $top_class)
            return static::GetWithEntityID($this->entity_id, $top_class);
        else return $this;
    }

    public function getRecursiveFillable()
    {
        $cache_key = 'Entity__getRecursiveFillable__' . static::class;

        if(Cache::has($cache_key))
            return Cache::get($cache_key);
        else
        {
            $recursive_fillable = $this->fillable;
            $entity = $this;

            while($entity !== null && \get_class($entity) !== Entity::class)
            {
                $next_parent = get_parent_class($entity);
                $phantom = new $next_parent;
                $parent_fillable = $phantom->getFillable();
                $recursive_fillable = array_merge($parent_fillable, $recursive_fillable);
                $entity = $entity->parent_model();
            }

            Cache::forever($cache_key, $recursive_fillable);

            return $recursive_fillable;
        }
    }

    public function fill(array $attributes)
    {
        $immediately_fillable = [];
        $not_immediately_fillable = [];

        $fillable = $this->fillable;

        foreach($attributes as $key => $value)
        {
            if (!\in_array($key, $fillable) && !\in_array($key, ['entity_id', 'id']))
                $not_immediately_fillable[$key] = $value;
            else $immediately_fillable[$key] = $value;
        }

        if(\is_array($not_immediately_fillable) && \count($not_immediately_fillable) >= 1)
            // attributes given in case parent entities not created yet. this can occur when an entity is
            // created using the new keyword, given attributes pertaining to parents, but has not been saved.
            $this->parent_model($not_immediately_fillable);

        return parent::fill($immediately_fillable);
    }

    public function attributeEntity($attribute_name)
    {
        $attribute_name = preg_replace('/_id$/', '', $attribute_name);
        return $this->{$attribute_name};
    }

    public function save(array $options = [])
    {
        $this->fill($options);

        $static_class = static::class;
        $save_queue = [];
        $entity = $this;

        while(($current_class = \get_class($entity)) != Entity::class)
        {
            $save_queue[] = $entity;
            $entity = $entity->parent_model();
        }

        $entity->__set('top_class', $static_class);
        $entity->raw_save();
        $entity_id = $entity->entity_id;

        $save_queue = array_reverse($save_queue);
//        $level = 1;

        foreach($save_queue as $save_item)
        {
            $current_class = \get_class($save_item);
            if($current_class != Entity::class)
                $save_item->entity_id = $entity_id;
            $save_item->raw_save();
//            $level++;
        }
    }

    private function raw_save($options = [])
    {
        $query = $this->newQueryWithoutScopes();

        if ($this->fireModelEvent('saving') === false) return false;

        if ($this->exists)
            $saved = $this->isDirty()?$this->performUpdate($query, $options) : true;
        else $saved = $this->performInsert($query);

        if ($saved) $this->finishSave($options);

        return $saved;
    }

    public function delete()
    {
        if(is_null($this->getKeyName()))
            throw new Exception('No primary key defined on model.');

        if($this->exists)
        {
            $parent_model = $this->parent_model();

            if($this->fireModelEvent('deleting') === false)
                return false;

            $this->touchOwners();
            $this->performDeleteOnModel();
            $this->exists = false;
            $this->fireModelEvent('deleted', false);

            return ($parent_model == null) || $parent_model->delete();
        }
    }

    public static function TableName($field_name = null)
    {
        return with(new static)->table_name($field_name);
    }

    public function table_name($field_name = null)
    {
        $cache_key = 'Entity__table_name__' . static::class . '__' . $field_name;

        if(Cache::has($cache_key))
            return Cache::get($cache_key);
        else
        {
            if($field_name !== null && !$this->hasAttribute($field_name))
            {
                $parent = $this->parent_model();
                if($parent === false || $field_name === null)
                    throw new Exception('Could not find field ' . $field_name . ', reached the top of the parent tree for ' . static::class . '.');
                $table_name = $parent->table_name($field_name);
            }
            else $table_name = with(new static)->getTable();

            Cache::forever($cache_key, $table_name);

            return $table_name;
        }
    }

    public function hasAttribute($key)
    {
        $cache_key = 'Entity__hasAttribute__' . static::class . '__' . $key;

        if(Cache::has($cache_key))
            return Cache::get($cache_key);
        else
        {
            $has_attribute = Schema::hasColumn($this->getTable(), $key);
            Cache::forever($cache_key, $has_attribute);

            return $has_attribute;
        }
    }
}
