<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class User extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $fillable = ['id', 'name'];
    public $timestamps = false;
    protected $connection = 'testing';

    public function __construct($attributes = [])
    {
        // Handle old style constructor: new User(1) or new User(1, 'Name')
        if (is_int($attributes) || is_string($attributes)) {
            $id = $attributes;
            $name = func_num_args() > 1 ? func_get_arg(1) : 'Test User';
            parent::__construct(['id' => $id, 'name' => $name]);
            return;
        }

        // Handle new array style constructor: new User(['id' => 1, 'name' => 'Name'])
        parent::__construct($attributes ?: []);
    }

    // Override to make it work without a real database
    public function save(array $options = []): bool
    {
        return true;
    }

    public static function find($id)
    {
        return new static(['id' => $id, 'name' => 'Test User']);
    }
}