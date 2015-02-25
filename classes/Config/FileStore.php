<?php
/**
 * This file is part of the Autarky package.
 *
 * (c) Andreas Lutro <anlutro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Autarky\Config;

use Autarky\Support\ArrayUtils;
use Autarky\Files\PathResolver;
use Autarky\Files\Locator;

/**
 * File-based config implementation.
 *
 * Reads files from one or multiple directories, with the possibility of
 * cascading for different environments and overriding of namespaces.
 */
class FileStore implements ConfigInterface
{
	/**
	 * The path resolver instance.
	 *
	 * @var PathResolver
	 */
	protected $pathResolver;

	/**
	 * The file locator instance.
	 *
	 * @var Locator
	 */
	protected $fileLocator;

	/**
	 * The loader factory instance.
	 *
	 * @var LoaderFactory
	 */
	protected $loaderFactory;

	/**
	 * The loaded config data.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * Constructor.
	 *
	 * @param PathResolver  $pathResolver
	 * @param Locator       $fileLocator
	 * @param LoaderFactory $loaderFactory
	 * @param string        $path          Path to config files in the global namespace.
	 * @param string|null   $environment
	 */
	public function __construct(
		PathResolver $pathResolver,
		Locator $fileLocator,
		LoaderFactory $loaderFactory,
		$environment = null
	) {
		$this->pathResolver = $pathResolver;
		$this->fileLocator = $fileLocator;
		$this->loaderFactory = $loaderFactory;
		$this->environment = $environment;
	}

	/**
	 * Get the loader factory instance.
	 *
	 * @return LoaderFactory
	 */
	public function getLoaderFactory()
	{
		return $this->loaderFactory;
	}

	/**
	 * {@inheritdoc}
	 */
	public function mount($location, $path)
	{
		$this->pathResolver->mount($location, $path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function setEnvironment($environment)
	{
		$this->environment = $environment;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($key)
	{
		$this->loadData($key);

		return ArrayUtils::has($this->data, $key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $default = null)
	{
		$this->loadData($key);

		return ArrayUtils::get($this->data, $key, $default);
	}

	/**
	 * {@inheritdoc}
	 */
	public function set($key, $value)
	{
		$this->loadData($key);

		ArrayUtils::set($this->data, $key, $value);
	}

	protected function loadData($key)
	{
		$basename = $this->getBasename($key);

		if (array_key_exists($basename, $this->data)) {
			return;
		}

		$basenames = $this->getBasenames($basename);
		$paths = $this->getPaths($basenames);

		foreach ($paths as $path) {
			$data = $this->getDataFromFile($path);

			if (isset($this->data[$basename])) {
				$this->data[$basename] = array_replace(
					$this->data[$basename], $data);
			} else {
				$this->data[$basename] = $data;
			}
		}
	}

	protected function getBasename($key)
	{
		return current(explode('.', $key));
		return $parts[0];
	}

	protected function getBasenames($basename)
	{
		$basenames = $this->pathResolver->resolve($basename);

		$envBasenames = array_map(function($basename) {
			return $basename.'.'.$this->environment;
		}, $basenames);

		return array_merge($basenames, $envBasenames);
	}

	protected function getPaths($basenames)
	{
		$extensions = $this->loaderFactory->getExtensions();

		return $this->fileLocator->locate($basenames, $extensions);
	}

	protected function getDataFromFile($path)
	{
		if (!is_readable($path)) {
			throw new LoadException("File is not readable: $path");
		}

		$loader = $this->loaderFactory->getForPath($path);

		return $loader->load($path);
	}
}