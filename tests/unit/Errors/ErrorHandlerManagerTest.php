<?php

use Mockery as m;

use Autarky\Errors\ErrorHandlerManager;
use Autarky\Errors\HandlerResolver;
use Autarky\Container\Container;
use Symfony\Component\HttpFoundation\Response;

class ErrorHandlerManagerTest extends PHPUnit_Framework_TestCase
{
	public function tearDown()
	{
		m::close();
	}

	protected function makeHandler()
	{
		return new ErrorHandlerManager(
			new HandlerResolver(new Container)
		);
	}

	/** @test */
	public function prependedHandlersGetPriority()
	{
		$handler = $this->makeHandler();
		$handler->appendHandler(function(Exception $e) { return 'foo'; });
		$handler->prependHandler(function(Exception $e) { return 'bar'; });
		$result = $handler->handle(new Exception);
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $result);
		$this->assertEquals('bar', $result->getContent());
		$this->assertEquals(500, $result->getStatusCode());
	}

	/** @test */
	public function wrongTypehintHandlersAreNotCalled()
	{
		$handler = $this->makeHandler();
		$handler->appendHandler(function(Exception $e) { return 'foo'; });
		$handler->prependHandler(function(\RuntimeException $e) { return 'bar'; });
		$result = $handler->handle(new \InvalidArgumentException);
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $result);
		$this->assertEquals('foo', $result->getContent());
		$this->assertEquals(500, $result->getStatusCode());
	}

	/** @test */
	public function defaultHandlerIsCalledWhenNoHandlersMatchTypeHint()
	{
		$handler = $this->makeHandler();
		$handler->appendHandler(function(\OutOfBoundsException $e) { return 'OutOfBoundsException'; });
		$handler->appendHandler(function(\InvalidArgumentException $e) { return 'InvalidArgumentException'; });
		$handler->setDefaultHandler($mock = m::mock('Autarky\Errors\ErrorHandlerInterface'));
		$exception = new \RuntimeException;
		$mock->shouldReceive('handle')->with($exception)->once()->andReturn('default handler');
		$result = $handler->handle($exception);
		$this->assertEquals('default handler', $result->getContent());
		$this->assertEquals(500, $result->getStatusCode());
	}

	/** @test */
	public function httpExceptionsAreGivenCorrectStatusCodes()
	{
		$handler = $this->makeHandler();
		$handler->setDefaultHandler($mock = m::mock('Autarky\Errors\ErrorHandlerInterface'));
		$mock->shouldReceive('handle')->andReturnUsing(function() {
			return new Response('default handler');
		});

		$response = $handler->handle(new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException);
		$this->assertEquals(403, $response->getStatusCode());

		$response = $handler->handle(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException);
		$this->assertEquals(404, $response->getStatusCode());

		$response = $handler->handle(new \Symfony\Component\HttpKernel\Exception\ConflictHttpException);
		$this->assertEquals(409, $response->getStatusCode());
	}

	/** @test */
	public function handlersAreResolved()
	{
		$manager = new ErrorHandlerManager(
			new HandlerResolver($container = new Container)
		);
		$manager->prependHandler(__NAMESPACE__.'\\StubHandler');
		$container->params(__NAMESPACE__.'\\StubHandler', ['$ret' => 'resolved handler']);
		$response = $manager->handle(new Exception);
		$this->assertEquals('resolved handler', $response->getContent());
	}

	/** @test */
	public function handlersCanThrowExceptions()
	{
		$handler = $this->makeHandler();
		$handler->appendHandler(function(\InvalidArgumentException $e) { return 'foo'; });
		$handler->appendHandler(function(\RuntimeException $e) { throw new \InvalidArgumentException; });
		$result = $handler->handle(new \RuntimeException);
		$this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $result);
		$this->assertEquals('foo', $result->getContent());
		$this->assertEquals(500, $result->getStatusCode());
	}
}

class StubHandler implements \Autarky\Errors\ErrorHandlerInterface {
	public function __construct($ret) {
		$this->ret = $ret;
	}
	public function handle(Exception $e) {
		return $this->ret;
	}
}
