<?php

/*
 * This file is part of the Indigo Supervisor component.
 *
 * (c) Indigo Development Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Indigo\Admin;

use Admin\Controller_Base;
use Indigo\Supervisor\Exception\SupervisorException;

/**
 * Supervisor Controller
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class Controller_Admin extends \Admin\Controller_Base
{
	public function before($data = null)
	{
		parent::before($data);

		\Config::load('supervisor', true);
	}

	/**
	 * Returns all Supervisor instances
	 *
	 * @return array
	 */
	public function get_instances()
	{
		$keys = array_keys(\Config::get('supervisor.supervisor', array()));
		$instances = array();

		foreach ($keys as $instance)
		{
			$i = $this->get_instance($instance);

			try
			{
				if ($i->isRunning() === false)
				{
					continue;
				}
			}
			catch (\Exception $e)
			{
				continue;
			}

			$instances[$instance] = $i;
		}

		return $instances;
	}

	/**
	 * Returns one specific instance
	 *
	 * @param string $instance
	 *
	 * @return Indigo\Supervisor\Supervisor
	 */
	public function get_instance($instance)
	{
		return \Supervisor::forge($instance);
	}

	/**
	 * Checks whether instance exists
	 *
	 * @param string  $instance
	 *
	 * @return boolean
	 */
	public function has_instance($instance)
	{
		$keys = \Config::get('supervisor.supervisor', array());

		return array_key_exists($instance, $keys);
	}

	/**
	 * Checks whether instance exists
	 *
	 * @param string $instance
	 *
	 * @throws HttpNotFoundException If $instance is not found
	 */
	public function check_instance($instance)
	{
		if ($this->has_instance($instance) === false)
		{
			throw new \HttpNotFoundException();
		}
	}

	/**
	 * Checks whether user has access
	 *
	 * @param string $access
	 * @param string $type
	 *
	 * @throws HttpForbiddenException If user has no $access
	 */
	public function check_access($access, $type = 'supervisor')
	{
		if (\Auth::has_access('supervisor.'. $type .'[' . $access . ']') === false)
		{
			throw new \HttpForbiddenException();
		}
	}

	/**
	 * Restarts one or all instances
	 *
	 * @param string $instance
	 * @param string $action
	 */
	public function instance_action($instance, $action)
	{
		is_null($instance) or $this->check_instance($instance);
		$this->check_access($action);

		if ($instance === null)
		{
			$this->check_access('all');
			$instances = $this->get_instances();
		}
		else
		{
			$instances = [$this->get_instance($instance)];
		}

		foreach ($instances as $instance)
		{
			try
			{
				$instance->$action();
			}
			catch (SupervisorException $e)
			{
			}
		}

		return \Response::redirect_back();
	}

	/**
	 * Restarts one or all processes of an instance
	 *
	 * @param string $instance
	 * @param string $process
	 * @param string $action
	 */
	public function process_action($instance, $process, $action)
	{
		is_null($instance) or $this->check_instance($instance);
		$this->check_access($action, 'process');

		$instance = $this->get_instance($instance);

		if ($process === null)
		{
			$this->check_access('all', 'process');
			$processes = $instance->getAllProcesses();
		}
		else
		{
			$processes = [$instance->getProcess($process)];
		}

		foreach ($processes as $process)
		{
			try
			{
				$process->$action(false);
			}
			catch (SupervisorException $e)
			{
			}
		}

		return \Response::redirect_back();
	}

	public function action_index()
	{
		$this->check_access('list');

		$this->template->content = $this->theme->view('supervisor/index');
		$this->template->content->set('instances', $this->get_instances(), false);
	}

	public function action_view($instance)
	{
		$this->check_instance($instance);
		$this->check_access('view');

		$this->template->content = $this->theme->view('supervisor/view');
		$this->template->content->set('name', $instance);
		$this->template->content->set('instance', $this->get_instance($instance), false);
	}

	public function action_restart($instance = null)
	{
		return $this->instance_action($instance, 'restart');
	}

	public function action_shutdown($instance = null)
	{
		return $this->instance_action($instance, 'shutdown');
	}

	public function action_start_process($instance, $process = null)
	{
		return $this->process_action($instance, $process, 'start');
	}

	public function action_restart_process($instance, $process = null)
	{
		return $this->process_action($instance, $process, 'restart');
	}

	public function action_stop_process($instance, $process = null)
	{
		return $this->process_action($instance, $process, 'stop');
	}
}
