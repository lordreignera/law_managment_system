<?php

namespace Tests\Unit;

use App\Support\RoutePermissionRegistry;
use PHPUnit\Framework\TestCase;

class RoutePermissionRegistryTest extends TestCase
{
    public function test_permission_keys_keep_stable_authorization_names_while_exposing_clear_labels(): void
    {
        $registry = new RoutePermissionRegistry;

        $this->assertSame('Approve staff access requests', $registry->permissionLabel('access.users.approve'));
        $this->assertSame('View Clients', $registry->permissionLabel('clients.index'));
        $this->assertSame('Delete Clients', $registry->permissionLabel('clients.destroy'));
        $this->assertSame('View audit logs', $registry->permissionLabel('audit.index'));
        $this->assertSame('View audit log details', $registry->permissionLabel('audit.show'));
        $this->assertStringContainsString('access control', strtolower($registry->permissionDescription('access.users.approve')));
    }
}
