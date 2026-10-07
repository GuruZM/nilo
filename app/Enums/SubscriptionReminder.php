<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * The payment reminders a paid period earns, in the order they go out.
 *
 * Case order is the escalation order: isAfter() and dueFor() both lean on it.
 */
enum SubscriptionReminder: string
{
    case DueInThreeDays = 'due_in_three_days';
    case DueTomorrow = 'due_tomorrow';
    case Overdue = 'overdue';

    /**
     * The moment this reminder becomes due, measured from the date payment is.
     */
    public function opensAt(CarbonInterface $endsAt): CarbonInterface
    {
        return match ($this) {
            self::DueInThreeDays => $endsAt->copy()->subDays(3),
            self::DueTomorrow => $endsAt->copy()->subDay(),
            self::Overdue => $endsAt->copy(),
        };
    }

    /**
     * The most advanced reminder whose moment has come, if any.
     *
     * A sweep that runs late, or a period that starts inside the window,
     * sends only this one — never a burst of every stage it slept through.
     */
    public static function dueFor(CarbonInterface $endsAt, CarbonInterface $now): ?self
    {
        foreach (array_reverse(self::cases()) as $reminder) {
            if ($reminder->opensAt($endsAt)->lte($now)) {
                return $reminder;
            }
        }

        return null;
    }

    public function isAfter(?self $other): bool
    {
        return $other === null || $this->rank() > $other->rank();
    }

    private function rank(): int
    {
        return array_search($this, self::cases(), true);
    }
}
