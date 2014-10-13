<?php
namespace Autarky\Tests\Routing;

use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use Autarky\Container\Container;
use Autarky\Routing\Router;
use Autarky\Routing\Invoker;
use Autarky\Routing\UrlGenerator;

class UrlGeneratorTest extends PHPUnit_Framework_TestCase
{
	public function makeRouterAndGenerator($request = null)
	{
		$router = new Router(new Invoker(new Container));
		$requests = new RequestStack;
		if ($request) $requests->push($request);
		return [$router, new UrlGenerator($router, $requests)];
	}

	/** @test */
	public function urlGeneration()
	{
		list($router, $url) = $this->makeRouterAndGenerator(Request::create('/'));
		$router->addRoute('get', '/foo/{v}', function() {}, 'name');
		$this->assertEquals('//localhost/foo/bar', $url->getRouteUrl('name', ['bar']));
		$this->assertEquals('/foo/bar', $url->getRouteUrl('name', ['bar'], true));
	}

	/** @test */
	public function canAddRoutesWithoutLeadingSlash()
	{
		list($router, $url) = $this->makeRouterAndGenerator($request = Request::create('/foo/foo'));
		$router->addRoute('get', 'foo/{v}', function() { return 'test'; }, 'name');
		$response = $router->dispatch($request);
		$this->assertEquals('test', $response->getContent());
		$this->assertEquals('//localhost/foo/bar', $url->getRouteUrl('name', ['bar']));
		$this->assertEquals('/foo/bar', $url->getRouteUrl('name', ['bar'], true));
	}

	/** @test */
	public function assetUrlIsBasedOnCurrentRequestRoot()
	{
		list($router, $url) = $this->makeRouterAndGenerator($request = Request::create('http://some.host/'));
		$router->addRoute('get', 'foo/{v}', function() {}, 'name');
		$this->assertEquals('//some.host/foo/bar', $url->getRouteUrl('name', ['bar']));
	}

	/** @test */
	public function canGenerateRelativeAssetUrls()
	{
		list($router, $url) = $this->makeRouterAndGenerator($request = Request::create('http://some.host/'));
		$this->assertEquals('/foo/bar', $url->getAssetUrl('foo/bar', true));
	}

	/** @test */
	public function canGenerateRelativeAssetUrlInSubdirectory()
	{
		$server = [
			'PHP_SELF' => '/path/to/subdir/index.php',
			'SCRIPT_FILENAME' => '/subdir/index.php',
			'SCRIPT_NAME' => '/subdir/index.php',
		];
		$request = Request::create('http://some.host/subdir/bar/baz', 'GET', [], [], [], $server);
		list($router, $url) = $this->makeRouterAndGenerator($request);
		$this->assertEquals('/subdir/foo/bar', $url->getAssetUrl('foo/bar', true));
	}

	/** @test */
	public function canSetAssetRoot()
	{
		list($router, $url) = $this->makeRouterAndGenerator($request = Request::create('/foo/foo'));
		$url->setAssetRoot('//some.cdn.com');
		$this->assertEquals('//some.cdn.com/foo/bar', $url->getAssetUrl('foo/bar'));
	}
}
