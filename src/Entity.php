<?php namespace Cvsouth\Entities;

use Cvsouth\Entities\Facades\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use Exception;

class Entity extends Model
{
    public static $name = 'Entity';
    public static $name_plural = 'Entities';

    public $table = 'entities';

    protected $fillable = ['id', 'top_class', 'entity_id'];
    protected $parent_ = null;
    public $timestamps = false;

    protected $loaded = [];

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
//        Log::debug($entity_class . " " . $attr . " " . get_class($this));
        $result = $entity_class::tableName();
        return $result;
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

    public static function createNew($entity_class, array $attributes)
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

    public function getEntityId()
    {
        return $this->entity_id;
    }

    public function fetchLocalAttributes()
    {
        $table = static::tableName();
        $entity_id = $this->getEntityId();
        $attributes_array = $this->fillable;
        $attributes_array[] = 'id';

        $data = (array) DB::table($table)->where('entity_id', '=', $entity_id)->first();

        foreach($data as $key => $value)
            if(in_array($key, $attributes_array))
                $this->setAttribute($key, $value);

        return true;
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
                else if(!array_key_exists($attr_, $this->attributesToArray()))
                {
                    $this->fetchLocalAttributes();
                    return $this->getAttribute($attr_);
                }
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
        return Entities::getWithEntityID($this->entity_id, $entity_class_);
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

                if($this->hasAttribute('entity_id') && ($entity_id = $this->entity_id))
                {
//                    $parent_table_name = $parent_class::tableName();
//                    $data = (array) DB::table($parent_table_name)->where($entity_id_column, '=', $entity_id)->first();

                    $data = ['entity_id' => $entity_id];

                    if(\is_array($given_attributes) && \count($given_attributes) > 0)
                        $data = array_merge($data, $given_attributes);
                }
                else $data = $given_attributes;

                if($data === null) $data = [];

                $this->parent_ = Entity::createNew($parent_class, $data);
            }
            else if(\is_array($given_attributes) && \count($given_attributes) > 0) $this->parent_->fill($given_attributes);

            return $this->parent_;
        };

        return $func();
    }

    public function parent()
    {
        return $this->parent_;
    }

    public function set_parent($entity)
    {
        $this->parent_ = $entity;
    }

    public function elevate($attributes = [])
    {
        $entity_id = $this->entity_id;
        $top_class = Entities::topClassWithEntityID($entity_id); // TODO: Try using inline code instead of Service call at high volume and whether it is better to do that for performance reasons
        $current_class = static::class;

        if($current_class !== $top_class)
        {
            $attributes['entity_id'] = $entity_id;

            $top_entity = new $top_class($attributes);

            $entity = $top_entity;
            while($entity->parent() === null)
            {
                if(get_parent_class($entity) === $current_class)
                    $entity->set_parent($this);
                else $entity = $entity->parent_model($attributes);
            }

            return $top_entity;
        }
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

        $result = parent::fill($immediately_fillable);

        if(isset($immediately_fillable['entity_id']))
            $this->setAttribute('entity_id', $immediately_fillable['entity_id']);

        return $result;
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

        while(($current_class = \get_class($entity)) !== Entity::class)
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
            if($current_class !== Entity::class)
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
        else
        {
            $saved = $this->performInsert($query);
            
            // TODO: Debug this. Does the new entity even have top_class / parent classes registered?
            Cache::forever('#' . $this->entity_id, $this->top_class);
        }

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
            
            if($parent_model == null)
            {
                Cache::forget('#' . $this->entity_id);
                return true;
            }
            else return $parent_model->delete();
        }
    }

    public static function tableName($field_name = null)
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
    
    public function __toString()
    {
        $fillable = $this->getRecursiveFillable();
        if(in_array("name", $fillable)) return $this->name;
        else if(in_array("title", $fillable)) return $this->title;
        else if(in_array("label", $fillable)) return $this->label;
        else if(in_array("term", $fillable)) return $this->term;
        else if(in_array("headline", $fillable)) return $this->headine;
        else if(in_array("caption", $fillable)) return $this->caption;
        else return static::$name . " ID " . $this->id_as(static::class) . "";
    }

    public static function select_by_term($term = null)
    {
        return with(new static)->selectByTerm($term);
    }

    public function selectByTerm($term = null)
    {
        $field = null;
        $fillable = $this->getRecursiveFillable();
        if(in_array("name", $fillable)) $field = "name";
        else if(in_array("title", $fillable)) $field = "title";
        else if(in_array("label", $fillable)) $field = "label";
        else if(in_array("term", $fillable)) $field = "term";
        else if(in_array("headline", $fillable)) $field = "headline";
        else if(in_array("caption", $fillable)) $field = "caption";
        if($field)
        {
            $entity_class = static::class;
            $entity_table = $entity_class::tableName();
            $query = $entity_class
                ::select($entity_table . ".*")
                ->where($field, "LIKE", "%" . $term . "%");
            return $query;
        }
        throw new Exception("Method selectByTerm must be overwritten by sub class.");
    }
    public function showForProjectEntityTypeAssociation() { return true; }
    public function initialise() { }
    public function filterColumns($columns, $filter_for = null) { return $columns; }
}
