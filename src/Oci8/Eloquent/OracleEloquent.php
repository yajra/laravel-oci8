<?php

namespace Yajra\Oci8\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as IlluminateQueryBuilder;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Query\Grammars\OracleGrammar;
use Yajra\Oci8\Query\OracleBuilder as QueryBuilder;

class OracleEloquent extends Model
{
    /**
     * List of binary (blob) columns.
     *
     * @var array
     */
    protected $binaries = [];

    /**
     * List of binary fields for storage.
     *
     * @var array
     */
    protected $binaryFields = [];

    /**
     * Sequence name variable.
     *
     * @var string
     */
    public $sequence = null;

    /**
     * Get next value of the model sequence.
     */
    public static function nextValue(?string $sequence = null): int
    {
        $instance = new static;
        $connection = $instance->getConnection();

        if (! $connection instanceof Oci8Connection) {
            return 0;
        }

        $sequence ??= $instance->getSequenceName();

        return $connection->getSequence()->nextValue($sequence);
    }

    /**
     * Get model's auto-generated sequence name.
     */
    public function getSequenceName(): string
    {
        if ($this->sequence) {
            return $this->sequence;
        }

        return $this->getTable().'_'.$this->getKeyName().'_seq';
    }

    /**
     * Set the model's sequence name.
     */
    public function setSequenceName(string $name): static
    {
        $this->sequence = $name;

        return $this;
    }

    /**
     * Update the model in the database.
     */
    public function update(array $attributes = [], array $options = []): bool|int
    {
        if (! $this->exists) {
            return false;
        }

        // If dirty attributes contains binary field
        // extract binary fields to new array
        if ($this->extractBinaries($attributes)) {
            return $this->newQuery()->updateLob($attributes, $this->binaryFields, $this->getKeyName());
        }

        return $this->fill($attributes)->save($options);
    }

    /**
     * Extract binary fields from given attributes.
     */
    protected function extractBinaries(array &$attributes): array
    {
        // If attributes contains binary field
        // extract binary fields to new array
        $binaries = [];
        if ($this->checkBinary($attributes) && $this->getConnection() instanceof Oci8Connection) {
            foreach ($attributes as $key => $value) {
                if (in_array($key, $this->binaries)) {
                    $binaries[$key] = $value;
                    unset($attributes[$key]);
                }
            }
        }

        return $this->binaryFields = $binaries;
    }

    /**
     * Check if attributes contains binary field.
     */
    protected function checkBinary(array $attributes): bool
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
     */
    public function getQualifiedKeyName(): string
    {
        $pos = strpos($this->getTable(), '@');

        if ($pos === false) {
            return parent::getQualifiedKeyName();
        }

        $table = mb_substr($this->getTable(), 0, $pos);
        $dbLink = mb_substr($this->getTable(), $pos);

        return $table.'.'.$this->getKeyName().$dbLink;
    }

    /**
     * Get a new query builder instance for the connection.
     */
    protected function newBaseQueryBuilder(): QueryBuilder|IlluminateQueryBuilder
    {
        $conn = $this->getConnection();
        $grammar = $conn->getQueryGrammar();

        if ($grammar instanceof OracleGrammar) {
            return new QueryBuilder($conn, $grammar, $conn->getPostProcessor());
        }

        return new IlluminateQueryBuilder($conn, $grammar, $conn->getPostProcessor());
    }

    /**
     * Perform a model update operation.
     */
    protected function performUpdate(Builder $query): bool
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            // If dirty attributes contains binary field
            // extract binary fields to new array
            $this->updateBinary($query, $dirty);

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Update model with binary (blob) fields.
     */
    protected function updateBinary(Builder $query, array $dirty): void
    {
        $builder = $this->setKeysForSaveQuery($query);

        if ($this->extractBinaries($dirty)) {
            $builder->updateLob($dirty, $this->binaryFields, $this->getKeyName());
        } else {
            $builder->update($dirty);
        }
    }

    /**
     * Perform a model insert operation.
     */
    protected function performInsert(Builder $query): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final inserted ID for this
        // table from the database. Not all tables have to be incrementing though.
        $attributes = $this->attributes;

        if ($this->getIncrementing()) {
            $this->insertAndSetId($query, $attributes);
        }

        // If the table is not incrementing we'll simply insert this attributes as they
        // are, as this attributes arrays must contain an "id" column already placed
        // there by the developer as the manually determined key for these models.
        else {
            if (empty($attributes)) {
                return true;
            }

            // If attributes contains binary field
            // extract binary fields to new array
            if ($this->extractBinaries($attributes)) {
                $query->getQuery()->insertLob($attributes, $this->binaryFields, $this->getKeyName());
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
     * @param  array  $attributes
     */
    protected function insertAndSetId(Builder $query, $attributes): void
    {
        $id = ($binaries = $this->extractBinaries($attributes)) ?
            $query->getQuery()->insertLob($attributes, $binaries, $keyName = $this->getKeyName()) :
            $query->insertGetId($attributes, $keyName = $this->getKeyName());

        $this->setAttribute($keyName, $id);
    }
}
