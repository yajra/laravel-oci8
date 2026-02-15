<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use Illuminate\Database\Eloquent\Model;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentHasOneOrManyWithAttributesPendingTest extends LaravelTestCase
{
    public function test_has_many_adds_attributes(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value], asConditions: false);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_has_one_adds_attributes(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasOne(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value], asConditions: false);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_morph_many_adds_attributes(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->morphMany(RelatedPendingAttributesModel::class, 'relatable')
            ->withAttributes([$key => $value], asConditions: false);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->relatable_id);
        $this->assertSame($parent::class, $relatedModel->relatable_type);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_morph_one_adds_attributes(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->morphOne(RelatedPendingAttributesModel::class, 'relatable')
            ->withAttributes([$key => $value], asConditions: false);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->relatable_id);
        $this->assertSame($parent::class, $relatedModel->relatable_type);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_pending_attributes_can_be_overridden(): void
    {
        $key = 'a key';
        $defaultValue = 'a value';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'relatable')
            ->withAttributes([$key => $defaultValue], asConditions: false);

        $relatedModel = $relationship->make([$key => $value]);

        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_querying_does_not_break_wither(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->where($key, $value)
            ->withAttributes([$key => $value], asConditions: false);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_attributes_can_be_appended(): void
    {
        $parent = new RelatedPendingAttributesModel;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes(['a' => 'A'], asConditions: false)
            ->withAttributes(['b' => 'B'], asConditions: false)
            ->withAttributes(['a' => 'AA'], asConditions: false);

        $relatedModel = $relationship->make([
            'b' => 'BB',
            'c' => 'C',
        ]);

        $this->assertSame('AA', $relatedModel->a);
        $this->assertSame('BB', $relatedModel->b);
        $this->assertSame('C', $relatedModel->c);
    }

    public function test_single_attribute_api(): void
    {
        $parent = new RelatedPendingAttributesModel;
        $key = 'attr';
        $value = 'Value';

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes($key, $value, asConditions: false);

        $relatedModel = $relationship->make();

        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_wheres_are_not_set(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value], asConditions: false);

        $wheres = $relationship->toBase()->wheres;

        $this->assertContains([
            'type' => 'Basic',
            'column' => $parent->qualifyColumn('parent_id'),
            'operator' => '=',
            'value' => $parentId,
            'boolean' => 'and',
        ], $wheres);

        $this->assertContains([
            'type' => 'NotNull',
            'column' => $parent->qualifyColumn('parent_id'),
            'boolean' => 'and',
        ], $wheres);

        // Ensure no other wheres exist
        $this->assertCount(2, $wheres);
    }

    public function test_null_value_is_accepted(): void
    {
        $parentId = 123;
        $key = 'a key';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes([$key => null], asConditions: false);

        $wheres = $relationship->toBase()->wheres;
        $relatedModel = $relationship->make();

        $this->assertNull($relatedModel->$key);

        $this->assertContains([
            'type' => 'Basic',
            'column' => $parent->qualifyColumn('parent_id'),
            'operator' => '=',
            'value' => $parentId,
            'boolean' => 'and',
        ], $wheres);

        $this->assertContains([
            'type' => 'NotNull',
            'column' => $parent->qualifyColumn('parent_id'),
            'boolean' => 'and',
        ], $wheres);

        // Ensure no other wheres exist
        $this->assertCount(2, $wheres);
    }

    public function test_one_keeps_attributes_from_has_many(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value], asConditions: false)
            ->one();

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_one_keeps_attributes_from_morph_many(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->morphMany(RelatedPendingAttributesModel::class, 'relatable')
            ->withAttributes([$key => $value], asConditions: false)
            ->one();

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->relatable_id);
        $this->assertSame($parent::class, $relatedModel->relatable_type);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_has_many_adds_casted_attributes(): void
    {
        $parentId = 123;

        $parent = new RelatedPendingAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedPendingAttributesModel::class, 'parent_id')
            ->withAttributes(['is_admin' => 1], asConditions: false);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame(true, $relatedModel->is_admin);
    }
}

class RelatedPendingAttributesModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_admin' => 'boolean',
    ];
}
