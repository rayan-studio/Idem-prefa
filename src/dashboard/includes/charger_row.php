<?php
// Safe stub to prevent fatal errors if required by a cached editor buffer
if (!function_exists('chargerRow')) {
    function chargerRow(array $charger, bool $editing = false, string $error = ''): void {}
}
