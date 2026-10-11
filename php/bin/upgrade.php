<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
try {
    db()->exec(file_get_contents(dirname(__DIR__).'/database/upgrade-1.2.sql'));
    echo "Gather 1.2 database upgrade applied. Existing accounts and subscriptions were retained.\n";
} catch (Throwable) {
    fwrite(STDERR,"Unable to apply the upgrade. Check the database configuration and CREATE/REFERENCES permissions. It is safe to retry.\n"); exit(1);
}
