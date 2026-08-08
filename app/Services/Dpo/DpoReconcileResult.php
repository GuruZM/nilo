<?php

namespace App\Services\Dpo;

final readonly class DpoReconcileResult
{
    protected function __construct(
        public int $checked,
        public int $activated,
        public int $rejected,
        public int $stillPending,
        public int $errors,
    ) {}

    public static function make(int $checked, int $activated, int $rejected, int $stillPending, int $errors): self
    {
        return new self($checked, $activated, $rejected, $stillPending, $errors);
    }

    public function summary(): string
    {
        return "Checked {$this->checked}, activated {$this->activated}, rejected {$this->rejected}, "
            ."still pending {$this->stillPending}, errors {$this->errors}.";
    }
}
