<?php

error_reporting((E_ALL | E_STRICT) ^ E_NOTICE);
ini_set('display_errors', 1);

try {
    $cloudPanelDirectory = '/home/clp/htdocs/app/files/';
    $varnishControllerDirectory = '/home/clp/htdocs/app/files/resources/varnish-cache/controller/';
    $sqliteDatabaseFile = '/home/clp/htdocs/app/data/db.sq3';
    $domainName = $argv[1] ?? null;
    $application = $argv[2] ?? null;
    $currentUser = get_current_user();
    if ('root' != $currentUser) {
        throw new \Exception('Execute the script as root.');
    }
    if ((true === is_null($domainName)) || (true === is_null($application))) {
        throw new Exception('Arguments missing.');
    }
    if (false === file_exists($sqliteDatabaseFile)) {
        throw new \Exception(sprintf('SQLite Database "%s" does not exist.', $sqliteDatabaseFile));
    }
    $varnishControllerName = strtolower($application);
    $varnishControllerFile = sprintf('%s/%s/controller.php', rtrim($varnishControllerDirectory, '/'), $varnishControllerName);
    if (false === file_exists($varnishControllerFile)) {
        throw new \Exception(sprintf('Varnish controller file %s not found.', $varnishControllerFile));
    }
    $pdo = new \PDO(sprintf('sqlite:%s', $sqliteDatabaseFile));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statement = $pdo->prepare('SELECT * FROM vhost_template WHERE name=:application');
    $statement->bindParam(':application', $application,PDO::PARAM_STR);
    $statement->execute();
    $vhostTemplate = $statement->fetch(PDO::FETCH_ASSOC);
    if (true === isset($vhostTemplate['varnish_cache_settings']) && false === empty($vhostTemplate['varnish_cache_settings'])) {
        $varnishCacheSettings = json_decode($vhostTemplate['varnish_cache_settings'], true);
        if (true === isset($varnishCacheSettings['cacheLifetime'])) {
            $muha = 1;
        }
    }
} catch (\Exception $e) {
    $errorMessage = sprintf('An error occurred: %s %s', $e->getMessage(), PHP_EOL);
    echo $errorMessage;
}