<?php namespace Cvsouth\Entities;

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
}
