<?php
declare(strict_types=1);

namespace Sitegeist\FluidStyleguide\Service;

use Sitegeist\FluidStyleguide\Domain\Model\Package;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class StyleguideConfigurationManager
{
    /**
     * @var YamlFileLoader
     */
    protected $yamlFileLoader;

    /**
     * @var ExtensionConfiguration
     */
    protected $extensionConfiguration;

    /**
     * @var string
     */
    protected $defaultConfigurationFile = 'EXT:fluid_styleguide/Configuration/Yaml/FluidStyleguide.yaml';

    /**
     * @var array
     */
    protected $defaultConfiguration;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var array
     */
    protected $mergedConfiguration;

    public function __construct(YamlFileLoader $yamlFileLoader, ExtensionConfiguration $extensionConfiguration)
    {
        $this->yamlFileLoader = $yamlFileLoader;
        $this->extensionConfiguration = $extensionConfiguration;
    }

    public function loadFromExtensionConfiguration()
    {
        $yamlFile = $this->extensionConfiguration->get('fluid_styleguide', 'configurationFile');
        $this->loadConfiguration($yamlFile);
    }

    public function loadConfiguration(string $yamlFile = '')
    {
        $this->defaultConfiguration = $this->yamlFileLoader->load($this->defaultConfigurationFile)['FluidStyleguide'];
        $this->configuration = $yamlFile ? $this->yamlFileLoader->load($yamlFile)['FluidStyleguide'] ?? [] : [];

        // Merge default configuration with custom configuration
        $this->mergedConfiguration = $this->defaultConfiguration;
        ArrayUtility::mergeRecursiveWithOverrule(
            $this->mergedConfiguration,
            $this->configuration
        );

        // Sanitize component assets
        $this->mergedConfiguration['ComponentAssets']['Global']['Css'] = $this->sanitizeComponentAssets(
            $this->mergedConfiguration['ComponentAssets']['Global']['Css'] ?? []
        );
        $this->mergedConfiguration['ComponentAssets']['Global']['Javascript'] = $this->sanitizeComponentAssets(
            $this->mergedConfiguration['ComponentAssets']['Global']['Javascript'] ?? []
        );
        foreach ($this->mergedConfiguration['ComponentAssets']['Packages'] as &$assets) {
            $assets['Css'] = $this->sanitizeComponentAssets($assets['Css'] ?? []);
            $assets['Javascript'] = $this->sanitizeComponentAssets($assets['Javascript'] ?? []);
        }
    }

    public function getFeatures(): array
    {
        return $this->mergedConfiguration['Features'];
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return !empty($this->mergedConfiguration['Features'][$feature]);
    }

    public function getGlobalCss(): array
    {
        return $this->mergedConfiguration['ComponentAssets']['Global']['Css'] ?? [];
    }

    public function getGlobalJavascript(): array
    {
        return $this->mergedConfiguration['ComponentAssets']['Global']['Javascript'] ?? [];
    }

    public function getCssForPackage(Package $package): array
    {
        return $this->mergedConfiguration['ComponentAssets']['Packages'][$package->getNamespace()]['Css'] ?? [];
    }

    public function getJavascriptForPackage(Package $package): array
    {
        return $this->mergedConfiguration['ComponentAssets']['Packages'][$package->getNamespace()]['Javascript'] ?? [];
    }

    public function getResponsiveBreakpoints(): array
    {
        return $this->mergedConfiguration['ResponsiveBreakpoints'] ?? [];
    }

    protected function sanitizeComponentAssets($assets) {
        if (is_string($assets)) {
            $assets = [$assets];
        } elseif (!is_array($assets)) {
            return [];
        }

        foreach ($assets as &$asset) {
            $asset = PathUtility::stripPathSitePrefix(GeneralUtility::getFileAbsFileName($asset));
        }
        return $assets;
    }
}