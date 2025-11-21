<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    //
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'parent_id',
    ];

    // parent category relationship
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // child categories relationship
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products() : BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product');
    } 

    // is Active children   
    public function activeChildren()
    {
        return $this->children()->where('is_active', true);
    }

    // is Top Level Category
    public function isTopLevel()
    {
        return is_null($this->parent_id);
    }
}
