<?php namespace Cvsouth\Entities\Providers;

use Cvsouth\Entities\Entity;
use Cvsouth\Entities\EntityType;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
    }

    public function register()
    {
        AliasLoader::getInstance()->alias('Entity', Entity::class);
        AliasLoader::getInstance()->alias('EntityType', EntityType::class);
    }
}
