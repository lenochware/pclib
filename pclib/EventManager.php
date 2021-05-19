<?php
/**
 * @file
 * Event manager.
 *
 * @author -dk- <lenochware@gmail.com>
 * http://pclib.brambor.net/
 */

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

namespace pclib;
use pclib;

/**
 * Event manager provides possibility to register event listeners and trigger events.
 */
class EventManager /*extends system\BaseObject*/ implements IService
{
	/** Array of listeners - store all attached event callbacks. */
	protected $listeners = [];

	/**
	 * Attach function $fn to event $name.
	 */
	function add($name, callable $fn, $target = null)
	{
		if (!is_callable($fn)) {
			throw new Exception("Event must be function or method.");
		}

		if (!isset($this->listeners[$name])) $this->listeners[$name] = [];

		$this->listeners[$name][] = [
			'fn' => $fn,
			'target' => $target,
		];
	}

	/**
	 * Remove listeners from event $name.
	 */
	function remove($name, $target = null)
	{
		if (empty($this->listeners[$name])) return;

		if (!$target) {
			$this->listeners[$name] = [];
			return;
		}

		foreach ($this->listeners[$name] as $i => $listener) {
			if ($listener['target'] !== $target) continue;
			unset($this->listeners[$name][$i]);
		}
	}

	/**
	 * Attach function $fn to event $name.
	 */
	function on($name, $fn, $target = null)
	{
		if ($fn) {
			$this->add($name, $fn, $target);
		}
		else {
			$this->remove($name, $target);
		}
	}

	/**
	 * Will return array of listeners, attached to event $name.
	 */
	function attached($name, $target = null)
	{
		$listeners = isset($this->listeners[$name]) ? $this->listeners[$name]  : [];

		foreach ($listeners as $i => $listener) {
			if ($listener['target'] and $listener['target'] !== $target) {
				unset($listeners[$i]);
			}
		}

		return $listeners;
	}

	/**
	 * Trigger event $name.
	 */
	function trigger($name, array $data = [], $target = null)
	{
		if (empty($this->listeners[$name])) return false;
		
		$listeners = $this->attached($name, $target);

		if (!$listeners) return false;

		$event = new Event($name, $data, $target);

		foreach ($listeners as $listener)
		{
			if (!$event->propagate) return $event;
			$event->result = call_user_func($listener['fn'], $event);
		}

		return $event;
	}


} //class Eventmng

/**
 * Representation of particular event.
 * When any event is triggered, Event object is created and passed into event handler.
 */
class Event
{

/** If set to false, event propagation stop immediatelly. */
public $propagate = true;

/** Object which triggered this event. */
public $target;

/** Event name */
public $name;

/** Value returned by event handler */
public $result;

/**
 * Event data. You can access data like object attributes too.
 * Ex: $event->data['sql'] is the same like $event->sql.
 */
public $data;

function __construct($name, $data, $target)
{
	$this->name = $name;
	$this->data = $data;
	$this->target = $target;
}

function stopPropagation()
{
	$this->propagate = false;
}

public function __get($name)
{
	return $this->data[$name];
}

} //class Event

?>