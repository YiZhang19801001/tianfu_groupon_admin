<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductToCategory extends Model
{
    protected $table = 'oc_product_to_category';
    protected $fillable = ['product_id', 'category_id'];
    public $timestamps = false;
    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery(\Illuminate\Database\Eloquent\Builder $query)
    {
        
            $query
                ->where('category_id', '=', $this->getAttribute('category_id'))
                ->where('product_id', '=', $this->getAttribute('product_id'));
            return $query;

    }

    /**
     * Get the primary key value for a save query.
     *
     * @param mixed $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }
}
