<?php

namespace Yajra\Oci8\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Query\OracleBuilder as QueryBuilder;

class OracleEloquent extends Model
{
    /**
     * List of binary (blob) columns
     *
     * @var array
     */
    protected $binaries = [];

    /**
     * @var array
     */
    protected $wrapBinaries = [];

    /**
     * Sequence name variable
     *
     * @var string
     */
    protected $sequence = null;

    /**
     * Get model's sequence name
     *
     * @return string
     */
    public function getSequenceName()
    {
        if ($this->sequence) {
            return $this->sequence;
        }

        return $this->getTable() . '_' . $this->getKeyName() . '_seq';
    }

    /**
     * Set sequence name
     *
     * @param string $name
     * @return string
     */
    public function setSequenceName($name)
    {
        return $this->sequence = $name;
    }

    /**
     * Update the model in the database.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool|int
     */
    public function update(array $attributes = [], array $options = [])
    {
        if (! $this->exists) {
            // If dirty attributes contains binary field
            // extract binary fields to new array
            if ($this->wrapBinary($dirty)) {
                return $this->newQuery()->updateLob($attributes, $this->wrapBinaries, $this->getKeyName());
            }

            return $this->newQuery()->update($attributes);
        }

        return $this->fill($attributes)->save();
    }

    /**
     * wrap binaries to each attributes
     *
     * @param  array $attributes
     * @return array
     */
    public function wrapBinary(&$attributes)
    {
        // If attributes contains binary field
        // extract binary fields to new array
        $binaries = [];
        if ($this->checkBinary($attributes) and $this->getConnection() instanceof Oci8Connection) {
            foreach ($attributes as $key => $value) {
                if (in_array($key, $this->binaries)) {
                    $binaries[$key] = $value;
                    unset($attributes[$key]);
                }
            }
        }

        return $this->wrapBinaries = $binaries;
    }

    /**
     * Check if attributes contains binary field
     *
     * @param  array $attributes
     * @return boolean
     */
    public function checkBinary(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            // if attribute is in binary field list
            if (in_array($key, $this->binaries)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
        $pos = strpos($this->getTable(), '@');

        if ($pos === false) {
            return $this->getTable() . '.' . $this->getKeyName();
        } else {
            $table  = substr($this->getTable(), 0, $pos);
            $dblink = substr($this->getTable(), $pos);

            return $table . '.' . $this->getKeyName() . $dblink;
        }
    }

    /**
     * Get a new query builder instance for the connection.
     *
     * @return \Yajra\Oci8\Query\OracleBuilder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();

        $grammar = $conn->getQueryGrammar();

        return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }

    /**
     * Perform a model update operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  array $options
     * @return boolean
     */
    protected function performUpdate(Builder $query, array $options = [])
    {
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            // If the updating event returns false, we will cancel the update operation so
            // developers can hook Validation systems into their models and cancel this
            // operation if the model does not pass validation. Otherwise, we update.
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            // First we need to create a fresh query instance and touch the creation and
            // update timestamp on the model which are maintained by us for developer
            // convenience. Then we will just continue saving the model instances.
            if ($this->timestamps && array_get($options, 'timestamps', true)) {
                $this->updateTimestamps();
            }

            // Once we have run the update operation, we will fire the "updated" event for
            // this model instance. This will allow developers to hook into these after
            // models are updated, giving them a chance to do any special processing.
            $dirty = $this->getDirty();

            if (count($dirty) > 0) {
                // If dirty attributes contains binary field
                // extract binary fields to new array
                $this->updateBinary($query, $dirty, $options);

                $this->fireModelEvent('updated', false);
            }
        }

        return true;
    }

    /**
     * @param Builder $query
     * @param array $dirty
     * @param array $options
     */
    protected function updateBinary(Builder $query, $dirty, $options = [])
    {
        if ($this->wrapBinary($dirty)) {
            $this->setKeysForSaveQuery($query)->updateLob($dirty, $this->wrapBinaries, $this->getKeyName());
        } else {
            $this->setKeysForSaveQuery($query)->update($dirty, $options);
        }
    }

    /**
     * Perform a model insert operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  array $options
     * @return bool
     */
    protected function performInsert(Builder $query, array $options = [])
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->timestamps && array_get($options, 'timestamps', true)) {
            $this->updateTimestamps();
        }

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = $this->attributes;

        if ($this->incrementing) {
            $this->insertAndSetId($query, $attributes);
        }

        // If the table is not incrementing we'll simply insert this attributes as they
        // are, as this attributes arrays must contain an "id" column already placed
        // there by the developer as the manually determined key for these models.
        else {
            // If attributes contains binary field
            // extract binary fields to new array
            if ($this->wrapBinary($attributes)) {
                $query->getQuery()->insertLob($attributes, $this->wrapBinaries, $this->getKeyName());
            } else {
                $query->insert($attributes);
            }
        }

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  array $attributes
     * @return int|void
     */
    protected function insertAndSetId(Builder $query, $attributes)
    {
        if ($binaries = $this->wrapBinary($attributes)) {
            $id = $query->getQuery()->insertLob($attributes, $binaries, $keyName = $this->getKeyName());
        } else {
            $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());
        }

        $this->setAttribute($keyName, $id);
    }
}
