<?php
/**
 * This file is part of the Autarky package.
 *
 * (c) Andreas Lutro <anlutro@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Autarky\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class RouteListCommand extends Command
{
	/**
	 * {@inheritdoc}
	 */
	public function configure()
	{
		$this->setName('route:list')
			->setDescription('Show a list of routes')
			->setHelp(<<<'EOS'
Shows a list of all routes registered, their name, controller, path and hooks.
EOS
);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$table = new Table($output);
		$table->setHeaders(['Name', 'Methods', 'Path', 'Controller', 'Hooks']);

		$router = $this->app->getContainer()
			->resolve('Autarky\Routing\Router');

		/** @var \Autarky\Routing\Route $route */
		foreach ($router->getRoutes() as $route) {
			$methods = implode('|', $route->getMethods());
			$controller = $route->getController();

			if (is_array($controller)) {
				$controller = implode('::', $controller);
			} else if ($controller instanceof \Closure) {
				$controller = 'Closure';
			}

			$hooks = '';
			if ($before = $route->getBeforeHooks()) {
				$hooks .= 'Before: ' . implode(', ', $before);
			}
			if ($after = $route->getAfterHooks()) {
				if ($hooks !== '') {
					$hooks .= ' - ';
				}
				$hooks .= 'After: ' . implode(', ', $after);
			}

			$table->addRow([$route->getName(), $methods, $route->getPattern(), $controller, $hooks]);
		}

		$table->render();

		return 0;
	}
}
