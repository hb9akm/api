<?php
// cli-config.php
require_once(__DIR__ . '/vendor/autoload.php');
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
$config = Setup::createYamlMetadataConfiguration(
    array(__DIR__ . '/v2/Model/Schema/'),
    true,
    __DIR__ . '/tmp/proxies/',
    null,
    false
);
$conn = array(
    'dbname' => 'dev',
    'user' => 'root',
    'password' => '123456',
    'host' => 'db',
    'driver' => 'pdo_mysql',
);
$evm = new \Doctrine\Common\EventManager();
$timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
$timestampableListener->setAnnotationReader($config->getMetadataDriverImpl());
$evm->addEventSubscriber($timestampableListener);
$translatableListener = new \Gedmo\Translatable\TranslatableListener();
// current translation locale should be set from session or hook later into the listener
// most important, before entity manager is flushed
$translatableListener->setTranslatableLocale('en-US');
$translatableListener->setDefaultLocale('en-US');
$translatableListener->setAnnotationReader($config->getMetadataDriverImpl());
$evm->addEventSubscriber($translatableListener);
$entityManager = EntityManager::create($conn, $config, $evm);

return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($entityManager);

