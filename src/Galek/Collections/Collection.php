<?php
/**
 * Created by PhpStorm.
 * User: Galek
 * Date: 18.1.2018
 */
declare(strict_types=1);

namespace Galek\Collections;


use Traversable;

class Collection implements \IteratorAggregate, \Countable, \JsonSerializable, \ArrayAccess
{

	protected $list;


	public function __construct(array $items = null)
	{
		if (null !== $items) {
			$this->collect($items);
		}
	}


	public function collect(array $items)
	{
		foreach ($items as $item) {
			$this->add($item);
		}

		return $this;
	}


	public function add($item)
	{
		$this->list[] = $item;
		return $this;
	}


	public function remove($index)
	{
		unset($this->list[$index]);
		return $this;
	}


	public function get(int $index)
	{
		return $this->list[$index];
	}


	public function all(): array
	{
		return $this->list;
	}


	public function map(callable $callback)
	{
		$list = array_map($callback, $this->list);
		return (new static())->collect($list);
	}


	public function filter(callable $callback)
	{
		$list = array_filter($this->list, $callback);
		return (new static())->collect($list);
	}


	public function sort(callable $callback = null)
	{
		$list = $this->list;

		$callback
			? uasort($list, $callback)
			: asort($list);

		return (new static())->collect($list);
	}


	public function each(callable $callback)
	{
		foreach ($this->list as $index => $item) {
			if (!$callback($item, $key)) {
				break;
			}
		}
		return $this;
	}


	public function slice(int $offset, $length = null)
	{
		$list = \array_slice($this->list, $offset, $length, true);
		return (new static())->collect($list);
	}


	public function reduce(callable $callback, $initial = null)
	{
		return array_reduce($this->list, $callback, $initial);
	}


	public function take(int $limit)
	{
		if ($limit < 0) {
			return $this->slice($limit, abs($limit));
		}

		return $this->slice(0, $limit);
	}


	public function values()
	{
		$list = array_values($this->list);
		return (new static())->collect($list);
	}


	public function push($value)
	{
		$this->offsetSet(null, $value);
		return $this;
	}


	public function nth($step, $offset = 0)
	{
		$list = [];
		$pos = 0;

		foreach ($this->list as $item) {
			if ($pos % $step === $offset) {
				$list[] = $item;
			}
			$pos++;
		}

		return (new static())->collect($list);
	}


	public function isEmpty()
	{
		return empty($this->list);
	}


	public function isNotEmpty()
	{
		return !$this->isEmpty();
	}


	public function has($key)
	{
		$keys = \is_array($key) ? $key : \func_get_args();

		foreach ($keys as $value) {
			if (!$this->offsetExists($value)) {
				return false;
			}
		}

		return true;
	}


	public function when($value, callable $callback, callable $default = null)
	{
		if ($value) {
			return $callback($this, $value);
		} elseif ($default) {
			return $default($this, $value);
		}

		return $this;
	}


	public function sum($callback = null)
	{
		if (null === $callback) {
			return array_sum($this->list);
		}

		$callback = $this->valueRetriever($callback);

		return $this->reduce(function($result, $item) use ($callback) {
			return $result + $callback($item);
		}, 0);
	}


	protected function valueRetriever($value)
	{
		if ($this->useAsCallable($value)) {
			return $value;
		}

		return function ($item) use ($value) {
			return $this->dataGet($item, $value);
		};
	}


	protected function useAsCallable($value)
	{
		return !\is_string($value) && \is_callable($value);
	}


	/**
	 * @inheritdoc
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->list);
	}


	/**
	 * @inheritdoc
	 */
	public function count()
	{
		return \count($this->list);
	}


	/**
	 * @inheritdoc
	 */
	public function jsonSerialize()
	{
		return json_encode($this->list);
	}


	/**
	 * @inheritdoc
	 */
	public function offsetExists($key)
	{
		return array_key_exists($key);
	}


	/**
	 * @inheritdoc
	 */
	public function offsetGet($key)
	{
		return $this->list[$key];
	}


	/**
	 * @inheritdoc
	 */
	public function offsetSet($key, $value)
	{
		if (null === $key) {
			$this->list[] = $value;
		} else {
			$this->list[$key] = $value;
		}
	}


	/**
	 * @inheritdoc
	 */
	public function offsetUnset($key)
	{
		unset($this->list[$key]);
	}


	protected function dataGet($target, $key, $default = null)
	{
		if (null === $key) {
			return $target;
		}
		$key = \is_array($key) ? $key : explode('.', $key);
		while (! is_null($segment = array_shift($key))) {
			if ($segment === '*') {
				if ($target instanceof Collection) {
					$target = $target->all();
				} elseif (! \is_array($target)) {
					return value($default);
				}
				$result = Arr::pluck($target, $key);
				return \in_array('*', $key) ? Arr::collapse($result) : $result;
			}
			if (Arr::accessible($target) && Arr::exists($target, $segment)) {
				$target = $target[$segment];
			} elseif (\is_object($target) && isset($target->{$segment})) {
				$target = $target->{$segment};
			} else {
				return value($default);
			}
		}
		return $target;
	}
}
