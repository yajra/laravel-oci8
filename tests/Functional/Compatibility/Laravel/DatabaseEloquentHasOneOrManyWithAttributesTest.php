<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel;

use Illuminate\Database\Eloquent\Model;
use Yajra\Oci8\Tests\LaravelTestCase;

class DatabaseEloquentHasOneOrManyWithAttributesTest extends LaravelTestCase
{
    public function test_has_many_adds_attributes(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value]);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_has_one_adds_attributes(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasOne(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value]);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_morph_many_adds_attributes(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->morphMany(RelatedWithAttributesModel::class, 'relatable')
            ->withAttributes([$key => $value]);

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

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->morphOne(RelatedWithAttributesModel::class, 'relatable')
            ->withAttributes([$key => $value]);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->relatable_id);
        $this->assertSame($parent::class, $relatedModel->relatable_type);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_with_attributes_can_be_overridden(): void
    {
        $key = 'a key';
        $defaultValue = 'a value';
        $value = 'the value';

        $parent = new RelatedWithAttributesModel;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'relatable')
            ->withAttributes([$key => $defaultValue]);

        $relatedModel = $relationship->make([$key => $value]);

        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_querying_does_not_break_wither(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->where($key, $value)
            ->withAttributes([$key => $value]);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_attributes_can_be_appended(): void
    {
        $parent = new RelatedWithAttributesModel;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes(['a' => 'A'])
            ->withAttributes(['b' => 'B'])
            ->withAttributes(['a' => 'AA']);

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
        $parent = new RelatedWithAttributesModel;
        $key = 'attr';
        $value = 'Value';

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes($key, $value);

        $relatedModel = $relationship->make();

        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_wheres_are_set(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value]);

        $wheres = $relationship->toBase()->wheres;

        $this->assertContains([
            'type' => 'Basic',
            'column' => 'related_with_attributes_models.'.$key,
            'operator' => '=',
            'value' => $value,
            'boolean' => 'and',
        ], $wheres);

        // Ensure this doesn't break the default where either.
        $this->assertContains([
            'type' => 'Basic',
            'column' => $parent->qualifyColumn('parent_id'),
            'operator' => '=',
            'value' => $parentId,
            'boolean' => 'and',
        ], $wheres);
    }

    public function test_null_value_is_accepted(): void
    {
        $parentId = 123;
        $key = 'a key';

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes([$key => null]);

        $wheres = $relationship->toBase()->wheres;
        $relatedModel = $relationship->make();

        $this->assertNull($relatedModel->$key);

        $this->assertContains([
            'type' => 'Null',
            'column' => 'related_with_attributes_models.'.$key,
            'boolean' => 'and',
        ], $wheres);
    }

    public function test_one_keeps_attributes_from_has_many(): void
    {
        $parentId = 123;
        $key = 'a key';
        $value = 'the value';

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes([$key => $value])
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

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->morphMany(RelatedWithAttributesModel::class, 'relatable')
            ->withAttributes([$key => $value])
            ->one();

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->relatable_id);
        $this->assertSame($parent::class, $relatedModel->relatable_type);
        $this->assertSame($value, $relatedModel->$key);
    }

    public function test_has_many_adds_casted_attributes(): void
    {
        $parentId = 123;

        $parent = new RelatedWithAttributesModel;
        $parent->id = $parentId;

        $relationship = $parent
            ->hasMany(RelatedWithAttributesModel::class, 'parent_id')
            ->withAttributes(['is_admin' => 1]);

        $relatedModel = $relationship->make();

        $this->assertSame($parentId, $relatedModel->parent_id);
        $this->assertSame(true, $relatedModel->is_admin);
    }
}

class RelatedWithAttributesModel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_admin' => 'boolean',
    ];
}
