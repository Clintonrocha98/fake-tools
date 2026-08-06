<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Tests\Contract\Support;

/**
 * Marca de valor exato dentro de um fixture de forma — ver
 * {@see AssertsRecordedShape::exactValue()}.
 */
final readonly class ExactRecordedValue
{
    public function __construct(public mixed $value) {}
}
