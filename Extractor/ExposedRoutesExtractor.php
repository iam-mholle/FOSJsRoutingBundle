<?php

/*
 * This file is part of the FOSJsRoutingBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\JsRoutingBundle\Extractor;

use JMS\I18nRoutingBundle\Router\I18nLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author      William DURAND <william.durand1@gmail.com>
 */
class ExposedRoutesExtractor implements ExposedRoutesExtractorInterface
{
    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * Base cache directory
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * @var array
     */
    protected $bundles;

    /**
     * @var array
     */
    protected $routesToExpose;
    /**
     * @var AccessMapInterface
     */
    private $accessMap;
    /**
     * @var RoleHierarchyInterface
     */
    private $hierarchy;
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * Default constructor.
     *
     * @param RouterInterface        $router         The router.
     * @param array                  $routesToExpose Some route names to expose.
     * @param string                 $cacheDir
     * @param array                  $bundles        list of loaded bundles to check when generating the prefix
     * @param AccessMapInterface     $accessMap
     * @param RoleHierarchyInterface $hierarchy
     * @param TokenStorageInterface  $tokenStorage
     */
    public function __construct(
        RouterInterface $router, array $routesToExpose = array (), $cacheDir, $bundles = array (), AccessMapInterface $accessMap,
        RoleHierarchyInterface $hierarchy, TokenStorageInterface $tokenStorage)
    {
        $this->router = $router;
        $this->routesToExpose = $routesToExpose;
        $this->cacheDir = $cacheDir;
        $this->bundles = $bundles;
        $this->accessMap = $accessMap;
        $this->hierarchy = $hierarchy;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutes()
    {
        $collection = $this->router->getRouteCollection();
        $routes = new RouteCollection();

        $user = $this->tokenStorage->getToken()->getUser();
        $userRoles = $user->getRoles();

        /** @var Route $route */
        foreach ($collection->all() as $name => $route) {
            $roles = $this->getRolesForRoute($route);
            if ($this->isRouteExposed($route, $name) && ($roles == null || array_intersect($roles, $userRoles) > 0)) {
                $routes->add($name, $route);
            }
        }

        return $routes;
    }

    /**
     * {@inheritDoc}
     */
    public function getBaseUrl()
    {
        return $this->router->getContext()->getBaseUrl() ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function getPrefix($locale)
    {
        if (isset($this->bundles['JMSI18nRoutingBundle'])) {
            return $locale.I18nLoader::ROUTING_PREFIX;
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function getHost()
    {
        $requestContext = $this->router->getContext();

        $host = $requestContext->getHost().('' === $this->getPort() ? $this->getPort() : ':'.$this->getPort());

        return $host;
    }

    /**
     * {@inheritDoc}
     */
    public function getPort()
    {
        $requestContext = $this->router->getContext();

        $port = "";
        if ($this->usesNonStandardPort()) {
            $method = sprintf('get%sPort', ucfirst($requestContext->getScheme()));
            $port = $requestContext->$method();
        }

        return $port;
    }

    /**
     * {@inheritDoc}
     */
    public function getScheme()
    {
        return $this->router->getContext()->getScheme();
    }

    /**
     * {@inheritDoc}
     */
    public function getCachePath($locale)
    {
        $cachePath = $this->cacheDir.DIRECTORY_SEPARATOR.'fosJsRouting';
        if (!file_exists($cachePath)) {
            mkdir($cachePath);
        }

        if (isset($this->bundles['JMSI18nRoutingBundle'])) {
            $cachePath = $cachePath.DIRECTORY_SEPARATOR.'data.'.$locale.'.json';
        } else {
            $cachePath = $cachePath.DIRECTORY_SEPARATOR.'data.json';
        }

        return $cachePath;
    }

    /**
     * {@inheritDoc}
     */
    public function getResources()
    {
        return $this->router->getRouteCollection()->getResources();
    }

    /**
     * {@inheritDoc}
     */
    public function isRouteExposed(Route $route, $name)
    {
        $pattern = $this->buildPattern();

        return true === $route->getOption('expose')
            || 'true' === $route->getOption('expose')
            || ('' !== $pattern && preg_match('#'.$pattern.'#', $name));
    }

    /**
     * Convert the routesToExpose array in a regular expression pattern
     *
     * @return string
     */
    protected function buildPattern()
    {
        $patterns = array ();
        foreach ($this->routesToExpose as $toExpose) {
            $patterns[] = '('.$toExpose.')';
        }

        return implode($patterns, '|');
    }

    /**
     * Check whether server is serving this request from a non-standard port
     *
     * @return bool
     */
    private function usesNonStandardPort()
    {
        return $this->usesNonStandardHttpPort() || $this->usesNonStandardHttpsPort();
    }

    /**
     * Check whether server is serving HTTP over a non-standard port
     *
     * @return bool
     */
    private function usesNonStandardHttpPort()
    {
        return 'http' === $this->getScheme() && '80' != $this->router->getContext()->getHttpPort();
    }

    /**
     * Check whether server is serving HTTPS over a non-standard port
     *
     * @return bool
     */
    private function usesNonStandardHttpsPort()
    {
        return 'https' === $this->getScheme() && '443' != $this->router->getContext()->getHttpsPort();
    }

    private function getRolesForRoute(Route $route)
    {
        $path = $route->getPath();
        $request = Request::create($path, 'GET');

        list($roles, $channel) = $this->accessMap->getPatterns($request);

        return $roles;
    }
}
