<?php

declare(strict_types=1);

namespace Headless\Accounting\Contracts;

use Headless\Accounting\Models\JournalEntry;

/**
 * JournalPostingHook — lets host projects add additional postings
 * (or even veto) before a {@see JournalEntry} is committed.
 *
 * Typical use cases:
 *   - inject cost-center or project tags from the host application
 *   - prevent double-posting when an integration triggered the entry
 *   - stamp custom metadata onto postings for downstream BI tools
 *
 * Hooks run inside the same transaction as the posting call. Throwing
 * an exception from a hook rolls the entry back.
 */
interface JournalPostingHook
{
    /** Stable identifier (used in logs and config). */
    public function name(): string;

    /**
     * Mutate the entry / postings or throw to abort the commit.
     *
     * @param  array<string,mixed>  $context  The full action context (e.g. order being paid).
     */
    public function beforeCommit(JournalEntry $entry, array $context): void;
}
