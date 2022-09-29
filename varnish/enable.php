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
    if (false === file_exists($varnishControllerDirectory)) {
        throw new \Exception(sprintf('Varnish controller directory does not exist. Did you update to 2.1?'));
    }
    $pdo = new \PDO(sprintf('sqlite:%s', $sqliteDatabaseFile));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statement = $pdo->prepare('SELECT * FROM site WHERE domain_name=:domainName');
    $statement->bindParam(':domainName', $domainName,PDO::PARAM_STR);
    $statement->execute();
    $site = $statement->fetch(PDO::FETCH_ASSOC);
    if (false === isset($site['id'])) {
        throw new \Exception(sprintf('Site "%s" does not exist.', $domainName));
    }
    $siteId = $site['id'];
    $siteUser = $site['user'];
    $homeDirectory = sprintf('/home/%s/', $siteUser);
    if (false === file_exists($homeDirectory)) {
        throw new \Exception(sprintf('Home directory does not exist: %s', $homeDirectory));
    }
    $siteVarnishCacheDirectory = sprintf('%s/.varnish-cache/', rtrim($homeDirectory, '/'));
    if (false === file_exists($siteVarnishCacheDirectory)) {
        @mkdir($siteVarnishCacheDirectory, 0770);
    }
    chown($siteVarnishCacheDirectory, $siteUser);
    chgrp($siteVarnishCacheDirectory, $siteUser);
    $statement = $pdo->prepare('SELECT * FROM vhost_template WHERE name=:application');
    $statement->bindParam(':application', $application,PDO::PARAM_STR);
    $statement->execute();
    $vhostTemplate = $statement->fetch(PDO::FETCH_ASSOC);
    if (true === isset($vhostTemplate['varnish_cache_settings']) && false === empty($vhostTemplate['varnish_cache_settings'])) {
        $varnishCacheSettings = json_decode($vhostTemplate['varnish_cache_settings'], true);
        if (true === isset($varnishCacheSettings['controller']) && true === isset($varnishCacheSettings['cacheLifetime'])) {
            $varnishControllerName = $varnishCacheSettings['controller'];
            $varnishControllerFile = sprintf('%s/%s/controller.php', rtrim($varnishControllerDirectory, '/'), $varnishControllerName);
            if (false === file_exists($varnishControllerFile)) {
                throw new \Exception(sprintf('Varnish controller file %s not found.', $varnishControllerFile));
            }
            $siteVarnishControllerFile = sprintf('%s/controller.php', rtrim($siteVarnishCacheDirectory, '/'));
            if (false === copy($varnishControllerFile, $siteVarnishControllerFile)) {
                throw new Exception(sprintf('Cannot copy file %s to %s', $varnishControllerFile, $siteVarnishControllerFile));
            }
            @chmod($siteVarnishControllerFile, 0770);
            @chown($siteVarnishControllerFile, $siteUser);
            @chgrp($siteVarnishControllerFile, $siteUser);
            $varnishCacheSettings = [
                'enabled'        => false,
                'server'         => '127.0.0.1:6081',
                'cacheTagPrefix' => substr(md5(time()), 0, 4),
                'cacheLifetime'  => $varnishCacheSettings['cacheLifetime'],
                'excludes'       => $varnishCacheSettings['excludes'],
                'excludedParams' => $varnishCacheSettings['excludedParams'],
            ];
            $varnishCacheSettings = json_encode($varnishCacheSettings, JSON_PRETTY_PRINT);
            $settingsFile = sprintf('%s/settings.json', rtrim($siteVarnishCacheDirectory, '/'));
            file_put_contents($settingsFile, $varnishCacheSettings);
            @chmod($settingsFile, 0770);
            @chown($settingsFile, $siteUser);
            @chgrp($settingsFile, $siteUser);
            $statement = $pdo->prepare('Update site set varnish_cache=1 WHERE id=:siteId');
            $statement->bindParam(':siteId', $siteId,PDO::PARAM_STR);
            $statement->execute();
            $successMessage = 'Done! Replace the current vhost with the varnish vhost and enjoy Varnish Cache :-)';
            echo $successMessage.PHP_EOL;
            exit;
        }
    } else {
        throw new Exception(sprintf('Vhost for application "%s" does not exist.', $application));
    }
} catch (\Exception $e) {
    $errorMessage = sprintf('An error occurred: %s %s', $e->getMessage(), PHP_EOL);
    echo $errorMessage;
}