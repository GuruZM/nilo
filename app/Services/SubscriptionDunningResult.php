<?php

namespace App\Services;

final readonly class SubscriptionDunningResult
{
    protected function __construct(
        public int $reminded,
        public int $paused,
        public int $errors,
    ) {}

    public static function make(int $reminded, int $paused, int $errors): self
    {
        return new self($reminded, $paused, $errors);
    }

    public function summary(): string
    {
        return "Reminded {$this->reminded}, paused {$this->paused}, errors {$this->errors}.";
    }
}
