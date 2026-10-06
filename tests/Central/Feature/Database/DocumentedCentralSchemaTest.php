<?php

test('central migrations implement every documented table field and local relationship', function (): void {
    assertDocumentedDatabaseSchema('central');
    assertDocumentedAuthorizationConstraints('central');
    assertDocumentedCentralGeographyConstraints();
});
