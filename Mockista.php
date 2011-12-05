<?php

namespace Mockista;

function mock($defaults = array())
{
	return MockFactory::create($defaults);
}

class MockFactory
{
	static function create($defaults = array())
	{
		$mock = new Mock();
		foreach ($defaults as $key=>$default) {
			if ($default instanceof \Closure) {
				$mock->$key()->andCallback($default);
			} else {
				$mock->$key = $default;
			}
		}
		return $mock;
	}
}

if (class_exists("\PHPUnit_Framework_AssertionFailedError")) {
	class MockException extends \PHPUnit_Framework_AssertionFailedError
	{
		const CODE_EXACTLY = 1;
		const CODE_AT_LEAST = 2;
		const CODE_NO_MORE_THAN = 3;
		const CODE_INVALID_ARGS = 4;
	}
} else {
	class MockException extends \Exception
	{
		const CODE_EXACTLY = 1;
		const CODE_AT_LEAST = 2;
		const CODE_NO_MORE_THAN = 3;
		const CODE_INVALID_ARGS = 4;
	}
}

class Mock extends MockObject implements MockInterface
{
	protected $__mode = self::MODE_LEARNING;

	public function freeze()
	{
		$this->__mode = self::MODE_COLLECTING;
		return $this;
	}


	public function __call($name, $args)
	{
		$hash = $this->hashArgs($args);
		$this->checkMethodsNamespace($name);
		if (self::MODE_LEARNING == $this->__mode) {
			$this->__methods[$name][$hash] = new MockMethod($name, $args);
			return $this->__methods[$name][$hash];
		} else if (self::MODE_COLLECTING == $this->__mode) {
			$useHash = $this->useHash($name, $args, $hash);
			return $this->__methods[$name][$useHash]->invoke($args);
		}
	}
}

class MockObject
{
	const MODE_LEARNING = 1;
	const MODE_COLLECTING = 2;

	protected $__metods = array();

	public function assertExpectations()
	{
		foreach ($this->__methods as $method) {
			foreach ($method as $argCombinationMethod) {
				$argCombinationMethod->assertExpectations();
			}
		}
	}

	protected function hashArgs($args)
	{
		if (array() == $args) {
			return 0;
		} else {
		       return md5(serialize($args));
		}
	}

	protected function useHash($name, $args, $hash)
	{
		if ($hash !== 0 && isset($this->__methods[$name][$hash])) {
			return $hash;
		} else if (isset($this->__methods[$name][0])) {
			return 0;
		} else {
			$argsStr = var_export($args, true);
			throw new MockException("Unexpected call in method: $name args: $argsStr", MockException::CODE_INVALID_ARGS);
		}
	}

	protected function checkMethodsNamespace($name)
	{
		if (! isset($this->__methods[$name])) {
			$this->__methods[$name] = array();
		}
	}
}


class MockMethod extends MockObject implements MethodInterface
{
	const CALL_TYPE_EXACTLY = 1;
	const CALL_TYPE_AT_LEAST = 2;
	const CALL_TYPE_NO_MORE_THAN = 3;

	const INVOKE_STRATEGY_RETURN = 1;
	const INVOKE_STRATEGY_THROW = 2;
	const INVOKE_STRATEGY_CALLBACK = 3;

	private $args;

	private $callType;
	private $callCount;

	private $invokeStrategy;
	private $invokeValue;

	private $name = '';

	private $callCountReal = 0;


	public function __construct($name, $args)
	{
		$this->name = $name;
		$this->args = $args;
	}

	public function __call($name, $args)
	{
	}

	public function assertExpectations()
	{
		$passed = true;
		$message = "";
		$code = 0;

		switch ($this->callType) {
			case self::CALL_TYPE_EXACTLY:
				$passed = $this->callCount == $this->callCountReal;
				$message = "Expected {$this->name} {$this->callCount} and called {$this->callCountReal}";
				$code = MockException::CODE_EXACTLY;
				break;

			case self::CALL_TYPE_AT_LEAST:
				$passed = $this->callCount <= $this->callCountReal;
				$message = "Expected {$this->name} at least {$this->callCount} and called {$this->callCountReal}";
				$code = MockException::CODE_AT_LEAST;
				break;

			case self::CALL_TYPE_NO_MORE_THAN:
				$passed = $this->callCount >= $this->callCountReal;
				$message = "Expected {$this->name} no more than {$this->callCount} and called {$this->callCountReal}";
				$code = MockException::CODE_NO_MORE_THAN;
				break;
			
			default:
				break;
		}

		if (! $passed) {
			throw new MockException($message, $code);
		}
	}
	
	public function invoke($args)
	{
		switch ($this->invokeStrategy) {
			case self::INVOKE_STRATEGY_RETURN:
				$this->callCountReal++;
				return $this->invokeValue;
				break;
			case self::INVOKE_STRATEGY_THROW:
				$this->callCountReal++;
				throw $this->invokeValue;
				break;
			case self::INVOKE_STRATEGY_CALLBACK:
				$this->callCountReal++;
				return call_user_func_array($this->invokeValue, $args);
			
			default:
				$this->callCountReal++;
				return;
				break;
		}
	}

	public function once()
	{
		$this->callType = self::CALL_TYPE_EXACTLY;
		$this->callCount = 1;
		return $this;
	}
	
	public function twice()
	{
		$this->callType = self::CALL_TYPE_EXACTLY;
		$this->callCount = 2;
		return $this;
	}
	
	public function never()
	{
		$this->callType = self::CALL_TYPE_EXACTLY;
		$this->callCount = 0;
		return $this;
	}
	
	public function exactly($count)
	{
		$this->callType = self::CALL_TYPE_EXACTLY;
		$this->callCount = $count;
		return $this;
	}
	

	public function atLeastOnce()
	{
		$this->callType = self::CALL_TYPE_AT_LEAST;
		$this->callCount = 1;
		return $this;
	}

	public function atLeast($count)
	{
		$this->callType = self::CALL_TYPE_AT_LEAST;
		$this->callCount = $count;
		return $this;
	}

	public function noMoreThanOnce()
	{
		$this->callType = self::CALL_TYPE_NO_MORE_THAN;
		$this->callCount = 1;
		return $this;
	}

	public function noMoreThan($count)
	{
		$this->callType = self::CALL_TYPE_NO_MORE_THAN;
		$this->callCount = $count;
		return $this;

	}

	public function andReturn($returnValue)
	{
		$this->invokeStrategy = self::INVOKE_STRATEGY_RETURN;
		$this->invokeValue = $returnValue;
		return $this;
	}

	public function andThrow($throwException)
	{
		$this->invokeStrategy = self::INVOKE_STRATEGY_THROW;
		$this->invokeValue = $throwException;
		return $this;
	}

	public function andCallback($callback)
	{
		$this->invokeStrategy = self::INVOKE_STRATEGY_CALLBACK;
		$this->invokeValue = $callback;
		return $this;
	}

	public function __get($name)
	{
		return $this->$name();
	}

}
