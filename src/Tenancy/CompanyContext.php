<?php

declare(strict_types=1);

namespace Headless\Accounting\Tenancy;

/**
 * CompanyContext — process-scoped holder for the active Company. Used
 * by:
 *   - BelongsToCompany: auto-fill company_id on save
 *   - CompanyScope: filter queries at runtime
 *   - Service classes that need to know the current tenant
 *
 *   CompanyContext::set($company);      // in HTTP middleware
 *   $company = CompanyContext::current();
 *
 *   // In a queued job: push/pop to keep multi-tenancy working.
 */
final class CompanyContext
{
    private static ?Company $current = null;

    public static function set(Company $company): void
    {
        self::$current = $company;
    }

    public static function current(): ?Company
    {
        return self::$current;
    }

    public static function id(): ?int
    {
        return self::$current?->id;
    }

    public static function forget(): void
    {
        self::$current = null;
    }
}
