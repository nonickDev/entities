# Joined-Table Inheritance for Laravel

This package provides joined-table inheritance to Laravel allowing you to store and query hierarchical objects in MySQL.

## Installation

```php
composer require cvsouth/entities
php artisan migrate
```

## Usage

### Defining classes

Extend your models from `Entity` instead of the usual `Model`:

```php
class Animal extends Entity
{
    public $table = "animals";
    protected $fillable =
    [
        'name',
        'species',
    ];
}
```

```php
class Bird extends Animal
{
    public $table = "birds";
    protected $fillable =
    [
        'flying',
    ];
}
```

And when creating your migrations, include `entity_id`. Also insert a new EntityType object:

```php
class CreateAnimalsTable extends Migration
{
    public function up()
    {
        Schema::create('animals', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('entity_id')->unsigned();
            $table->string('species', 250);
            $table->string('name', 250)->nullable();
        });
        
        $entity_type = new EntityType(["entity_class" => Animal::class]); $entity_type->save();
    }

    public function down()
    {
        Schema::drop('animals');
                
        $entity_type = EntityType::where("entity_class", Animal::class)->first(); if($entity_type) EntityType::destroy([$entity_type->id]);
    }
}
```

```php
class CreateBirdsTable extends Migration
{
    public function up()
    {
        Schema::create('birds', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('entity_id')->unsigned();
            $table->boolean('flying');
        });
        
        $entity_type = new EntityType(["entity_class" => Bird::class]); $entity_type->save();
    }

    public function down()
    {
        Schema::drop('birds');
        
        $entity_type = EntityType::where("entity_class", Bird::class)->first(); if($entity_type) EntityType::destroy([$entity_type->id]);
    }
}
```

### Storing objects

You can then use your objects just like normal Eloquent objects:

```php
$bird = new Bird
([
   "species" => "Aratinga solstitialis", // Note: This attribute is inherited from Animal
   "flying" => true,
]);
$bird->save();

echo $bird->species;
// Aratinga solstitialis
```

### Querying objects

Again, you can query the object just like usual for Eloquent:

```php
$bird = Bird::where("species", "=", "Aratinga solstitialis")->first();

echo "This " . strtolower($bird->species) . " can " . ($bird->flying ? "" : "not ") . "fly";
// This aratinga solstitialis can fly 
```

### Relationships



### Primary keys at different levels of inheritance

At each level of inheritance the object has an ID. In the example above, the $bird has an Animal ID as well as a Bird ID. In addition to this each entity has a common ID called Entity ID which is consistent throughout it's class hierarchy.

Use the `id_as` method to get the id for an entity at a specific level of inheritance:

```php
// The entity's Animal ID
echo $bird->id_as(Animal::class);

// The entity's Bird ID
echo $bird->id_as(Bird::class);
```

Or use the `entity_id` property to get the entities common ID:

```php
// The entity's Animal ID
echo $bird->entity_id
```