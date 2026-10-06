<?php

test('tenant migrations implement every documented table field and local relationship', function (): void {
    $this->artisan('migrate', ['--path' => database_path('migrations/tenant'), '--realpath' => true, '--no-interaction' => true])->assertSuccessful();

    assertDocumentedDatabaseSchema('tenant');
    assertDocumentedAuthorizationConstraints('tenant');
    assertDocumentedTenantCatalogAndStockConstraints();
});
