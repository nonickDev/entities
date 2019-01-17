<?php namespace Cvsouth\Entities\Services;

class Entities
{
    public function topClassWithEntityID($entity_id)
    {
        $cache_key = '#' . $entity_id;

        if(Cache::has($cache_key)) return Cache::get($cache_key);
        else // cache is granular and should be permanant, but if the cache is bust or something,
            // when that occurs, let's just rebuild the entire cache again.
            // for a large database this could take a long time
            // an alternative would be to have it only build the cache for itself.
            // in the long term that would mean a more database queries
            // and also a massive burst when the cache is first being rebuilt
            // it wont take too long, let's just rebuild the whole thing
        {
            $entities = DB::table('entities')->simplePaginate(300);

            foreach($entities as $entity)
                Cache::forever('#' . $entity->id, $entity->top_class);
        }
    }
    
    public function getWithID($entity_class, $id)
    {
        return $entity_class::where('id', $id)->first();
    }

    public function getWithEntityID($entity_id, $entity_class = null)
    {
        if($entity_class === null)
            $entity_class = Entity::class;

        if($entity_class != Entity::class)
            $entity = $entity_class::where('entity_id', $entity_id)->first();
        else $entity = $entity_class::where('id', $entity_id)->first();

        return $entity;
    }

    public static function elevateMultiple($entities)
    {
        // TODO: always fetch top class values, in grouped queries

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

}
