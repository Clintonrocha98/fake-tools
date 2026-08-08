<?php

declare(strict_types=1);

namespace He4rt\Control\Feed\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReadControlFeedRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'after' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * `null` significa "do começo do que ainda existe".
     *
     * Nome deliberadamente diferente do parâmetro `after`: um método `after()`
     * num FormRequest é o hook de pós-validação do Laravel, e o validator o
     * invoca esperando uma lista de callables.
     */
    public function cursorAfter(): ?int
    {
        $after = $this->query('after');

        return is_numeric($after) ? (int) $after : null;
    }

    /**
     * O teto é aplicado aqui, não como regra `max:`: um poll que pede mais do
     * que o teto deve receber o teto, não um 422 — quem chama é uma sidebar em
     * loop, e derrubá-la por excesso de zelo só produz ruído no dev.
     */
    public function limit(): int
    {
        $default = config()->integer('control.feed.default_limit');
        $max = config()->integer('control.feed.max_limit');

        $limit = $this->query('limit');

        return min(is_numeric($limit) ? max(1, (int) $limit) : $default, $max);
    }
}
