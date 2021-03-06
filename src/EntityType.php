<?php namespace Cvsouth\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EntityType extends Entity
{
    public static $name = 'Entity Type';
    public static $name_plural = 'Entity Types';

    public $table = "entity_types";

    protected $fillable =
    [
        'entity_class',
    ];

    public function __toString()
    {
        $entity_class = $this->entity_class;

        $name = $entity_class::$name;

        return $name;
    }

    public static function From($entity_class_)
    {
        $entity_type = EntityType::where("entity_class", "=", $entity_class_)->first();
        return $entity_type;
    }

    public static function FromTableName($table_name_)
    {
        $cache_key = static::class . "_FromTableName_" . $table_name_;
        if(Cache::has($cache_key))
            return Cache::get($cache_key);
        else
        {
            $entity_types = static::all();
            $table_name = null;
            foreach($entity_types as $entity_type)
            {
                $entity_class = $entity_type->entity_class;
                $table_name = $entity_class::TableName();

                if($table_name === $table_name_)
                    return $entity_type;
            }
            Cache::forever($cache_key, $table_name);
            return $table_name;
        }
    }

    public static function GetParents($entity_type, $include_entity = false)
    {
        if(is_string($entity_type)) $entity_type = EntityType::From($entity_type);

        $entity_class = $entity_type->entity_class;

        if($entity_class === Entity::class || $entity_class === EntityType::class) return [];

        $parents = [];

        $end_class = $include_entity ? Model::class : Entity::class;

        while(($entity_class = get_parent_class($entity_class)) !== $end_class)
            $parents[] = EntityType::From($entity_class);

        return $parents;
    }

    public static function GetChildren($entity_type_, $recursive = false, $include_entity = false)
    {
        $cache_key = "EntityType_GetChildren_" . $entity_type_->id_as(EntityType::class);
        if(Cache::has($cache_key))
        {
            $children_entity_types = Cache::get($cache_key);
        }
        else
        {
            $children_entity_types = [];
            $entity_types = static::all();

            $entity_class_ = $entity_type_->entity_class;

            foreach($entity_types as $entity_type)
            {
                $entity_class = $entity_type->entity_class;
                $entity_class_parent = get_parent_class($entity_class);

                if($entity_class_parent == $entity_class_)
                    $children_entity_types[] = $entity_type;
            }

            Cache::forever($cache_key, $children_entity_types);
        }

        if($recursive)
            foreach($children_entity_types as $children_entity_type)
                foreach(static::GetChildren($children_entity_type, true, false) as $recursive_child)
                    $children_entity_types[] = $recursive_child;

        if($include_entity)
            $children_entity_types = array_merge([$entity_type_], $children_entity_types);

        return collect($children_entity_types);
    }
}
