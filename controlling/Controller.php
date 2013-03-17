<?php

require_once(dirname(__FILE__) . "/../logging/Logger.php");

Class Controller {
	
	protected static function perfmon($enter) {
		global $PERFMON;
		if (@$PERFMON) {
			if ($enter)
				$PERFMON->enter("controller");
			else
				$PERFMON->leave("controller");
		}
	}

	protected static function log($level, $s) {
		global $LOGGER;
		if (@$LOGGER)
			$LOGGER->message("framework.control", $level, $s);
	}

	public function dispatch($action, $args = array()) {
		static::perfmon(true);
		$result = (method_exists($this, $action) && $this->before_filter($action, $args)) &&
		          (!method_exists($this, "before_filter_" . $action) ||
		           call_user_method_array("before_filter_" . $action, $this, $args));
		if ($result) {
			$class = get_called_class();
			self::log(Logger::INFO_2, "Dispatch action '{$action}' on controller '{$class}'");
			call_user_method_array($action, $this, $args);
		}
		static::perfmon(true);
		return $result;
	}
	
	protected function before_filter($action, $args = array()) {
		return TRUE;
	}
	
}
