<?php

namespace I18nBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use I18nBundle\Configuration\Configuration as BundleConfiguration;

class I18nExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container)
    {
        $configs = $container->getExtensionConfig($this->getAlias());

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $zoneTranslations = array_values(array_map(static function (array $zone) {
            return $zone['config']['translations'];
        }, $config['zones']));

        $translations = array_merge($config['translations'], ...$zoneTranslations);

        foreach ($translations as $translation) {

            $translationKey = sprintf('i18n.route.translations.%s', $translation['key']);
            $translationValue = implode('|', $translation['values']);

            if ($container->hasParameter($translationKey)) {
                continue;
            }

            $container->setParameter($translationKey, $translationValue);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator([__DIR__ . '/../Resources/config']));
        $loader->load('services.yml');
        $loader->load('profiler.yml');

        $configManagerDefinition = $container->getDefinition(BundleConfiguration::class);
        $configManagerDefinition->addMethodCall('setConfig', [$config]);

        $container->setParameter('i18n.registry_availability', $config['registry']);

        // set geo db path (including legacy path)
        if ($container->hasParameter('pimcore.geoip.db_file') && !empty($container->getParameter('pimcore.geoip.db_file'))) {
            $geoIpDbFile = $container->getParameter('pimcore.geoip.db_file');
        } else {
            $geoIpDbFile = realpath(PIMCORE_CONFIGURATION_DIRECTORY . '/GeoLite2-City.mmdb');
        }

        $container->setParameter('i18n.geo_ip.db_file', is_string($geoIpDbFile) ? $geoIpDbFile : '');
    }
}
