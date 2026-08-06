<?php

declare(strict_types=1);

namespace UnraidFirewall;

require_once __DIR__ . '/common.php';

function getSettingsPage(): string
{
    ob_start();
    require __DIR__ . '/Pages/Settings.php';
    return (string) ob_get_clean();
}
