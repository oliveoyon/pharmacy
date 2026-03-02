<?php

namespace App\Support\Tenancy;

class TenantContext
{
    public function __construct(
        private ?int $organizationId = null,
        private ?int $branchId = null,
    ) {
    }

    public function setOrganizationId(?int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function setBranchId(?int $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function organizationId(): ?int
    {
        return $this->organizationId;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }
}

