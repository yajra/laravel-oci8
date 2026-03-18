<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility\Laravel\Fixtures\Factories\Money;

use Illuminate\Database\Eloquent\Factories\Factory;

class PriceFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}
